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
use RuntimeException;

class ConnectCustomServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, InteractsWithServerProgress, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $serverId) {}

    public function handle(SshClient $ssh): void
    {
        $server = Server::with('sshKey')->findOrFail($this->serverId);
        $this->progress($server, 10, 'Verifying the SSH connection', ServerStatus::Provisioning);

        // Checked before anything is installed, so an unreachable box or an unauthorised
        // key fails in seconds with something the operator can act on, rather than part
        // way through a bootstrap that half-configured their machine.
        $identity = $ssh->run($server, 'id -u; . /etc/os-release 2>/dev/null && echo "${ID}:${VERSION_ID}"');
        [$uid, $release] = array_pad(preg_split('/\R/', trim($identity)), 2, '');

        if (trim($uid) !== '0') {
            throw new RuntimeException('Connected, but not as root. Uplary needs the key authorised on the root account to install and configure services.');
        }

        if (! str_starts_with($release, 'ubuntu:')) {
            throw new RuntimeException('This server is not running Ubuntu ('.($release ?: 'unknown').'). Uplary provisions Ubuntu 22.04 and 24.04.');
        }

        $this->progress($server, 25, 'Connected to Ubuntu '.explode(':', $release)[1]);

        $ssh->runScript($server, resource_path('scripts/bootstrap-ubuntu.sh'), ['PHP_VERSION' => '8.4']);
        $this->progress($server, 90, 'Verifying services');

        FinalizeProvisioningJob::dispatch($server->id)->onQueue('provisioning');
    }
}
