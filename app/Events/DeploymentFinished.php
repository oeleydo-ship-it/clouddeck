<?php

namespace App\Events;

use App\Models\Deployment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wakes the deployment show page when a run settles so the queued flash and cancel
 * button do not outlive a successful (or failed) deploy.
 */
class DeploymentFinished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Deployment $deployment) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('deployments.'.$this->deployment->id);
    }

    public function broadcastAs(): string
    {
        return 'deployment-finished';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->deployment->status->value,
            'progress' => $this->deployment->progress,
            'exit_code' => $this->deployment->exit_code,
            'release' => $this->deployment->release,
        ];
    }
}
