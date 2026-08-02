<?php

namespace App\Events;

use App\Models\Site;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A newly created site sits at "configuring" while the remote job writes its Nginx server
 * block and PHP-FPM pool, then flips to active or failed. Broadcasting the change means the
 * page the operator is already looking at tells them, instead of them reloading to find out.
 *
 * Now rather than queued: the status change happens inside a queued job, so queueing the
 * broadcast behind it would announce the news only after the work that produced it.
 */
class SiteStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Site $site) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('sites.'.$this->site->id);
    }

    public function broadcastAs(): string
    {
        return 'status-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->site->status,
            'last_deployed_at' => $this->site->last_deployed_at?->diffForHumans(),
        ];
    }
}
