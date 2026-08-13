<?php

namespace App\Actions\Deployments;

use App\Enums\DeploymentStatus;
use App\Jobs\Deployments\DeployLaravelJob;
use App\Jobs\Deployments\DeployReactJob;
use App\Jobs\Deployments\DeployWordPressJob;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class StartDeployment
{
    public function execute(Site $site, ?User $user, string $trigger = 'manual', array $commit = []): Deployment
    {
        if ($site->deployments()->whereIn('status', [DeploymentStatus::Pending, DeploymentStatus::Running])->exists()) {
            throw ValidationException::withMessages(['deployment' => 'A deployment is already in progress.']);
        }

        // React SPAs are static builds and do not need a database. Laravel and WordPress do.
        if (! $site->isReact()) {
            // A new Laravel site is seeded with cache/queue/session/Redis variables but no
            // database ones: those arrive only when a managed database is attached. Deploy
            // without them and Laravel falls back to sqlite, which fails because provisioned
            // PHP only carries mysql and pgsql. Setting DB_CONNECTION by hand is the escape
            // hatch. WordPress reads credentials from wp-config.php generated from DB_DATABASE.
            $databaseKey = $site->isWordPress() ? 'DB_DATABASE' : 'DB_CONNECTION';

            if (! $site->environmentVariables()->where('key', $databaseKey)->exists()) {
                throw ValidationException::withMessages(['deployment' => 'This site has no database configured. Create one for it from the server\'s Databases tab, or set DB_CONNECTION yourself on the Environment tab if this application does not use a database.']);
            }
        }

        $deployment = $site->deployments()->create([
            'user_id' => $user?->id,
            'status' => DeploymentStatus::Pending,
            'trigger' => $trigger,
            'commit_hash' => $commit['hash'] ?? null,
            'commit_message' => $commit['message'] ?? null,
        ]);
        $job = match (true) {
            $site->isWordPress() => DeployWordPressJob::class,
            $site->isReact() => DeployReactJob::class,
            default => DeployLaravelJob::class,
        };
        $job::dispatch($deployment->id)->onQueue('deployments');

        return $deployment;
    }
}
