<?php

namespace App\Jobs\Servers;

use App\Enums\ServerStatus;
use App\Events\ServerProvisioningUpdated;
use App\Models\Server;
use Throwable;

trait InteractsWithServerProgress
{
    private function progress(Server $server, int $progress, string $step, ?ServerStatus $status = null): void
    {
        $server->update(['progress' => $progress, 'current_step' => $step, ...($status ? ['status' => $status] : [])]);
        $this->announce($server);
    }

    public function failed(Throwable $e): void
    {
        $server = Server::find($this->serverId);
        $server?->update(['status' => ServerStatus::Failed, 'failure_reason' => $e->getMessage(), 'current_step' => 'Failed']);

        if ($server) {
            $this->announce($server->refresh());
        }
    }

    /**
     * Best effort, like the deployment log stream. Provisioning must not be abandoned part
     * way because the WebSocket server happens to be down: that would leave a droplet that
     * exists at the provider but is unfinished and unusable here.
     */
    private function announce(Server $server): void
    {
        try {
            ServerProvisioningUpdated::dispatch($server);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
