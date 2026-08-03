<?php

namespace App\Jobs\Servers;

use App\Enums\ServerStatus;
use App\Jobs\Monitoring\ManageMonitoringAgentJob;
use App\Models\Server;
use App\Notifications\OperationalEventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class FinalizeProvisioningJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,InteractsWithServerProgress,Queueable,SerializesModels;

    public function __construct(public readonly string $serverId) {}

    public function handle(): void
    {
        $server = Server::findOrFail($this->serverId);
        $server->update(['status' => ServerStatus::Ready, 'progress' => 100, 'current_step' => 'Ready', 'failure_reason' => null, 'provisioned_at' => now()]);

        $monitoring = $this->enableMonitoring($server);

        // Provisioning takes long enough that nobody sits watching it finish.
        $server->user?->notify(new OperationalEventNotification(
            event: 'server_provisioned',
            title: $server->name.' is ready',
            body: 'Provisioning finished at '.$server->public_ip.'. Nginx, PHP, MySQL, Redis, and Supervisor are installed and the firewall is up.'
                .($monitoring ? ' The metric agent is installed and reporting every minute.' : ''),
            url: route('servers.manage', $server),
            context: ['server_id' => $server->id],
        ));
    }

    /**
     * A server nobody is watching is the one that surprises you, so monitoring is on by
     * arrival rather than after somebody remembers to click Enable. The install itself is
     * the same path the button uses: it writes the agent, its config, and the every-minute
     * cron entry over SSH.
     *
     * Left alone if monitoring is already configured, so re-running the tail of a
     * provision does not rotate a secret the agent on the box is already using.
     */
    private function enableMonitoring(Server $server): bool
    {
        if ($server->monitoring_enabled && $server->monitoring_secret) {
            return false;
        }

        $server->update(['monitoring_secret' => Str::random(64), 'monitoring_enabled' => true]);

        $operation = $server->operations()->create([
            'user_id' => $server->user_id,
            'type' => 'monitoring:install',
            'status' => 'pending',
        ]);

        ManageMonitoringAgentJob::dispatch($operation->id);

        return true;
    }
}
