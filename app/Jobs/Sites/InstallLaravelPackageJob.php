<?php

namespace App\Jobs\Sites;

use App\Models\TerminalCommand;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class InstallLaravelPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly string $commandId, public readonly string $package, public readonly string $installCommand)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $command = TerminalCommand::with('site.server.sshKey')->findOrFail($this->commandId);
        $command->update(['status' => 'running', 'started_at' => now()]);
        $output = $ssh->runScript($command->site->server, resource_path('scripts/install-laravel-package.sh'), [
            'DOMAIN' => $command->site->domain,
            'PACKAGE' => $this->package,
            'INSTALL_COMMAND' => $this->installCommand,
        ]);
        $command->update(['status' => 'successful', 'output' => $output, 'exit_code' => 0, 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        TerminalCommand::find($this->commandId)?->update(['status' => 'failed', 'output' => $exception->getMessage(), 'exit_code' => 1, 'finished_at' => now()]);
    }
}
