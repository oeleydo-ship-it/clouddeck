<?php

namespace App\Jobs\Monitoring;

use App\Enums\ServerStatus;
use App\Jobs\Sites\CheckSiteQueueHealthJob;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchSiteChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    public function handle(): void
    {
        $includeQueue = now()->minute % 15 === 0;

        Site::query()
            ->where('site_monitoring_enabled', true)
            ->where('status', 'active')
            ->with(['server', 'sslCertificates'])
            ->each(function (Site $site) use ($includeQueue): void {
                if ($site->server?->status !== ServerStatus::Ready) {
                    return;
                }

                if ($site->isDeploying()) {
                    return;
                }

                CheckSiteUptimeJob::dispatch($site->id)->onQueue('monitoring');
                CheckSiteDnsJob::dispatch($site->id)->onQueue('monitoring');

                if ($includeQueue && $site->isLaravel()) {
                    CheckSiteQueueHealthJob::dispatch($site->id)->onQueue('operations');
                }
            });
    }
}
