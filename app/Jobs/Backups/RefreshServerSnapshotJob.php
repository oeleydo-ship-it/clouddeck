<?php

namespace App\Jobs\Backups;

use App\Cloud\CloudProviderManager;
use App\Models\ServerSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class RefreshServerSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 45;

    public function __construct(public readonly string $snapshotId) {}

    public function handle(CloudProviderManager $providers): void
    {
        $snapshot = ServerSnapshot::with('server.cloudAccount')->findOrFail($this->snapshotId);
        if ($snapshot->status !== 'processing') {
            return;
        }
        $provider = $providers->for($snapshot->server->cloudAccount);
        $action = $provider->actionStatus($snapshot->server->provider_id, $snapshot->provider_action_id);
        $snapshot->update(['last_checked_at' => now()]);
        if (($action['status'] ?? null) === 'in-progress') {
            $this->release(30);

            return;
        }
        if (($action['status'] ?? null) !== 'completed') {
            throw new RuntimeException('Provider snapshot action failed.');
        }
        $remote = collect($provider->snapshots($snapshot->server->provider_id))->firstWhere('name', $snapshot->name);
        if (! $remote) {
            $this->release(15);

            return;
        }
        $snapshot->update(['status' => 'ready', 'provider_snapshot_id' => (string) $remote['id'], 'size_gigabytes' => $remote['size_gigabytes'] ?? null, 'provider_created_at' => $remote['created_at'] ?? now(), 'completed_at' => now()]);
        if ($snapshot->backup_policy_id) {
            PruneBackupRetentionJob::dispatch($snapshot->backup_policy_id)->onQueue('operations');
        }
    }

    public function failed(Throwable $e): void
    {
        ServerSnapshot::find($this->snapshotId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
