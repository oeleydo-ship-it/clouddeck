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
        $output = $ssh->run($worker->site->server, 'supervisorctl status '.escapeshellarg('clouddeck-'.$worker->id.':*').' 2>&1 || true');
        preg_match('/\b(RUNNING|STARTING|BACKOFF|STOPPING|STOPPED|EXITED|FATAL)\b/', $output, $match);
        $worker->update(['runtime_status' => $match[1] ?? 'unknown', 'runtime_output' => trim($output), 'runtime_checked_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        QueueWorker::withTrashed()->find($this->workerId)?->update(['runtime_status' => 'unknown', 'runtime_output' => $exception->getMessage(), 'runtime_checked_at' => now()]);
    }
}
