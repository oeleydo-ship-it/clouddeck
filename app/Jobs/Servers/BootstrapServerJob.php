<?php

namespace App\Jobs\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BootstrapServerJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,InteractsWithServerProgress,Queueable,SerializesModels;

    public int $timeout = 1800;

    public function __construct(public readonly string $serverId) {}

    public function handle(SshClient $ssh): void
    {
        $server = Server::with('sshKey')->findOrFail($this->serverId);
        $this->progress($server, 40, 'Bootstrapping Ubuntu', ServerStatus::Provisioning);
        $ssh->runScript($server, resource_path('scripts/bootstrap-ubuntu.sh'), ['PHP_VERSION' => '8.4']);
        $this->progress($server, 90, 'Verifying services');
    }
}
