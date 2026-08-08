<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

final class EntitlementService
{
    public function subscription(User $user): ?Subscription
    {
        return $user->subscriptions()->with('plan')->latest()->get()->first(fn (Subscription $subscription) => $subscription->isEntitled());
    }

    public function plan(User $user): ?Plan
    {
        return $this->subscription($user)?->plan ?? Plan::where('slug', 'free')->where('active', true)->first();
    }

    /**
     * Effective create/quota ceiling. Super admins remain uncapped operationally.
     */
    public function limit(User $user, string $resource): int
    {
        if ($user->isSuperAdmin()) {
            return -1;
        }

        return $this->planLimit($user, $resource);
    }

    /**
     * Limits from the entitled plan (Free / Pro / Business, etc.) for billing and dashboard display.
     * Does not apply the super-admin unlimited bypass.
     */
    public function planLimit(User $user, string $resource): int
    {
        $plan = $this->plan($user);
        if (! $plan) {
            return -1;
        }

        $limits = $plan->limits ?? [];

        // Pre-split plans only had a single `sites` pool. Until an admin saves
        // managed_sites, keep the old shared ceiling so existing customers are not
        // suddenly blocked from adding sites on managed hosts.
        if ($resource === 'managed_sites' && ! array_key_exists('managed_sites', $limits)) {
            return (int) ($limits['sites'] ?? 0);
        }

        return (int) ($limits[$resource] ?? 0);
    }
}
