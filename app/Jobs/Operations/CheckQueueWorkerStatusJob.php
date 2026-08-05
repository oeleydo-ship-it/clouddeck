<?php

namespace App\Jobs\Operations;

use App\Models\QueueWorker;
use App\Services\SupervisorProgram;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CheckQueueWorkerStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public readonly string $workerId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $worker = QueueWorker::withTrashed()->with('site.server.sshKey')->findOrFail($this->workerId);
        $output = $ssh->run($worker->site->server, SupervisorProgram::statusCommand($worker->id));
        $worker->update([
            'runtime_status' => SupervisorProgram::parseStatus($output, $worker->id),
            'runtime_output' => trim($output),
            'runtime_checked_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        QueueWorker::withTrashed()->find($this->workerId)?->update(['runtime_status' => 'unknown', 'runtime_output' => $exception->getMessage(), 'runtime_checked_at' => now()]);
    }
}
