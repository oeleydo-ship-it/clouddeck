<?php

namespace App\Jobs\Monitoring;

use App\Models\ServerOperation;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ManageMonitoringAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $operationId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $operation = ServerOperation::with('server.sshKey')->findOrFail($this->operationId);
        $operation->update(['status' => 'running', 'started_at' => now()]);

        if ($operation->type === 'monitoring:remove') {
            $output = $ssh->run($operation->server, 'rm -f /etc/cron.d/clouddeck-monitor /etc/clouddeck-monitor.conf /usr/local/bin/clouddeck-monitor');
        } else {
            $server = $operation->server;
            $output = $ssh->runScript($server, resource_path('scripts/install-monitoring-agent.sh'), [
                'AGENT_BASE64' => base64_encode((string) file_get_contents(resource_path('scripts/clouddeck-monitor.sh'))),
                'APP_URL' => rtrim(config('app.url'), '/'),
                'SERVER_ID' => $server->id,
                'SECRET' => $server->monitoring_secret,
            ]);
        }

        $operation->update(['status' => 'successful', 'output' => $output, 'exit_code' => 0, 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        ServerOperation::find($this->operationId)?->update(['status' => 'failed', 'output' => $exception->getMessage(), 'exit_code' => 1, 'finished_at' => now()]);
    }
}
