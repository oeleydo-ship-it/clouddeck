<?php

namespace App\Jobs\Deployments;

use App\Enums\DeploymentStatus;
use App\Events\DeploymentFinished;
use App\Events\DeploymentLogAppended;
use App\Jobs\Concerns\BroadcastsQuietly;
use App\Models\Deployment;
use App\Models\Site;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class DeployLaravelJob implements ShouldQueue
{
    use BroadcastsQuietly, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $deploymentId) {}

    public function handle(SshClient $ssh): void
    {
        $deployment = Deployment::with(['site.server.sshKey', 'site.database', 'site.environmentVariables'])->findOrFail($this->deploymentId);

        // The job can still be waiting in the queue when the operator cancels; running it
        // then would undo the cancellation and deploy something nobody is expecting.
        if ($deployment->status === DeploymentStatus::Cancelled) {
            return;
        }

        $release = now()->format('YmdHis').'-'.Str::lower(Str::random(8));
        $started = now();
        $previous = $deployment->site->deployments()->where('status', DeploymentStatus::Successful)->whereNotNull('release')->latest('finished_at')->value('release');
        $deployment->update(['status' => DeploymentStatus::Running, 'started_at' => $started, 'progress' => 5, 'release' => $release, 'previous_release' => $previous]);
        $this->log($deployment, 'Starting release '.$release);

        $site = $deployment->site;
        $this->ensureApplicationKey($site);
        $site->syncManagedDatabaseEnvironment();
        $environment = $site->environmentVariables->map(fn ($variable) => $variable->key.'='.$this->quoteEnv($variable->value))->implode("\n")."\n";
        // The server block is written once, when the site is created. If that never landed,
        // Nginx has no block for this domain and answers requests for it with whichever site
        // it lists first — the deployment succeeds while the domain serves someone else's
        // application. Confirming it here costs one command and cannot be forgotten. An
        // existing block is left exactly as it is, so Certbot's TLS lines survive.
        $ssh->runScript($site->server, resource_path('scripts/configure-site.sh'), [
            'DOMAIN' => $site->domain,
            'PHP_VERSION' => $site->php_version,
            'DOCUMENT_ROOT' => $site->documentRoot(),
            'PLATFORM' => $site->platform ?: 'laravel',
        ]);

        $result = $ssh->runScriptStreaming($site->server, resource_path('scripts/deploy-laravel.sh'), [
            'DOMAIN' => $site->domain,
            'REPOSITORY' => $site->repository_url,
            'BRANCH' => $site->branch,
            'RELEASE' => $release,
            'PHP_VERSION' => $site->php_version,
            'ENVIRONMENT_BASE64' => base64_encode($environment),
            'CUSTOM_SCRIPT_BASE64' => base64_encode($site->deployment_script ?? ''),
            'MANAGED_PACKAGES' => implode(' ', $site->managed_packages ?? []),
        ], function (string $type, string $output) use ($deployment) {
            if (trim($output) !== '') {
                if (preg_match('/\[(\d)\/9\]/', $output, $match)) {
                    $deployment->update(['progress' => min(95, 5 + ((int) $match[1] * 10))]);
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

    /**
     * Sites are created with an empty APP_KEY, which reaches the release as APP_KEY="".
     * The remote script then falls back to `key:generate`, and because Laravel builds its
     * replacement pattern from the current (empty) key it rewrites only the prefix, leaving
     * APP_KEY=base64:NEW="". Worse, the generated key is never stored here, so every single
     * deployment minted a fresh one and invalidated every encrypted column and session.
     * Generating it once and persisting it keeps the key stable across releases.
     */
    private function ensureApplicationKey(Site $site): void
    {
        $existing = $site->environmentVariables->firstWhere('key', 'APP_KEY');
        if ($existing && str_starts_with(trim($existing->value, '"'), 'base64:')) {
            return;
        }

        $site->environmentVariables()->updateOrCreate(
            ['key' => 'APP_KEY'],
            ['value' => 'base64:'.base64_encode(random_bytes(32)), 'is_secret' => true],
        );
        $site->load('environmentVariables');
    }

    private function quoteEnv(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', ''], $value).'"';
    }
}
