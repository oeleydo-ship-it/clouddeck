<?php

namespace App\Jobs\Security;

use App\Models\Server;
use App\Services\SecurityDetectionSettings;
use App\Services\SecurityDetectorEngine;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CollectServerSecuritySignalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(public readonly string $serverId)
    {
        // SSH collection is an operations-class job (same as other remote scripts).
        // Horizon's monitoring supervisor defaults to a 60s timeout, which is too short.
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh, SecurityDetectorEngine $detector, SecurityDetectionSettings $settings): void
    {
        $server = Server::with(['sshKey', 'sites:id,server_id,domain'])->findOrFail($this->serverId);

        if (! config('security-detection.enabled') || ! $settings->enabledForServer($server)) {
            $server->markSecurityScan('idle');

            return;
        }

        $server->markSecurityScan('running');

        try {
            // Cap below the default 1800s SSH timeout so a hung host cannot pin a Windows
            // queue:work process (no pcntl) and leave later scans stuck as "queued".
            $output = $ssh->runScript($server, resource_path('scripts/collect-security-signals.sh'), [
                'WINDOW_MINUTES' => (string) $settings->maxLookbackForServer($server),
            ], timeoutSeconds: 150);

            $events = collect(preg_split('/\R/', trim($output)))
                ->filter()
                ->map(function (string $line) use ($server): ?array {
                    $event = json_decode($line, true);
                    if (! is_array($event)) {
                        Log::warning('Security collector returned an invalid record.', ['server_id' => $server->id]);

                        return null;
                    }

                    return $event;
                })
                ->filter()
                ->take(1000)
                ->values()
                ->all();

            $detector->evaluate($server, $events);
            $server->markSecurityScan('succeeded', null, touchScannedAt: true);
        } catch (Throwable $e) {
            $server->markSecurityScan('failed', $this->safeMessage($e->getMessage()));

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $server = Server::find($this->serverId);
        if (! $server) {
            return;
        }

        if ($server->security_scan_status === 'failed' && filled($server->security_scan_message)) {
            return;
        }

        $server->markSecurityScan('failed', $this->safeMessage($e?->getMessage() ?? 'Security scan failed.'));
    }

    private function safeMessage(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        $message = preg_replace('/-----BEGIN [^-]+-----.*?-----END [^-]+-----/s', '[REDACTED]', $message) ?? $message;
        $message = preg_replace('/(?i)(password|passwd|secret|token|key|authorization)\s*[:=]\s*\S+/', '$1=[REDACTED]', $message) ?? $message;
        $message = preg_replace('#/(?:home|root|Users)/[^\s/]+#', '/…', $message) ?? $message;

        return Str::limit($message !== '' ? $message : 'Security scan failed.', 180);
    }
}
