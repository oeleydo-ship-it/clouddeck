<?php

namespace App\Jobs\Backups;

use App\Cloud\CloudProviderManager;
use App\Models\ServerSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeleteServerSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $snapshotId) {}

    public function handle(CloudProviderManager $providers): void
    {
        $snapshot = ServerSnapshot::with('server.cloudAccount')->findOrFail($this->snapshotId);
        $snapshot->update(['status' => 'deleting']);
        if ($snapshot->provider_snapshot_id) {
            $providers->for($snapshot->server->cloudAccount)->deleteSnapshot($snapshot->provider_snapshot_id);
        }
        $snapshot->delete();
    }

    public function failed(Throwable $e): void
    {
        ServerSnapshot::find($this->snapshotId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
