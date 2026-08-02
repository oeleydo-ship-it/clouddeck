<?php

namespace App\Jobs\Deployments;

use App\Enums\DeploymentStatus;
use App\Events\DeploymentFinished;
use App\Events\DeploymentLogAppended;
use App\Models\Deployment;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RollbackDeploymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $deploymentId) {}

    public function handle(SshClient $ssh): void
    {
        $deployment = Deployment::with('site.server.sshKey')->findOrFail($this->deploymentId);
        $started = now();
        $deployment->update(['status' => DeploymentStatus::Running, 'started_at' => $started, 'progress' => 20]);
        $result = $ssh->runScriptStreaming($deployment->site->server, resource_path('scripts/rollback-release.sh'), ['DOMAIN' => $deployment->site->domain, 'RELEASE' => $deployment->release, 'PHP_VERSION' => $deployment->site->php_version], function (string $type, string $output) use ($deployment) {
            if (trim($output) === '') {
                return;
            }
            $log = $deployment->logs()->create(['level' => $type === 'err' ? 'error' : 'info', 'output' => $output, 'created_at' => now()]);
            DeploymentLogAppended::dispatch($deployment, $log);
        });
        $finished = now();
        if ($result->failed()) {
            $deployment->update(['status' => DeploymentStatus::Failed, 'finished_at' => $finished, 'exit_code' => $result->exitCode() ?? 1]);
            throw new \RuntimeException($result->errorOutput());
        }
        $deployment->update(['status' => DeploymentStatus::RolledBack, 'finished_at' => $finished, 'duration_ms' => $started->diffInMilliseconds($finished), 'exit_code' => 0, 'progress' => 100]);
        $deployment->site->update(['last_deployed_at' => $finished]);
        DeploymentFinished::dispatch($deployment->fresh(['site.user']));
    }

    public function failed(Throwable $exception): void
    {
        $deployment = Deployment::find($this->deploymentId);
        $deployment?->update(['status' => DeploymentStatus::Failed, 'finished_at' => now(), 'exit_code' => 1]);
        if ($deployment) {
            DeploymentFinished::dispatch($deployment->fresh(['site.user']));
        }
    }
}
