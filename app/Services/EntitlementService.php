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
     * OS backup GB = plan-included capacity plus any active Stripe GB add-on.
     */
    public function limit(User $user, string $resource): int
    {
        if ($user->isSuperAdmin()) {
            return -1;
        }

        $planLimit = $this->planLimit($user, $resource);
        if ($resource !== 'os_backup_gb') {
            return $planLimit;
        }

        if ($planLimit < 0) {
            return -1;
        }

        $addon = in_array($user->os_backup_stripe_subscription_status, ['active', 'trialing'], true)
            ? (int) $user->os_backup_addon_gb
            : 0;

        return $planLimit + $addon;
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
