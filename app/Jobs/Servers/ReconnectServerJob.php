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

class ReconnectServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, InteractsWithServerProgress, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $serverId, public readonly bool $reinstallStack = false) {}

    public function handle(SshClient $ssh): void
    {
        $server = Server::with('sshKey')->findOrFail($this->serverId);
        $this->progress($server, 10, 'Verifying the SSH connection', ServerStatus::Provisioning);

        $identity = $ssh->run($server, 'id -u; . /etc/os-release 2>/dev/null && echo "${ID}:${VERSION_ID}"');
        [$uid, $release] = array_pad(preg_split('/\R/', trim($identity)), 2, '');

        if (trim($uid) !== '0') {
            throw new RuntimeException('Connected, but not as root. Uplary needs the key authorised on the root account to manage this server.');
        }

        if (! str_starts_with($release, 'ubuntu:')) {
            throw new RuntimeException('This server is not running Ubuntu ('.($release ?: 'unknown').'). Uplary manages Ubuntu 22.04, 24.04 and 26.04.');
        }

        $this->progress($server, 25, 'Connected to Ubuntu '.explode(':', $release)[1]);

        // A record can be marked provisioned from a previous life of this IP, but the
        // box itself may have been reimaged since — the operator knows that, we do not, so
        // let them force the full bootstrap instead of trusting a stale provisioned_at.
        if ($server->provisioned_at && ! $this->reinstallStack) {
            $this->progress($server, 80, 'Existing stack detected — skipping bootstrap');
            FinalizeProvisioningJob::dispatch($server->id)->onQueue('provisioning');

            return;
        }

        $this->progress($server, 40, 'Bootstrapping Ubuntu', ServerStatus::Provisioning);
        $ssh->runScript($server, resource_path('scripts/bootstrap-ubuntu.sh'), [
            'PHP_VERSION' => config('uplary.default_php_version'),
            'PHP_VERSIONS' => implode(' ', config('uplary.php_versions')),
        ]);
        $this->progress($server, 90, 'Verifying services');

        FinalizeProvisioningJob::dispatch($server->id)->onQueue('provisioning');
    }
}
