<?php

namespace App\Jobs\Servers;

use App\Cloud\CloudProviderManager;
use App\Cloud\Data\CreateServerData;
use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateDropletJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,InteractsWithServerProgress,Queueable,SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $serverId) {}

    public function handle(CloudProviderManager $manager): void
    {
        $server = Server::with(['cloudAccount', 'sshKey'])->findOrFail($this->serverId);
        $this->progress($server, 10, 'Creating cloud server', ServerStatus::Creating);
        $provider = $manager->for($server->cloudAccount);
        $providerKeyId = $server->sshKey ? $provider->ensureSshKey($server->sshKey->name, $server->sshKey->public_key, $server->sshKey->fingerprint) : null;
        $droplet = $provider->createServer(new CreateServerData($server->hostname, $server->region, $server->size, $server->image, array_filter([$providerKeyId])));
        $server->update(['provider_id' => (string) $droplet['id'], 'metadata' => $droplet]);
    }
}
