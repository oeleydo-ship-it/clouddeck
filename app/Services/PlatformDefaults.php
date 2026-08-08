<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\Plan;

final class PlatformDefaults
{
    /**
     * The plans and feature flags a Uplary instance cannot function without: signups have
     * nowhere to land and every quota check reads an empty table. Deployments only run
     * migrations, never seeders, so a freshly deployed instance had neither until the
     * installer or an operator created them by hand. Everything here is updateOrCreate, so
     * running it again on a live instance changes nothing.
     */
    public function ensure(): void
    {
        $labels = FeatureManager::catalog();
        $defaults = config('plan-features.defaults', []);

        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => ['servers' => 1, 'managed_servers' => 0, 'sites' => 1, 'managed_sites' => 5, 'databases' => 3, 'api_tokens' => 2, 'teams' => 1, 'team_members' => 3],
            'features' => $defaults['free'] ?? [],
            'active' => true,
            'public' => true,
            'sort_order' => 10,
        ]);
        Plan::updateOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'monthly_price' => 2900,
            'yearly_price' => 29000,
            'currency' => 'USD',
            'limits' => ['servers' => 10, 'managed_servers' => 3, 'sites' => 50, 'managed_sites' => 30, 'databases' => 50, 'api_tokens' => 10, 'teams' => 3, 'team_members' => 20],
            'features' => $defaults['pro'] ?? [],
            'active' => true,
            'public' => true,
            'sort_order' => 20,
        ]);
        Plan::updateOrCreate(['slug' => 'business'], [
            'name' => 'Business',
            'monthly_price' => 9900,
            'yearly_price' => 99000,
            'currency' => 'USD',
            'limits' => ['servers' => -1, 'managed_servers' => -1, 'sites' => -1, 'managed_sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1],
            'features' => $defaults['business'] ?? [],
            'active' => true,
            'public' => true,
            'sort_order' => 30,
        ]);

        foreach ($labels as $key => $name) {
            FeatureFlag::updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'enabled' => true, 'rollout_percentage' => 100],
            );
        }
    }

    public function freePlan(): Plan
    {
        return Plan::where('slug', 'free')->firstOrFail();
    }
}
