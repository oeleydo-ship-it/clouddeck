<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The dashboard says which plan an account is on and what it has left, using the same
 * entitlement and quota services as the billing page so the two cannot disagree.
 */
class DashboardPlanPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_names_the_current_plan_and_its_headroom(): void
    {
        $user = $this->subscribed('Starter', 0, ['servers' => 2, 'sites' => 5, 'databases' => 3]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('plan.heading', 'Current plan')
                ->where('plan.plan.name', 'Starter')
                ->where('plan.usage.servers.label', '0 / 2')
                ->where('plan.usage.sites.label', '0 / 5'));
    }

    public function test_a_cheaper_plan_is_offered_the_next_one_up_by_name(): void
    {
        $user = $this->subscribed('Starter', 1000, ['servers' => 2]);
        Plan::create(['name' => 'Growth', 'slug' => 'growth', 'monthly_price' => 5000, 'limits' => ['servers' => 10], 'active' => true, 'public' => true]);
        Plan::create(['name' => 'Scale', 'slug' => 'scale', 'monthly_price' => 9000, 'limits' => ['servers' => 50], 'active' => true, 'public' => true]);

        // The cheapest plan above the current one, not merely the most expensive on offer.
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('plan.upgrade.name', 'Growth')
                ->where('plan.upgrade_label', 'Upgrade to Growth'));
    }

    public function test_the_top_plan_is_not_offered_an_upgrade_that_does_not_exist(): void
    {
        $user = $this->subscribed('Scale', 9000, ['servers' => 50]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('plan.upgrade', null)
                ->where('plan.upgrade_label', null)
                ->where('plan.no_upgrade', 'nothing to upgrade to'));
    }

    public function test_a_plan_at_its_limit_says_so_rather_than_showing_a_quiet_full_bar(): void
    {
        $user = $this->subscribed('Starter', 0, ['servers' => 1]);
        $user->servers()->create([
            'name' => 'Production', 'hostname' => 'production', 'public_ip' => '203.0.113.10',
            'region' => 'ams3', 'size' => 's-1vcpu-1gb', 'status' => 'ready',
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('plan.usage.servers.label', '1 / 1')
                ->where('plan.usage.servers.at_limit', true)
                ->where('plan.limit_reached', 'Limit reached'));
    }

    private function subscribed(string $name, int $price, array $limits): User
    {
        $user = User::factory()->create();
        $plan = Plan::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'monthly_price' => $price,
            'limits' => $limits,
            'active' => true,
            'public' => true,
        ]);
        $user->subscriptions()->create(['plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);

        return $user;
    }
}
