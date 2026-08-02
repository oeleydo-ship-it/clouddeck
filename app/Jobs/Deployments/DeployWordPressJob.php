<?php

namespace App\Jobs\Deployments;

use App\Enums\DeploymentStatus;
use App\Events\DeploymentFinished;
use App\Events\DeploymentLogAppended;
use App\Jobs\Concerns\BroadcastsQuietly;
use App\Jobs\Sites\CheckWordPressInstallJob;
use App\Models\Deployment;
use App\Services\WordPressConfig;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * WordPress is downloaded from wordpress.org rather than cloned, and configured by a
 * generated wp-config.php rather than an environment file, so it does not share the
 * Laravel pipeline. What it does share is the release layout: build beside the live site,
 * then switch with a symlink, so a failure never takes the running site down.
 */
class DeployWordPressJob implements ShouldQueue
{
    use BroadcastsQuietly, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $deploymentId) {}

    public function handle(SshClient $ssh, WordPressConfig $config): void
    {
        $deployment = Deployment::with(['site.server.sshKey', 'site.environmentVariables'])->findOrFail($this->deploymentId);
        $site = $deployment->site;
        $release = now()->format('YmdHis').'-'.Str::lower(Str::random(8));
        $started = now();
        $previous = $site->deployments()->where('status', DeploymentStatus::Successful)->whereNotNull('release')->latest('finished_at')->value('release');

        $deployment->update(['status' => DeploymentStatus::Running, 'started_at' => $started, 'progress' => 5, 'release' => $release, 'previous_release' => $previous]);
        $this->log($deployment, 'Starting WordPress release '.$release);

        $result = $ssh->runScriptStreaming($site->server, resource_path('scripts/deploy-wordpress.sh'), [
            'DOMAIN' => $site->domain,
            'RELEASE' => $release,
            'PHP_VERSION' => $site->php_version,
            'WP_CONFIG_BASE64' => base64_encode($config->render($site)),
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
            throw new RuntimeException('WordPress deployment failed with exit code '.($result->exitCode() ?? 1).'.');
        }

        $finished = now();
        preg_match('/CLOUDDECK_WP_VERSION=([0-9.]+)/', $result->output(), $version);
        $deployment->update([
            'status' => DeploymentStatus::Successful,
            'finished_at' => $finished,
            'duration_ms' => $started->diffInMilliseconds($finished),
            'exit_code' => 0,
            'progress' => 100,
            'commit_message' => isset($version[1]) ? 'WordPress '.$version[1] : null,
        ]);
        $site->update(['status' => 'active', 'last_deployed_at' => $finished]);
        $this->log($deployment, 'Deployment completed successfully.');
        // The files are in place; whether the install has been completed is a separate
        // question, and only the database can answer it.
        CheckWordPressInstallJob::dispatch($site->id)->onQueue('operations');
        $this->broadcastQuietly(fn () => DeploymentFinished::dispatch($deployment->fresh(['site.user'])));
    }

    public function failed(Throwable $exception): void
    {
        $deployment = Deployment::find($this->deploymentId);

        if ($deployment && $deployment->status !== DeploymentStatus::Failed) {
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
}
