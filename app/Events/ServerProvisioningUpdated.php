<?php

namespace App\Events;

use App\Models\Server;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on every provisioning step so the servers list reflects a bootstrap in real
 * time. ShouldBroadcastNow rather than ShouldBroadcast: provisioning already runs inside a
 * queued job, and queueing the broadcast behind it would deliver each step only once the
 * work that produced it had finished — which is the opposite of a progress indicator.
 */
class ServerProvisioningUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Server $server) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('servers.'.$this->server->id);
    }

    public function broadcastAs(): string
    {
        return 'provisioning-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->server->status->value,
            'progress' => $this->server->progress,
            'current_step' => $this->server->current_step,
            'failure_reason' => $this->server->failure_reason,
        ];
    }
}
