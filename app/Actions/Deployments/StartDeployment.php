<?php

namespace App\Actions\Deployments;

use App\Enums\DeploymentStatus;
use App\Jobs\Deployments\DeployLaravelJob;
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
        $deployment = $site->deployments()->create([
            'user_id' => $user?->id,
            'status' => DeploymentStatus::Pending,
            'trigger' => $trigger,
            'commit_hash' => $commit['hash'] ?? null,
            'commit_message' => $commit['message'] ?? null,
        ]);
        DeployLaravelJob::dispatch($deployment->id)->onQueue('deployments');

        return $deployment;
    }
}
