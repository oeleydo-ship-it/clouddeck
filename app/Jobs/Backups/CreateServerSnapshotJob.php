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

class CreateServerSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $snapshotId) {}

    public function handle(CloudProviderManager $providers): void
    {
        $snapshot = ServerSnapshot::with('server.cloudAccount')->findOrFail($this->snapshotId);
        $snapshot->update(['status' => 'creating']);
        $action = $providers->for($snapshot->server->cloudAccount)->action($snapshot->server->provider_id, 'snapshot', ['name' => $snapshot->name]);
        $snapshot->update(['status' => 'processing', 'provider_action_id' => (string) $action['id'], 'last_checked_at' => now()]);
        RefreshServerSnapshotJob::dispatch($snapshot->id)->delay(now()->addSeconds(30))->onQueue('operations');
    }

    public function failed(Throwable $e): void
    {
        ServerSnapshot::find($this->snapshotId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
