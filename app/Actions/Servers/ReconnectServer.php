<?php

namespace App\Actions\Servers;

use App\Enums\ServerStatus;
use App\Jobs\Servers\ReconnectServerJob;
use App\Models\Server;
use InvalidArgumentException;

final class ReconnectServer
{
    /**
     * @param  array<string, mixed>  $updates
     */
    public function execute(Server $server, array $updates = [], bool $reinstallStack = false): Server
    {
        if (! $server->trashed()) {
            throw new InvalidArgumentException('Only removed servers can be reconnected.');
        }

        $server->restore();

        $server->update([
            ...$updates,
            'status' => ServerStatus::Pending,
            'progress' => 5,
            'current_step' => 'Waiting to reconnect',
            'failure_reason' => null,
        ]);

        ReconnectServerJob::dispatch($server->id, $reinstallStack)->onQueue('provisioning');

        return $server->fresh();
    }
}
