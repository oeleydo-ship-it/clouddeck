<?php

namespace App\Actions\Sites;

use App\Actions\Deployments\StartDeployment;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Validation\ValidationException;

final class PromoteStagingSite
{
    public function __construct(
        private readonly SystemSettings $settings,
        private readonly StartDeployment $deployments,
    ) {}

    public function execute(Site $staging, User $actor): Deployment
    {
        if (! $this->settings->stagingSitesEnabled()) {
            throw ValidationException::withMessages(['staging' => 'Staging sites are disabled for this platform.']);
        }

        if (! $staging->isStaging()) {
            throw ValidationException::withMessages(['staging' => 'Only a staging site can be promoted.']);
        }

        $production = $staging->productionSite;
        if (! $production) {
            throw ValidationException::withMessages(['staging' => 'This staging site is not linked to a production site.']);
        }

        if ($staging->status !== 'active' || $production->status !== 'active') {
            throw ValidationException::withMessages(['staging' => 'Both staging and production must be active before promoting.']);
        }

        $production->update([
            'repository_url' => $staging->repository_url,
            'branch' => $staging->branch,
            'deployment_script' => $staging->deployment_script,
            'php_version' => $staging->php_version,
            'zero_downtime' => $staging->zero_downtime,
        ]);

        return $this->deployments->execute($production, $actor, 'promote');
    }
}
