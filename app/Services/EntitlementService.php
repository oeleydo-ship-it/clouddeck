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

    public function limit(User $user, string $resource): int
    {
        if ($user->isSuperAdmin()) {
            return -1;
        }

        $plan = $this->plan($user);

        return $plan ? (int) ($plan->limits[$resource] ?? 0) : -1;
    }
}
