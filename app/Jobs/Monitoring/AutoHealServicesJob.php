<?php

namespace App\Jobs\Monitoring;

use App\Enums\ServerStatus;
use App\Jobs\Operations\RunServerOperationJob;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\ServerOperation;
use App\Notifications\OperationalEventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class AutoHealServicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Agent service keys mapped to allowlisted restart operation types.
     * PostgreSQL is collected by the agent but has no allowlisted restart yet.
     *
     * @var array<string, string>
     */
    public const SERVICE_OPERATIONS = [
        'nginx' => 'nginx:restart',
        'php_fpm' => 'php:restart',
        'mysql' => 'mysql:restart',
        'redis' => 'redis:restart',
        'supervisor' => 'supervisor:restart',
    ];

    public function __construct(public readonly int $metricId)
    {
        $this->onQueue('monitoring');
    }

    public function handle(): void
    {
        $metric = ServerMetric::with('server.user')->find($this->metricId);
        if (! $metric) {
            return;
        }

        $server = $metric->server;
        if (! $server
            || ! $server->monitoring_enabled
            || ! $server->auto_heal_enabled
            || $server->status !== ServerStatus::Ready
        ) {
            return;
        }

        $services = $metric->services ?? [];
        if ($services === []) {
            return;
        }

        $required = max(1, (int) $server->auto_heal_consecutive_samples);
        $recent = ServerMetric::where('server_id', $server->id)
            ->latest('recorded_at')
            ->limit($required)
            ->get();

        $lastActions = $server->auto_heal_last_actions ?? [];
        $cooldownMinutes = max(1, (int) $server->auto_heal_cooldown_minutes);
        $healed = false;

        foreach (self::SERVICE_OPERATIONS as $service => $operationType) {
            if (! array_key_exists($service, $services) || $services[$service] !== false) {
                continue;
            }

            if (! $this->consistentlyDown($recent, $service, $required)) {
                continue;
            }

            if ($this->cooldownActive($lastActions[$service] ?? null, $cooldownMinutes)) {
                continue;
            }

            if ($this->hasActiveOperation($server, $operationType)) {
                continue;
            }

            $operation = $server->operations()->create([
                'user_id' => $server->user_id,
                'type' => $operationType,
                'target' => 'auto-heal:'.$service,
                'status' => 'pending',
            ]);

            RunServerOperationJob::dispatch($operation->id)->onQueue('operations');

            $lastActions[$service] = now()->toIso8601String();
            $healed = true;

            $label = str_replace('_', ' ', $service);
            $server->user?->notify(new OperationalEventNotification(
                event: 'auto_heal',
                title: 'Auto-heal queued on '.$server->name,
                body: ucfirst($label).' was reported down for '.$required.' consecutive samples. A '.$operationType.' was queued.',
                url: route('servers.manage', ['server' => $server, 'tab' => 'monitoring']),
                severity: 'warning',
                context: ['server_id' => $server->id, 'service' => $service, 'operation_id' => $operation->id],
            ));
        }

        if ($healed) {
            $server->update(['auto_heal_last_actions' => $lastActions]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ServerMetric>  $recent
     */
    private function consistentlyDown($recent, string $service, int $required): bool
    {
        if ($recent->count() < $required) {
            return false;
        }

        return $recent->every(function (ServerMetric $sample) use ($service): bool {
            $services = $sample->services ?? [];

            return array_key_exists($service, $services) && $services[$service] === false;
        });
    }

    private function cooldownActive(mixed $lastQueuedAt, int $cooldownMinutes): bool
    {
        if (! is_string($lastQueuedAt) || $lastQueuedAt === '') {
            return false;
        }

        try {
            return Carbon::parse($lastQueuedAt)->gt(now()->subMinutes($cooldownMinutes));
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasActiveOperation(Server $server, string $operationType): bool
    {
        return ServerOperation::where('server_id', $server->id)
            ->where('type', $operationType)
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }
}
