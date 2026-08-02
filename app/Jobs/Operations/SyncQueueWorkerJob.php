<?php

namespace App\Jobs\Operations;

use App\Models\QueueWorker;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncQueueWorkerJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public readonly string $workerId) {}

    public function handle(SshClient $ssh): void
    {
        $worker = QueueWorker::with('site.server.sshKey')->withTrashed()->findOrFail($this->workerId);
        $ssh->runScript($worker->site->server, resource_path('scripts/sync-worker.sh'), ['ID' => $worker->id, 'ENABLED' => $worker->trashed() ? 'no' : 'yes', 'DOMAIN' => $worker->site->domain, 'NAME' => $worker->name, 'TYPE' => $worker->type, 'CONNECTION' => $worker->connection, 'QUEUE' => $worker->queue, 'PROCESSES' => $worker->processes, 'TRIES' => $worker->tries, 'TIMEOUT' => $worker->timeout, 'MEMORY' => $worker->memory, 'PORT' => (string) ($worker->port ?? '')]);
        if (! $worker->trashed()) {
            $worker->update(['status' => 'active']);
        }
    }

    public function failed(Throwable $e): void
    {
        QueueWorker::withTrashed()->find($this->workerId)?->update(['status' => 'failed']);
    }
}
