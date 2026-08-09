<?php

namespace App\Jobs\Sites;

use App\Models\Site;
use App\Services\PlatformStagingDns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncPlatformStagingDnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(public readonly string $siteId) {}

    public function handle(PlatformStagingDns $dns): void
    {
        $site = Site::withTrashed()->with('server')->find($this->siteId);
        if (! $site || $site->trashed()) {
            return;
        }

        $dns->sync($site);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
