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

class RestoreServerSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $snapshotId) {}

    public function handle(CloudProviderManager $providers): void
    {
        $snapshot = ServerSnapshot::with('server.cloudAccount')->findOrFail($this->snapshotId);
        $server = $snapshot->server;
        $server->update(['status' => 'provisioning', 'progress' => 10, 'current_step' => 'Restoring provider snapshot']);
        $action = $providers->for($server->cloudAccount)->action($server->provider_id, 'restore', ['image' => $snapshot->provider_snapshot_id]);
        $server->update(['progress' => 25, 'current_step' => 'Provider restore accepted', 'metadata' => [...($server->metadata ?? []), 'restore_action_id' => (string) $action['id'], 'restore_snapshot_id' => $snapshot->id]]);
        RefreshServerRestoreJob::dispatch($server->id, (string) $action['id'])->delay(now()->addSeconds(30))->onQueue('operations');
    }

    public function failed(Throwable $e): void
    {
        $snapshot = ServerSnapshot::with('server')->find($this->snapshotId);
        $snapshot?->server?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
