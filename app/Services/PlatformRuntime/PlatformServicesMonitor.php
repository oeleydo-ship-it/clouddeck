<?php

namespace App\Services\PlatformRuntime;

use App\Services\PlatformRuntime\Contracts\PlatformProcessLauncher;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Read-only status plus start/stop orchestration for control-plane runtime services.
 */
final class PlatformServicesMonitor
{
    public const SERVICES = ['redis', 'horizon', 'queue', 'reverb'];

    public function __construct(
        private readonly PlatformProcessLauncher $launcher,
        private readonly PlatformPidStore $pids,
        private readonly PlatformSslMonitor $ssl,
    ) {}

    /**
     * @return array{
     *     platform: string,
     *     windows: bool,
     *     pcntl: bool,
     *     horizon_recommended: bool,
     *     services: array<string, array<string, mixed>>,
     *     ssl: array<string, mixed>,
     *     polled_at: string
     * }
     */
    public function status(): array
    {
        $windows = PHP_OS_FAMILY === 'Windows';
        $pcntl = extension_loaded('pcntl') && extension_loaded('posix');

        return [
            'platform' => PHP_OS_FAMILY,
            'windows' => $windows,
            'pcntl' => $pcntl,
            'horizon_recommended' => $pcntl && ! $windows,
            'services' => [
                'redis' => $this->redisStatus(),
                'horizon' => $this->horizonStatus($pcntl, $windows),
                'queue' => $this->queueStatus(),
                'reverb' => $this->reverbStatus(),
            ],
            'ssl' => $this->ssl->status(),
            'polled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{ok: bool, message: string, ssl: array<string, mixed>}
     */
    public function renewSsl(): array
    {
        return $this->ssl->renew();
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    public function start(string $service): array
    {
        return match ($service) {
            'redis' => $this->startRedis(),
            'horizon' => $this->startHorizon(),
            'queue' => $this->startQueue(),
            'reverb' => $this->startReverb(),
            default => ['ok' => false, 'message' => 'Unknown service.', 'service' => []],
        };
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    public function stop(string $service): array
    {
        return match ($service) {
            'redis' => $this->stopRedis(),
            'horizon' => $this->stopTrackedOrTerminate('horizon', ['horizon:terminate']),
            'queue' => $this->stopTracked('queue'),
            'reverb' => $this->stopTracked('reverb'),
            default => ['ok' => false, 'message' => 'Unknown service.', 'service' => []],
        };
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    public function restart(string $service): array
    {
        $stop = $this->stop($service);

        if (! $stop['ok'] && ($stop['service']['status'] ?? null) !== 'stopped') {
            // Already stopped is fine for restart; real failures are not.
            if (! str_contains(strtolower($stop['message']), 'already')) {
                return $stop;
            }
        }

        return $this->start($service);
    }

    /**
     * @return array<string, mixed>
     */
    private function redisStatus(): array
    {
        $host = (string) config('database.redis.default.host', '127.0.0.1');
        $port = (int) config('database.redis.default.port', 6379);
        $container = (string) config('platform-services.redis_docker_container', 'uplary-redis');
        $docker = $this->dockerContainerState($container);

        $pingOk = false;
        $error = null;

        try {
            $pong = Redis::connection()->ping();
            $pingOk = $pong === true || $pong === '+PONG' || $pong === 'PONG' || $pong === 1;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $status = $pingOk ? 'running' : 'stopped';
        $detail = sprintf('%s:%d · PING %s', $host, $port, $pingOk ? 'ok' : 'failed');

        if ($docker['available']) {
            $detail .= sprintf(' · Docker `%s` %s', $container, $docker['running'] ? 'running' : 'not running');
        } elseif ($docker['message']) {
            $detail .= ' · '.$docker['message'];
        }

        if ($error && ! $pingOk) {
            $detail .= ' · '.mb_substr($error, 0, 120);
        }

        $canDockerControl = $docker['available'] && $docker['exists'];

        return [
            'key' => 'redis',
            'name' => 'Redis',
            'status' => $status,
            'detail' => $detail,
            'note' => 'This panel never kills a system Redis process. Start/stop only the configured Docker container when Docker is available.',
            'link' => null,
            'meta' => [
                'host' => $host,
                'port' => $port,
                'ping' => $pingOk,
                'docker_container' => $container,
                'docker_available' => $docker['available'],
                'docker_exists' => $docker['exists'],
                'docker_running' => $docker['running'],
            ],
            'actions' => [
                'start' => $canDockerControl && ! $docker['running'],
                'stop' => $canDockerControl && $docker['running'],
                'restart' => $canDockerControl,
            ],
            'last_error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function horizonStatus(bool $pcntl, bool $windows): array
    {
        $installed = class_exists(\Laravel\Horizon\Horizon::class);
        $pid = $this->pids->read('horizon');
        $pidRunning = $pid !== null && $this->launcher->isRunning($pid);
        $masters = $this->horizonMastersActive();
        $running = $pidRunning || $masters;

        if ($pid !== null && ! $pidRunning) {
            $this->pids->clear('horizon');
            $pid = null;
        }

        $status = match (true) {
            ! $installed => 'unavailable',
            $running => 'running',
            $windows || ! $pcntl => 'unavailable',
            default => 'stopped',
        };

        $detail = match (true) {
            ! $installed => 'laravel/horizon is not installed',
            $running && $pid => "Master running · PID {$pid}",
            $running => 'Horizon masters detected via Redis',
            $windows || ! $pcntl => 'Requires pcntl/posix (not available on this PHP build)',
            default => 'Not running',
        };

        $note = $windows || ! $pcntl
            ? 'Horizon needs pcntl and posix. On Windows, use the Queue workers card (`queue:work`) instead.'
            : 'Dashboard: /horizon (super admins always allowed).';

        $canStart = $installed && $pcntl && ! $windows && ! $running;
        $canStop = $running;

        return [
            'key' => 'horizon',
            'name' => 'Horizon',
            'status' => $status,
            'detail' => $detail,
            'note' => $note,
            'link' => $installed ? '/horizon' : null,
            'meta' => [
                'installed' => $installed,
                'pcntl' => $pcntl,
                'pid' => $pid,
                'masters' => $masters,
            ],
            'actions' => [
                'start' => $canStart,
                'stop' => $canStop,
                'restart' => $canStart || $canStop,
            ],
            'last_error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueStatus(): array
    {
        $queues = $this->queueList();
        $queueCsv = implode(',', $queues);
        $pid = $this->pids->read('queue');
        $pidRunning = $pid !== null && $this->launcher->isRunning($pid);

        if ($pid !== null && ! $pidRunning) {
            $this->pids->clear('queue');
            $pid = null;
        }

        $horizonRunning = ($this->horizonStatus(
            extension_loaded('pcntl') && extension_loaded('posix'),
            PHP_OS_FAMILY === 'Windows'
        )['status'] ?? null) === 'running';

        $status = match (true) {
            $pidRunning => 'running',
            $horizonRunning => 'degraded',
            default => 'stopped',
        };

        $detail = match (true) {
            $pidRunning => "queue:work · PID {$pid} · queues {$queueCsv}",
            $horizonRunning => 'Jobs may be processed by Horizon (no local queue:work PID)',
            default => "Not running · would use queues {$queueCsv}",
        };

        return [
            'key' => 'queue',
            'name' => 'Queue workers',
            'status' => $status,
            'detail' => $detail,
            'note' => 'Starts `php artisan queue:work` with the same queues as Horizon. Prefer Horizon on Linux.',
            'link' => null,
            'meta' => [
                'queues' => $queues,
                'pid' => $pid,
                'covered_by_horizon' => $horizonRunning,
            ],
            'actions' => [
                'start' => ! $pidRunning,
                'stop' => $pidRunning,
                'restart' => true,
            ],
            'last_error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reverbStatus(): array
    {
        $installed = class_exists(\Laravel\Reverb\Reverb::class)
            || class_exists(\Laravel\Reverb\ReverbServiceProvider::class);
        $host = (string) config('reverb.servers.reverb.host', '0.0.0.0');
        $port = (int) config('reverb.servers.reverb.port', 8080);
        $checkHost = in_array($host, ['0.0.0.0', '::', ''], true) ? '127.0.0.1' : $host;
        $listening = $this->portOpen($checkHost, $port);
        $pid = $this->pids->read('reverb');
        $pidRunning = $pid !== null && $this->launcher->isRunning($pid);

        if ($pid !== null && ! $pidRunning) {
            $this->pids->clear('reverb');
            $pid = null;
        }

        $running = $pidRunning || $listening;
        $status = match (true) {
            ! $installed => 'unavailable',
            $running => 'running',
            default => 'stopped',
        };

        $detail = match (true) {
            ! $installed => 'laravel/reverb is not installed',
            $pidRunning && $listening => "Listening on {$checkHost}:{$port} · PID {$pid}",
            $listening => "Port {$port} is open (process may be external)",
            $pidRunning => "PID {$pid} tracked but port {$port} not accepting connections",
            default => "Not listening on {$checkHost}:{$port}",
        };

        if ($pidRunning && ! $listening) {
            $status = 'degraded';
        }

        return [
            'key' => 'reverb',
            'name' => 'Reverb',
            'status' => $status,
            'detail' => $detail,
            'note' => 'Starts `php artisan reverb:start`. Stop only terminates a PID started from this panel.',
            'link' => null,
            'meta' => [
                'installed' => $installed,
                'host' => $host,
                'port' => $port,
                'listening' => $listening,
                'pid' => $pid,
            ],
            'actions' => [
                'start' => $installed && ! $running,
                'stop' => $pidRunning,
                'restart' => $installed && ($pidRunning || ! $running),
            ],
            'last_error' => null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    private function startRedis(): array
    {
        $container = (string) config('platform-services.redis_docker_container', 'uplary-redis');
        $docker = $this->dockerContainerState($container);

        if (! $docker['available']) {
            return [
                'ok' => false,
                'message' => $docker['message'] ?: 'Docker is not available. Start Redis manually or with Docker Desktop.',
                'service' => $this->redisStatus(),
            ];
        }

        if (! $docker['exists']) {
            return [
                'ok' => false,
                'message' => "Docker container `{$container}` was not found. Create it (e.g. `docker run -d --name {$container} -p 6379:6379 redis:alpine`) then try again.",
                'service' => $this->redisStatus(),
            ];
        }

        if ($docker['running']) {
            return ['ok' => true, 'message' => 'Redis Docker container is already running.', 'service' => $this->redisStatus()];
        }

        $result = $this->launcher->run(['docker', 'start', $container]);

        if ($result['exit_code'] !== 0) {
            return [
                'ok' => false,
                'message' => trim($result['error'] ?: $result['output']) ?: "Failed to start Docker container `{$container}`.",
                'service' => $this->redisStatus(),
            ];
        }

        return ['ok' => true, 'message' => "Started Docker container `{$container}`.", 'service' => $this->redisStatus()];
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    private function stopRedis(): array
    {
        $container = (string) config('platform-services.redis_docker_container', 'uplary-redis');
        $docker = $this->dockerContainerState($container);

        if (! $docker['available'] || ! $docker['exists']) {
            return [
                'ok' => false,
                'message' => 'Redis stop is limited to the configured Docker container when Docker is available. System Redis is never killed from this panel.',
                'service' => $this->redisStatus(),
            ];
        }

        if (! $docker['running']) {
            return ['ok' => true, 'message' => 'Redis Docker container is already stopped.', 'service' => $this->redisStatus()];
        }

        $result = $this->launcher->run(['docker', 'stop', $container]);

        if ($result['exit_code'] !== 0) {
            return [
                'ok' => false,
                'message' => trim($result['error'] ?: $result['output']) ?: "Failed to stop Docker container `{$container}`.",
                'service' => $this->redisStatus(),
            ];
        }

        return ['ok' => true, 'message' => "Stopped Docker container `{$container}`.", 'service' => $this->redisStatus()];
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    private function startHorizon(): array
    {
        $windows = PHP_OS_FAMILY === 'Windows';
        $pcntl = extension_loaded('pcntl') && extension_loaded('posix');

        if ($windows || ! $pcntl) {
            return [
                'ok' => false,
                'message' => 'Horizon cannot run without pcntl/posix. Start Queue workers instead.',
                'service' => $this->horizonStatus($pcntl, $windows),
            ];
        }

        $current = $this->horizonStatus($pcntl, $windows);

        if ($current['status'] === 'running') {
            return ['ok' => true, 'message' => 'Horizon is already running.', 'service' => $current];
        }

        try {
            $pid = $this->launcher->startArtisan('horizon', ['horizon']);
            $this->pids->write('horizon', $pid);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'service' => $this->horizonStatus($pcntl, $windows)];
        }

        return ['ok' => true, 'message' => "Horizon started (PID {$pid}).", 'service' => $this->horizonStatus($pcntl, $windows)];
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    private function startQueue(): array
    {
        $current = $this->queueStatus();

        if (($current['meta']['pid'] ?? null) && $current['status'] === 'running') {
            return ['ok' => true, 'message' => 'Queue worker is already running.', 'service' => $current];
        }

        $queues = implode(',', $this->queueList());

        try {
            $pid = $this->launcher->startArtisan('queue', [
                'queue:work',
                'redis',
                '--queue='.$queues,
                '--sleep=1',
                '--tries=3',
                '--timeout=1800',
            ]);
            $this->pids->write('queue', $pid);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'service' => $this->queueStatus()];
        }

        return ['ok' => true, 'message' => "Queue worker started (PID {$pid}).", 'service' => $this->queueStatus()];
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    private function startReverb(): array
    {
        $current = $this->reverbStatus();

        if ($current['status'] === 'running') {
            return ['ok' => true, 'message' => 'Reverb is already running.', 'service' => $current];
        }

        if (! ($current['meta']['installed'] ?? false)) {
            return ['ok' => false, 'message' => 'laravel/reverb is not installed.', 'service' => $current];
        }

        try {
            $pid = $this->launcher->startArtisan('reverb', ['reverb:start']);
            $this->pids->write('reverb', $pid);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'service' => $this->reverbStatus()];
        }

        return ['ok' => true, 'message' => "Reverb started (PID {$pid}).", 'service' => $this->reverbStatus()];
    }

    /**
     * @param  list<string>  $terminateArtisan
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    private function stopTrackedOrTerminate(string $service, array $terminateArtisan): array
    {
        $pid = $this->pids->read($service);

        try {
            // Prefer Horizon's graceful terminate when available.
            if ($service === 'horizon') {
                $this->launcher->run(array_merge([PHP_BINARY, base_path('artisan')], $terminateArtisan), 30);
            }

            if ($pid !== null) {
                $this->launcher->stop($pid);
                $this->pids->clear($service);
            } elseif ($service !== 'horizon') {
                return ['ok' => true, 'message' => ucfirst($service).' was already stopped.', 'service' => $this->serviceStatus($service)];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'service' => $this->serviceStatus($service)];
        }

        return ['ok' => true, 'message' => ucfirst($service).' stop requested.', 'service' => $this->serviceStatus($service)];
    }

    /**
     * @return array{ok: bool, message: string, service: array<string, mixed>}
     */
    private function stopTracked(string $service): array
    {
        $pid = $this->pids->read($service);

        if ($pid === null) {
            return ['ok' => true, 'message' => ucfirst($service).' was already stopped.', 'service' => $this->serviceStatus($service)];
        }

        try {
            $this->launcher->stop($pid);
            $this->pids->clear($service);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'service' => $this->serviceStatus($service)];
        }

        return ['ok' => true, 'message' => ucfirst($service).' stopped.', 'service' => $this->serviceStatus($service)];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceStatus(string $service): array
    {
        $full = $this->status();

        return $full['services'][$service] ?? [];
    }

    /**
     * @return list<string>
     */
    private function queueList(): array
    {
        /** @var list<string> $queues */
        $queues = config('platform-services.queues', [
            'default', 'operations', 'deployments', 'provisioning', 'notifications', 'monitoring', 'billing',
        ]);

        return $queues;
    }

    private function horizonMastersActive(): bool
    {
        try {
            if (! interface_exists(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)) {
                return false;
            }

            $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();

            return is_array($masters) && count($masters) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function portOpen(string $host, int $port): bool
    {
        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, 0.5);

            if (is_resource($connection)) {
                fclose($connection);

                return true;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * @return array{available: bool, exists: bool, running: bool, message: string|null}
     */
    private function dockerContainerState(string $container): array
    {
        $version = $this->launcher->run(['docker', 'version', '--format', '{{.Server.Version}}']);

        if ($version['exit_code'] !== 0) {
            return [
                'available' => false,
                'exists' => false,
                'running' => false,
                'message' => 'Docker CLI not available',
            ];
        }

        $inspect = $this->launcher->run([
            'docker', 'inspect', '-f', '{{.State.Running}}', $container,
        ]);

        if ($inspect['exit_code'] !== 0) {
            return [
                'available' => true,
                'exists' => false,
                'running' => false,
                'message' => "Container `{$container}` not found",
            ];
        }

        $running = trim($inspect['output']) === 'true';

        return [
            'available' => true,
            'exists' => true,
            'running' => $running,
            'message' => null,
        ];
    }
}
