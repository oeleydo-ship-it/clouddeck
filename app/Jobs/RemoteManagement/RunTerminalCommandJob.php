<?php

namespace App\Jobs\RemoteManagement;

use App\Models\TerminalCommand;
use App\Services\SafeTerminalCommand;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunTerminalCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 360;

    public function __construct(public readonly string $commandId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh, SafeTerminalCommand $safeCommand): void
    {
        $command = TerminalCommand::with('site.server.sshKey')->findOrFail($this->commandId);
        $command->update(['status' => 'running', 'started_at' => now(), 'output' => '']);
        $compiled = $safeCommand->compile($command->command);
        $root = '/var/www/'.$command->site->domain.'/current';
        $result = $ssh->runStreaming($command->site->server, 'cd '.escapeshellarg($root).' && timeout 300 sudo -u www-data -- '.$compiled.' 2>&1', function (string $type, string $chunk) use ($command): void {
            $current = $command->fresh()->output ?? '';
            $command->update(['output' => substr($current.$chunk, -1000000)]);
        });
        $command->refresh();
        $command->update(['status' => $result->successful() ? 'successful' : 'failed', 'output' => $command->output ?: ($result->output().$result->errorOutput()), 'exit_code' => $result->exitCode(), 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        TerminalCommand::find($this->commandId)?->update(['status' => 'failed', 'output' => $exception->getMessage(), 'exit_code' => 1, 'finished_at' => now()]);
    }
}
