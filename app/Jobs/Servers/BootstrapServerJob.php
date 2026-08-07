<?php

namespace App\Jobs\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class BootstrapServerJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,InteractsWithServerProgress,Queueable,SerializesModels;

    public int $timeout = 1800;

    public function __construct(public readonly string $serverId) {}

    public function handle(SshClient $ssh): void
    {
        $server = Server::with('sshKey')->findOrFail($this->serverId);
        $this->progress($server, 35, 'Waiting for SSH', ServerStatus::Provisioning);
        $this->awaitSsh($server);
        $this->progress($server, 40, 'Bootstrapping Ubuntu', ServerStatus::Provisioning);
        $ssh->runScript($server, resource_path('scripts/bootstrap-ubuntu.sh'), [
            'PHP_VERSION' => config('clouddeck.default_php_version'),
            'PHP_VERSIONS' => implode(' ', config('clouddeck.php_versions')),
        ]);
        $this->progress($server, 90, 'Verifying services');
    }

    /**
     * A provider calls a machine "active" as soon as it powers on, which is well before
     * sshd accepts connections, and importing or retrying can equally arrive at a host
     * still coming up. Connecting straight away loses that race and fails the whole
     * provision on "connection refused" — a working server reported as broken.
     *
     * Waited here rather than through job retries: releasing this job back would also
     * re-run it after a genuine bootstrap failure, and repeating a half-finished
     * bootstrap is worse than reporting it.
     */
    private function awaitSsh(Server $server): void
    {
        $port = $server->ssh_port ?: 22;
        $timeout = max(0, (int) config('clouddeck.ssh_ready_timeout', 180));
        $deadline = now()->addSeconds($timeout);
        $error = 'no route to the host';

        while (true) {
            $socket = @fsockopen($server->public_ip, $port, $code, $error, 5);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            if (now()->gte($deadline)) {
                throw new RuntimeException("{$server->public_ip} did not accept SSH on port {$port} within {$timeout}s: {$error}");
            }

            sleep(5);
        }
    }
}
