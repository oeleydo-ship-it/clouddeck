<?php

namespace App\Jobs\Deployments;

use App\Enums\DeploymentStatus;
use App\Events\DeploymentFinished;
use App\Events\DeploymentLogAppended;
use App\Jobs\Concerns\BroadcastsQuietly;
use App\Models\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class DeployReactJob implements ShouldQueue
{
    use BroadcastsQuietly, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $deploymentId) {}

    public function handle(\App\Ssh\SshClient $ssh): void
    {
        $deployment = Deployment::with(['site.server.sshKey', 'site.environmentVariables'])->findOrFail($this->deploymentId);

        if ($deployment->status === DeploymentStatus::Cancelled) {
            return;
        }

        $release = now()->format('YmdHis').'-'.Str::lower(Str::random(8));
        $started = now();
        $previous = $deployment->site->deployments()->where('status', DeploymentStatus::Successful)->whereNotNull('release')->latest('finished_at')->value('release');
        $deployment->update(['status' => DeploymentStatus::Running, 'started_at' => $started, 'progress' => 5, 'release' => $release, 'previous_release' => $previous]);
        $this->log($deployment, 'Starting release '.$release);

        $environment = $deployment->site->environmentVariables->map(fn ($variable) => $variable->key.'='.$this->quoteEnv($variable->value))->implode("\n")."\n";
        $ssh->runScript($deployment->site->server, resource_path('scripts/configure-site.sh'), [
            'DOMAIN' => $deployment->site->domain,
            'PHP_VERSION' => $deployment->site->php_version,
            'DOCUMENT_ROOT' => $deployment->site->documentRoot(),
            'PLATFORM' => 'react',
        ]);

        $result = $ssh->runScriptStreaming($deployment->site->server, resource_path('scripts/deploy-react.sh'), [
            'DOMAIN' => $deployment->site->domain,
            'REPOSITORY' => $deployment->site->repository_url,
            'BRANCH' => $deployment->site->branch,
            'RELEASE' => $release,
            'ENVIRONMENT_BASE64' => base64_encode($environment),
            'CUSTOM_SCRIPT_BASE64' => base64_encode($deployment->site->deployment_script ?? ''),
        ], function (string $type, string $output) use ($deployment) {
            if (trim($output) !== '') {
                if (preg_match('/\[(\d)\/6\]/', $output, $match)) {
                    $deployment->update(['progress' => min(95, 5 + ((int) $match[1] * 15))]);
                }
                $this->log($deployment, $output, $type === 'err' ? 'error' : 'info');
            }
        });

        if ($result->failed()) {
            $this->finishFailed($deployment, $started, $result->exitCode() ?? 1, $result->errorOutput());
            throw new \RuntimeException('Deployment command failed with exit code '.($result->exitCode() ?? 1).'.');
        }

        $finished = now();
        preg_match('/CLOUDDECK_COMMIT=([a-f0-9]{40})/', $result->output(), $commit);
        preg_match('/CLOUDDECK_MESSAGE_BASE64=([^\s]+)/', $result->output(), $message);
        $deployment->update(['status' => DeploymentStatus::Successful, 'finished_at' => $finished, 'duration_ms' => $started->diffInMilliseconds($finished), 'exit_code' => 0, 'progress' => 100, 'commit_hash' => $deployment->commit_hash ?: ($commit[1] ?? null), 'commit_message' => $deployment->commit_message ?: (isset($message[1]) ? base64_decode($message[1], true) : null)]);
        $deployment->site->update(['status' => 'active', 'last_deployed_at' => $finished]);
        $this->log($deployment, 'Deployment completed successfully.');
        $this->broadcastQuietly(fn () => DeploymentFinished::dispatch($deployment->fresh(['site.user'])));
    }

    public function failed(Throwable $exception): void
    {
        $deployment = Deployment::find($this->deploymentId);
        if ($deployment && ! in_array($deployment->status, [DeploymentStatus::Failed, DeploymentStatus::Cancelled], true)) {
            $this->finishFailed($deployment, $deployment->started_at ?? now(), 1, $exception->getMessage());
        }
        if ($deployment) {
            $this->broadcastQuietly(fn () => DeploymentFinished::dispatch($deployment->fresh(['site.user'])));
        }
    }

    private function finishFailed(Deployment $deployment, $started, int $exitCode, string $message): void
    {
        $finished = now();
        $deployment->update(['status' => DeploymentStatus::Failed, 'finished_at' => $finished, 'duration_ms' => $started->diffInMilliseconds($finished), 'exit_code' => $exitCode]);
        $this->log($deployment, $message, 'error');
    }

    private function log(Deployment $deployment, string $output, string $level = 'info'): void
    {
        $log = $deployment->logs()->create(['level' => $level, 'output' => Str::limit($output, 65535, ''), 'created_at' => now()]);
        $this->broadcastQuietly(fn () => DeploymentLogAppended::dispatch($deployment, $log));
    }

    private function quoteEnv(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', ''], $value).'"';
    }
}
