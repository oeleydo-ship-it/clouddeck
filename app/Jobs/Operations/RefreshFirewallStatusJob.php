<?php

namespace App\Jobs\Operations;

use App\Models\Server;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class RefreshFirewallStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $serverId) {}

    public function handle(SshClient $ssh): void
    {
        $server = Server::with('sshKey')->findOrFail($this->serverId);
        $output = $ssh->runScript($server, resource_path('scripts/refresh-firewall.sh'));

        if (str_contains($output, 'UFW_NOT_INSTALLED')) {
            $server->update([
                'firewall_status' => 'missing_ufw',
                'firewall_message' => 'UFW is not installed on this server. Install and enable UFW before managing firewall rules.',
                'firewall_remote_status' => null,
            ]);

            return;
        }

        $server->update([
            'firewall_status' => 'ok',
            'firewall_message' => null,
            'firewall_remote_status' => Str::limit(trim($output), 8000),
            'firewall_synced_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        $server = Server::find($this->serverId);
        if (! $server || $server->firewall_status === 'missing_ufw') {
            return;
        }

        $server->update([
            'firewall_status' => 'error',
            'firewall_message' => Str::limit(trim($e->getMessage()), 500),
        ]);
    }
}
