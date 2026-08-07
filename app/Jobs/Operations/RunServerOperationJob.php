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

    public int $timeout = 1800;

    public function __construct(public readonly string $operationId) {}

    public function handle(SshClient $ssh): void
    {
        $operation = ServerOperation::with('server.sshKey')->findOrFail($this->operationId);
        $operation->update(['status' => 'running', 'started_at' => now()]);

        $scripts = [
            'system:harden' => 'harden-ubuntu.sh',
            'system:update' => 'update-ubuntu.sh',
            'system:release-upgrade' => 'upgrade-ubuntu-release.sh',
        ];

        if (isset($scripts[$operation->type])) {
            $output = $ssh->runScript($operation->server, resource_path('scripts/'.$scripts[$operation->type]));
        } else {
            $commands = [
                'nginx:test' => 'nginx -t',
                'nginx:reload' => 'systemctl reload nginx',
                'nginx:restart' => 'systemctl restart nginx',
                'php:reload' => 'bash -lc \'for v in 8.5 8.4 8.3 8.2; do systemctl is-active --quiet "php${v}-fpm" && systemctl reload "php${v}-fpm"; done\'',
                'php:restart' => 'bash -lc \'for v in 8.5 8.4 8.3 8.2; do systemctl is-active --quiet "php${v}-fpm" && systemctl restart "php${v}-fpm"; done\'',
                'supervisor:restart' => 'supervisorctl restart all',
                'redis:restart' => 'systemctl restart redis-server',
                'mysql:restart' => 'systemctl restart mysql',
            ];
            $command = $commands[$operation->type] ?? throw new \RuntimeException('Unsupported operation.');
            $output = $ssh->run($operation->server, $command.' 2>&1');
        }

        $operation->update(['status' => 'successful', 'output' => $output, 'exit_code' => 0, 'finished_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        ServerOperation::find($this->operationId)?->update(['status' => 'failed', 'output' => $e->getMessage(), 'exit_code' => 1, 'finished_at' => now()]);
    }
}
