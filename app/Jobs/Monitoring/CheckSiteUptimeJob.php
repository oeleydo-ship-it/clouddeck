<?php

namespace App\Jobs\Monitoring;

use App\Enums\ServerStatus;
use App\Models\Site;
use App\Models\SiteMonitorIncident;
use App\Notifications\OperationalEventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class CheckSiteUptimeJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 55;

    public function __construct(public readonly string $siteId)
    {
        $this->onQueue('monitoring');
    }

    public function uniqueId(): string
    {
        return 'site-uptime:'.$this->siteId;
    }

    public function handle(): void
    {
        $site = Site::with(['server', 'user', 'sslCertificates'])->find($this->siteId);
        if (! $site || ! $site->site_monitoring_enabled || $site->status !== 'active') {
            return;
        }

        if ($site->server?->status !== ServerStatus::Ready || $site->isDeploying()) {
            return;
        }

        $url = $site->monitorUrl();
        $timeout = (int) config('monitoring.site_probe_timeout', 10);
        $started = hrtime(true);
        $statusCode = null;
        $error = null;
        $up = false;

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->withHeaders(['User-Agent' => config('app.name', 'Uplary').' Site Monitor/1.0'])
                ->get($url);

            $statusCode = $response->status();
            $up = $statusCode >= 200 && $statusCode < 400;
            if (! $up) {
                $error = 'HTTP '.$statusCode;
            }
        } catch (ConnectionException $e) {
            $error = $this->shortError($e->getMessage());
        } catch (Throwable $e) {
            $error = $this->shortError($e->getMessage());
        }

        $latencyMs = (int) max(0, (hrtime(true) - $started) / 1_000_000);
        $consecutive = $up ? 0 : ((int) $site->monitor_consecutive_down + 1);
        $threshold = max(1, (int) $site->monitor_consecutive_failures);

        $site->update([
            'monitor_last_status' => $up ? 'up' : 'down',
            'monitor_last_checked_at' => now(),
            'monitor_last_latency_ms' => $latencyMs,
            'monitor_last_error' => $up ? null : ($error ?? 'Unreachable'),
            'monitor_consecutive_down' => $consecutive,
        ]);

        if ($up) {
            $this->resolveDown($site);

            return;
        }

        if ($consecutive < $threshold) {
            return;
        }

        $this->openDown($site, $error ?? 'Unreachable', $statusCode);
    }

    private function openDown(Site $site, string $error, ?int $statusCode): void
    {
        $message = $site->domain.' is down'.($statusCode ? ' (HTTP '.$statusCode.')' : '').': '.$error;
        $incident = SiteMonitorIncident::where('site_id', $site->id)
            ->where('type', 'site_down')
            ->where('status', 'open')
            ->first();

        if (! $incident) {
            $incident = SiteMonitorIncident::create([
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'type' => 'site_down',
                'status' => 'open',
                'message' => $message,
                'started_at' => now(),
            ]);
        } else {
            $incident->update(['message' => $message]);
        }

        $cooldown = max(1, (int) $site->monitor_cooldown_minutes);
        if ($incident->last_notified_at && $incident->last_notified_at->gt(now()->subMinutes($cooldown))) {
            return;
        }

        $incident->update(['last_notified_at' => now()]);
        $site->user?->notify(new OperationalEventNotification(
            event: 'site_down',
            title: $site->domain.' appears down',
            body: $message,
            url: route('sites.show', ['site' => $site, 'tab' => 'monitoring']),
            severity: 'critical',
            context: ['site_id' => $site->id, 'incident_id' => $incident->id],
        ));
    }

    private function resolveDown(Site $site): void
    {
        $incident = SiteMonitorIncident::where('site_id', $site->id)
            ->where('type', 'site_down')
            ->where('status', 'open')
            ->first();

        if (! $incident) {
            return;
        }

        $incident->update(['status' => 'resolved', 'resolved_at' => now()]);
        $site->user?->notify(new OperationalEventNotification(
            event: 'site_recovered',
            title: $site->domain.' is back up',
            body: 'The site responded successfully after being reported down.',
            url: route('sites.show', ['site' => $site, 'tab' => 'monitoring']),
            severity: 'info',
            context: ['site_id' => $site->id, 'incident_id' => $incident->id],
        ));
    }

    private function shortError(string $message): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 180);
    }
}
