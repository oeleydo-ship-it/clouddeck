<?php

namespace App\Jobs\Backups;

use App\Cloud\CloudProviderManager;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class RefreshServerRestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public function __construct(public readonly string $serverId, public readonly string $actionId) {}

    public function handle(CloudProviderManager $providers): void
    {
        $server = Server::with('cloudAccount')->findOrFail($this->serverId);
        $action = $providers->forServer($server)->actionStatus($server->provider_id, $this->actionId);
        if (($action['status'] ?? null) === 'in-progress') {
            $this->release(30);

            return;
        }
        if (($action['status'] ?? null) !== 'completed') {
            throw new RuntimeException('Provider server restore failed.');
        }
        $metadata = $server->metadata ?? [];
        unset($metadata['restore_action_id']);
        $server->update(['status' => 'ready', 'progress' => 100, 'current_step' => 'Snapshot restore completed', 'failure_reason' => null, 'metadata' => $metadata, 'provisioned_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        Server::find($this->serverId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage(), 'current_step' => 'Snapshot restore failed']);
    }
}
