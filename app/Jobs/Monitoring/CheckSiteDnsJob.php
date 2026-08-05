<?php

namespace App\Jobs\Monitoring;

use App\Enums\ServerStatus;
use App\Models\Site;
use App\Models\SiteMonitorIncident;
use App\Notifications\OperationalEventNotification;
use App\Services\SiteDnsResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CheckSiteDnsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 55;

    public function __construct(public readonly string $siteId)
    {
        $this->onQueue('monitoring');
    }

    public function uniqueId(): string
    {
        return 'site-dns:'.$this->siteId;
    }

    public function handle(SiteDnsResolver $dns): void
    {
        $site = Site::with(['server', 'user'])->find($this->siteId);
        if (! $site || ! $site->site_monitoring_enabled || $site->status !== 'active') {
            return;
        }

        if ($site->server?->status !== ServerStatus::Ready || $site->isDeploying()) {
            return;
        }

        $expected = $site->server->public_ip;
        if (blank($expected)) {
            $site->update([
                'dns_last_status' => 'unknown',
                'dns_last_checked_at' => now(),
                'dns_last_error' => 'Server has no public IP to compare.',
            ]);

            return;
        }

        $addresses = $dns->resolve($site->domain);
        $ok = in_array($expected, $addresses, true);

        $site->update([
            'dns_last_status' => $ok ? 'ok' : ($addresses === [] ? 'unknown' : 'mismatch'),
            'dns_last_checked_at' => now(),
            'dns_last_error' => $ok
                ? null
                : ($addresses === []
                    ? 'No A/AAAA records found for '.$site->domain
                    : 'Expected '.$expected.'; found '.Str::limit(implode(', ', $addresses), 120)),
        ]);

        if ($ok) {
            $this->resolveMismatch($site);

            return;
        }

        if ($addresses === []) {
            return;
        }

        $this->openMismatch($site, $expected, $addresses);
    }

    /**
     * @param  list<string>  $addresses
     */
    private function openMismatch(Site $site, string $expected, array $addresses): void
    {
        $message = $site->domain.' DNS does not point to '.$expected.' (found '.implode(', ', $addresses).')';
        $incident = SiteMonitorIncident::where('site_id', $site->id)
            ->where('type', 'dns_mismatch')
            ->where('status', 'open')
            ->first();

        if (! $incident) {
            $incident = SiteMonitorIncident::create([
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'type' => 'dns_mismatch',
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
            event: 'dns_mismatch',
            title: 'DNS mismatch for '.$site->domain,
            body: $message,
            url: route('sites.show', ['site' => $site, 'tab' => 'monitoring']),
            severity: 'warning',
            context: ['site_id' => $site->id, 'incident_id' => $incident->id],
        ));
    }

    private function resolveMismatch(Site $site): void
    {
        SiteMonitorIncident::where('site_id', $site->id)
            ->where('type', 'dns_mismatch')
            ->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_at' => now()]);
    }
}
