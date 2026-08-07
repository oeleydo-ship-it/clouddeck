<?php

namespace App\Jobs\Security;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\SecurityDetectionSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchSecurityScansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SecurityDetectionSettings $settings): void
    {
        if (! config('security-detection.enabled')) {
            return;
        }

        Server::query()
            ->where('status', ServerStatus::Ready)
            ->whereNotNull('ssh_key_id')
            ->select(['id', 'user_id', 'team_id'])
            ->chunkById(100, function ($servers) use ($settings): void {
                foreach ($servers as $server) {
                    if (! $settings->enabledForServer($server)) {
                        continue;
                    }

                    $server->markSecurityScan('queued');
                    CollectServerSecuritySignalsJob::dispatch($server->id)->onQueue('monitoring');
                }
            });
    }
}
