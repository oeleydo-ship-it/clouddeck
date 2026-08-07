<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\User;
use App\Services\FeatureManager;
use App\Services\PlatformDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_features_gate_sidebar_and_routes_without_a_feature_flag_row(): void
    {
        $features = array_merge(config('plan-features.defaults.free'), [
            'firewall' => false,
        ]);
        $plan = Plan::create([
            'name' => 'Restricted',
            'slug' => 'restricted',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => ['servers' => 5, 'sites' => 5, 'databases' => 5, 'api_tokens' => 5, 'teams' => 2, 'team_members' => 5],
            'features' => $features,
            'active' => true,
            'public' => true,
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'manual', 'current_period_ends_at' => now()->addMonth()]);

        $this->assertFalse(app(FeatureManager::class)->enabled('firewall', $user));
        $this->assertTrue(app(FeatureManager::class)->enabled('notifications', $user));

        $this->actingAs($user)->get('/firewall')->assertForbidden();
        $this->actingAs($user)->get('/servers')->assertOk();
        $dashboard = $this->actingAs($user)->get('/dashboard')->assertOk();
        $dashboard->assertDontSee(route('firewall.index'), false);
        $dashboard->assertSee(route('servers.index'), false);
    }

    public function test_user_override_can_unlock_a_plan_blocked_feature(): void
    {
        $plan = Plan::create([
            'name' => 'Locked',
            'slug' => 'locked',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => [],
            'features' => ['firewall' => false],
            'active' => true,
            'public' => true,
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'manual']);
        $flag = FeatureFlag::create(['key' => 'firewall', 'name' => 'Firewall', 'enabled' => true, 'rollout_percentage' => 100]);
        $flag->overrides()->create(['user_id' => $user->id, 'enabled' => true]);
        cache()->forget('feature-flag:firewall');

        $this->assertTrue(app(FeatureManager::class)->enabled('firewall', $user));
        $this->actingAs($user)->get('/firewall')->assertOk();
    }

    public function test_seeded_free_plan_denies_premium_modules(): void
    {
        app(PlatformDefaults::class)->ensure();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $free = Plan::where('slug', 'free')->firstOrFail();
        $user->subscriptions()->create(['plan_id' => $free->id, 'status' => 'active', 'provider' => 'system']);

        $this->actingAs($user)->get('/firewall')->assertForbidden();
        $this->actingAs($user)->get('/security')->assertForbidden();
        $this->actingAs($user)->get('/servers')->assertOk();
        $this->actingAs($user)->get('/sites')->assertOk();
    }

    public function test_super_admin_bypasses_plan_feature_gates(): void
    {
        $plan = Plan::create([
            'name' => 'Empty',
            'slug' => 'empty',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => [],
            'features' => ['firewall' => false],
            'active' => true,
            'public' => true,
        ]);
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);
        $admin->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'manual']);

        $this->actingAs($admin)->get('/firewall')->assertOk();
    }
}
