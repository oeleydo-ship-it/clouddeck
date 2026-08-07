<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class FeatureManager
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return config('plan-features.labels', []);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /**
     * Resolve every catalog feature for the sidebar / composers in one pass.
     *
     * @return array<string, bool>
     */
    public function mapFor(User $user): array
    {
        $map = [];
        foreach (self::keys() as $key) {
            $map[$key] = $this->enabled($key, $user);
        }

        return $map;
    }

    public function enabled(string $key, User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $flag = Cache::remember('feature-flag:'.$key, 60, fn () => FeatureFlag::with('overrides')->where('key', $key)->first());

        if ($flag) {
            $userOverride = $flag->overrides->firstWhere('user_id', $user->id);
            if ($userOverride) {
                return (bool) $userOverride->enabled;
            }
            if (! $flag->enabled) {
                return false;
            }
        }

        $plan = $this->entitlements->plan($user);

        if ($flag && $plan) {
            $planOverride = $flag->overrides->firstWhere('plan_id', $plan->id);
            if ($planOverride) {
                return (bool) $planOverride->enabled;
            }
        }

        // No entitled plan (and no free fallback) mirrors quota unmetered mode: allow
        // everything so fresh / test installs remain usable until plans are seeded.
        if (! $plan) {
            if (! $flag) {
                return true;
            }
            $bucket = (int) sprintf('%u', crc32($user->id.':'.$key)) % 100;

            return $bucket < $flag->rollout_percentage;
        }

        $planAllows = $this->planAllows($plan->features ?? [], $key);
        if (! $planAllows) {
            return false;
        }

        // Plan says yes. Without a flag row the entitlement alone is enough; with a flag,
        // keep stable percentage rollout for staged launches.
        if (! $flag) {
            return true;
        }

        $bucket = (int) sprintf('%u', crc32($user->id.':'.$key)) % 100;

        return $bucket < $flag->rollout_percentage;
    }

    /**
     * Catalog keys missing from a non-empty plan map are denied. An empty features array
     * is treated as unmetered (legacy / test “unlimited” plans) — same idea as missing limits.
     *
     * @param  array<string, mixed>  $features
     */
    private function planAllows(array $features, string $key): bool
    {
        if ($features === []) {
            return true;
        }

        if (array_key_exists($key, $features)) {
            return (bool) $features[$key];
        }

        return false;
    }
}
