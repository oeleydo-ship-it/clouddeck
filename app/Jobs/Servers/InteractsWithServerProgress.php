<?php

namespace App\Jobs\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use Throwable;

trait InteractsWithServerProgress
{
    private function progress(Server $server, int $progress, string $step, ?ServerStatus $status = null): void
    {
        $server->update(['progress' => $progress, 'current_step' => $step, ...($status ? ['status' => $status] : [])]);
    }

    public function failed(Throwable $e): void
    {
        Server::find($this->serverId)?->update(['status' => ServerStatus::Failed, 'failure_reason' => $e->getMessage(), 'current_step' => 'Failed']);
    }
}
