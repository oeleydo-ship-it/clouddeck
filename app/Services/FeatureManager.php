<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class FeatureManager
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function enabled(string $key, User $user): bool
    {
        $flag = Cache::remember('feature-flag:'.$key, 60, fn () => FeatureFlag::with('overrides')->where('key', $key)->first());
        if (! $flag) {
            return false;
        }
        $userOverride = $flag->overrides->firstWhere('user_id', $user->id);
        if ($userOverride) {
            return $userOverride->enabled;
        }
        if (! $flag->enabled) {
            return false;
        }
        $plan = $this->entitlements->plan($user);
        $planOverride = $plan ? $flag->overrides->firstWhere('plan_id', $plan->id) : null;
        if ($planOverride) {
            return $planOverride->enabled;
        }
        if ($plan && array_key_exists($key, $plan->features ?? []) && ! $plan->features[$key]) {
            return false;
        }

        $bucket = (int) sprintf('%u', crc32($user->id.':'.$key)) % 100;

        return $bucket < $flag->rollout_percentage;
    }
}
