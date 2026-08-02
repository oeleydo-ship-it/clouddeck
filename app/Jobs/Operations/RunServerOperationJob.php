<?php

namespace App\Jobs\Operations;

use App\Models\ServerOperation;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunServerOperationJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public readonly string $operationId) {}

    public function handle(SshClient $ssh): void
    {
        $operation = ServerOperation::with('server.sshKey')->findOrFail($this->operationId);
        $operation->update(['status' => 'running', 'started_at' => now()]);
        $commands = ['nginx:test' => 'nginx -t', 'nginx:reload' => 'systemctl reload nginx', 'nginx:restart' => 'systemctl restart nginx', 'php:reload' => 'systemctl reload php8.4-fpm', 'php:restart' => 'systemctl restart php8.4-fpm', 'supervisor:restart' => 'supervisorctl restart all', 'redis:restart' => 'systemctl restart redis-server', 'mysql:restart' => 'systemctl restart mysql'];
        $command = $commands[$operation->type] ?? throw new \RuntimeException('Unsupported operation.');
        $output = $ssh->run($operation->server, $command.' 2>&1');
        $operation->update(['status' => 'successful', 'output' => $output, 'exit_code' => 0, 'finished_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        ServerOperation::find($this->operationId)?->update(['status' => 'failed', 'output' => $e->getMessage(), 'exit_code' => 1, 'finished_at' => now()]);
    }
}
