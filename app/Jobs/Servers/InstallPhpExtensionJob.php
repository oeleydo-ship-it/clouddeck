<?php

namespace App\Jobs\Servers;

use App\Models\ServerOperation;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class InstallPhpExtensionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly string $operationId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $operation = ServerOperation::with('server.sshKey')->findOrFail($this->operationId);
        $operation->update(['status' => 'running', 'started_at' => now()]);
        $extension = Str::after($operation->type, 'php:extension:');
        $output = $ssh->runScript($operation->server, resource_path('scripts/install-php-extension.sh'), ['EXTENSION' => $extension]);
        $operation->update(['status' => 'successful', 'output' => $output, 'exit_code' => 0, 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        ServerOperation::find($this->operationId)?->update(['status' => 'failed', 'output' => $exception->getMessage(), 'exit_code' => 1, 'finished_at' => now()]);
    }
}
