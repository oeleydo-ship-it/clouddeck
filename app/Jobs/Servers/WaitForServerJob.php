<?php

namespace App\Jobs\Servers;

use App\Cloud\CloudProviderManager;
use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class WaitForServerJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,InteractsWithServerProgress,Queueable,SerializesModels;

    public int $tries = 20;

    public function __construct(public readonly string $serverId) {}

    public function handle(CloudProviderManager $manager): void
    {
        $server = Server::with('cloudAccount')->findOrFail($this->serverId);
        $this->progress($server, 25, 'Waiting for public IP');
        $droplet = $manager->for($server->cloudAccount)->server($server->provider_id);
        if (($droplet['status'] ?? null) !== 'active') {
            $this->release(10);

            return;
        } $ip = collect($droplet['networks']['v4'] ?? [])->firstWhere('type', 'public')['ip_address'] ?? null;
        if (! $ip) {
            throw new RuntimeException('DigitalOcean did not return a public IPv4 address.');
        } $server->update(['public_ip' => $ip, 'status' => ServerStatus::Active, 'metadata' => $droplet]);
    }
}
