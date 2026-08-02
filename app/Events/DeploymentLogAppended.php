<?php

namespace App\Events;

use App\Models\Deployment;
use App\Models\DeploymentLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentLogAppended implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Deployment $deployment, public readonly DeploymentLog $log) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('deployments.'.$this->deployment->id);
    }

    public function broadcastAs(): string
    {
        return 'log-appended';
    }

    public function broadcastWith(): array
    {
        return [
            'log' => [
                'level' => $this->log->level,
                'output' => $this->log->output,
                'created_at' => $this->log->created_at->format('H:i:s'),
            ],
            'status' => $this->deployment->status->value,
            'progress' => $this->deployment->progress,
            'exit_code' => $this->deployment->exit_code,
            'duration_for_humans' => $this->deployment->duration_for_humans,
        ];
    }
}
