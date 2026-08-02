<?php

namespace App\Jobs\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeProvisioningJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,InteractsWithServerProgress,Queueable,SerializesModels;

    public function __construct(public readonly string $serverId) {}

    public function handle(): void
    {
        $server = Server::findOrFail($this->serverId);
        $server->update(['status' => ServerStatus::Ready, 'progress' => 100, 'current_step' => 'Ready', 'failure_reason' => null, 'provisioned_at' => now()]);
    }
}
