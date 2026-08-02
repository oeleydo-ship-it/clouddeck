<?php

namespace App\Jobs\Servers;

use App\Models\ServerOperation;
use App\Services\ServerPortRegistry;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ManagePhpMyAdminJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly string $operationId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $operation = ServerOperation::with('server.sshKey')->findOrFail($this->operationId);
        $operation->update(['status' => 'running', 'started_at' => now()]);
        $server = $operation->server;
        $port = $server->phpmyadmin_port ?: ServerPortRegistry::PHPMYADMIN_DEFAULT;

        if ($operation->type === 'phpmyadmin:remove') {
            $output = $ssh->runScript($server, resource_path('scripts/remove-phpmyadmin.sh'), ['PORT' => (string) $port]);
            $server->update(['phpmyadmin_enabled' => false]);
        } else {
            $output = $ssh->runScript($server, resource_path('scripts/install-phpmyadmin.sh'), ['PORT' => (string) $port]);
            $server->update(['phpmyadmin_enabled' => true, 'phpmyadmin_port' => $port]);
        }

        $operation->update(['status' => 'successful', 'output' => $output, 'exit_code' => 0, 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        ServerOperation::find($this->operationId)?->update(['status' => 'failed', 'output' => $exception->getMessage(), 'exit_code' => 1, 'finished_at' => now()]);
    }
}
