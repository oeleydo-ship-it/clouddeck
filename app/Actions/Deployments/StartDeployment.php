<?php

namespace App\Actions\Deployments;

use App\Enums\DeploymentStatus;
use App\Jobs\Deployments\DeployLaravelJob;
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

        // A new site is seeded with cache, queue, session, and Redis variables but no
        // database ones: those arrive only when a managed database is attached. Deploy
        // without them and Laravel falls back to its built-in sqlite default, which fails
        // two minutes in with "could not find driver" because the provisioned PHP carries
        // only the mysql and pgsql drivers. Say so up front instead. Setting DB_CONNECTION
        // by hand is the escape hatch for an application that genuinely has no database.
        // WordPress reads its credentials from wp-config.php, which Uplary generates from
        // DB_DATABASE and friends, so it needs a database just as much — it simply never has
        // a DB_CONNECTION to check for.
        $databaseKey = $site->isWordPress() ? 'DB_DATABASE' : 'DB_CONNECTION';

        if (! $site->environmentVariables()->where('key', $databaseKey)->exists()) {
            throw ValidationException::withMessages(['deployment' => 'This site has no database configured. Create one for it from the server\'s Databases tab, or set DB_CONNECTION yourself on the Environment tab if this application does not use a database.']);
        }
        $deployment = $site->deployments()->create([
            'user_id' => $user?->id,
            'status' => DeploymentStatus::Pending,
            'trigger' => $trigger,
            'commit_hash' => $commit['hash'] ?? null,
            'commit_message' => $commit['message'] ?? null,
        ]);
        $job = $site->isWordPress() ? DeployWordPressJob::class : DeployLaravelJob::class;
        $job::dispatch($deployment->id)->onQueue('deployments');

        return $deployment;
    }
}
