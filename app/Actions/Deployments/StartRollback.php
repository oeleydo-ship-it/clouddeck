<?php

namespace App\Actions\Deployments;

use App\Enums\DeploymentStatus;
use App\Jobs\Deployments\RollbackDeploymentJob;
use App\Models\Deployment;
use App\Models\User;

final class StartRollback
{
    public function execute(Deployment $target, User $user): Deployment
    {
        $rollback = $target->site->deployments()->create([
            'user_id' => $user->id,
            'status' => DeploymentStatus::Pending,
            'trigger' => 'rollback',
            'release' => $target->release,
        ]);
        RollbackDeploymentJob::dispatch($rollback->id)->onQueue('deployments');

        return $rollback;
    }
}
