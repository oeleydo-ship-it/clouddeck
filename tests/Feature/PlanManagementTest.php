<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Pro', 'slug' => 'pro', 'currency' => 'usd',
            'monthly_price' => 2900, 'yearly_price' => 29000, 'sort_order' => 20,
            'servers' => 10, 'managed_servers' => 3, 'sites' => 50, 'managed_sites' => 30, 'databases' => 50, 'api_tokens' => 10,
            'teams' => 3, 'team_members' => 20, 'os_backup_gb' => 50,
            'active' => '1', 'public' => '1', 'feature_monitoring' => '1',
            ...$overrides,
        ];
    }

    public function test_prices_are_entered_as_customers_see_them_and_stored_in_minor_units(): void
    {
        $this->actingAs($this->admin())->post('/admin/plans', $this->payload(['monthly_price' => 2999]))->assertSessionHas('status');

        $plan = Plan::where('slug', 'pro')->sole();
        $this->assertSame(2999, $plan->monthly_price);
        $this->assertSame(29000, $plan->yearly_price);
        $this->assertSame('USD', $plan->currency);
    }

    public function test_a_free_plan_is_allowed_and_unlimited_limits_round_trip(): void
    {
        $this->actingAs($this->admin())->post('/admin/plans', $this->payload([
            'slug' => 'free', 'name' => 'Free', 'monthly_price' => 0, 'yearly_price' => 0, 'servers' => -1,
        ]))->assertSessionHas('status');

        $plan = Plan::where('slug', 'free')->sole();
        $this->assertSame(0, $plan->monthly_price);
        $this->assertSame(-1, $plan->limits['servers']);
        $this->actingAs($this->admin())->get('/admin/plans')->assertOk()->assertSee('Unlimited')->assertSee('Free');
    }

    public function test_a_plan_nobody_is_paying_for_can_be_deleted(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/plans', $this->payload());
        $plan = Plan::where('slug', 'pro')->sole();

        $this->actingAs($admin)->delete("/admin/plans/{$plan->id}")->assertSessionHas('status');

        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.deleted', 'auditable_id' => $plan->id]);
    }

    public function test_a_plan_with_subscribers_cannot_be_deleted_out_from_under_them(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/plans', $this->payload());
        $plan = Plan::where('slug', 'pro')->sole();
        Subscription::create(['user_id' => $admin->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);

        $this->actingAs($admin)->delete("/admin/plans/{$plan->id}")->assertSessionHasErrors('plan');

        // Every quota check reads the plan, so deleting it would break the subscriber.
        $this->assertNotSoftDeleted('plans', ['id' => $plan->id]);
    }

    public function test_a_cancelled_subscription_does_not_pin_a_plan_in_place(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/plans', $this->payload());
        $plan = Plan::where('slug', 'pro')->sole();
        Subscription::create(['user_id' => $admin->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'canceled']);

        $this->actingAs($admin)->delete("/admin/plans/{$plan->id}")->assertSessionHas('status');
        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
    }

    public function test_the_page_shows_pricing_limits_and_subscriber_counts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/plans', $this->payload());

        $this->actingAs($admin)->get('/admin/plans')
            ->assertOk()
            ->assertSee('Pro')
            ->assertSee('USD 29')
            ->assertSee('Monitoring')
            ->assertSee('0 subscriptions');
    }

    public function test_non_zero_hosting_quotas_enable_matching_access_gates(): void
    {
        $this->actingAs($this->admin())->post('/admin/plans', $this->payload([
            'slug' => 'starter',
            'name' => 'Starter',
            // Intentionally omit feature_providers / feature_managed_servers checkboxes.
            'servers' => 2,
            'managed_servers' => 1,
            'managed_sites' => 3,
            'os_backup_gb' => 25,
        ]))->assertSessionHas('status');

        $plan = Plan::where('slug', 'starter')->sole();
        $this->assertTrue($plan->features['providers']);
        $this->assertTrue($plan->features['managed_servers']);
        $this->assertTrue($plan->features['os_backups']);
        $this->assertSame(25, $plan->limits['os_backup_gb']);
    }

    public function test_customers_cannot_create_or_delete_plans(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::create(['name' => 'Free', 'slug' => 'free', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => [], 'features' => [], 'active' => true, 'public' => true]);

        $this->actingAs($customer)->post('/admin/plans', $this->payload())->assertForbidden();
        $this->actingAs($customer)->delete("/admin/plans/{$plan->id}")->assertForbidden();
        $this->assertNotSoftDeleted('plans', ['id' => $plan->id]);
    }

    public function test_public_homepage_uses_stored_plan_cents_not_invented_copy(): void
    {
        $this->markInstalled();
        $this->actingAs($this->admin())->post('/admin/plans', $this->payload());
        $this->post('/logout');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Marketing/Home')
                ->where('plans.0.monthly_price', 2900)
                ->where('plans.0.yearly_price', 29000)
                ->where('plans.0.currency', 'USD')
                ->where('plans.0.name', 'Pro'));
    }

    public function test_yearly_price_may_be_omitted_and_stores_zero(): void
    {
        $this->actingAs($this->admin())->post('/admin/plans', $this->payload([
            'yearly_price' => '',
        ]))->assertSessionHas('status');

        $this->assertSame(0, Plan::where('slug', 'pro')->sole()->yearly_price);
    }
}
