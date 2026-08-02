<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersAndFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    public function test_the_user_list_offers_every_action_against_the_right_account(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['name' => 'Paying Customer', 'email' => 'paying@example.test']);
        Plan::create(['name' => 'Pro', 'slug' => 'pro', 'monthly_price' => 2900, 'yearly_price' => 29000, 'currency' => 'USD', 'limits' => [], 'features' => [], 'active' => true, 'public' => true]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('paying@example.test')
            ->assertSee(route('admin.users.subscription', $customer), false)
            ->assertSee(route('admin.users.role', $customer), false)
            ->assertSee(route('admin.users.suspend', $customer), false)
            ->assertSee('Pro');
    }

    public function test_a_suspended_account_is_labelled_and_offered_a_restore(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'blocked@example.test', 'suspended_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin/users')->assertOk();

        $response->assertSee('Suspended')->assertSee('Restore');
        // The button posts the inverse of the current state, so a suspended account offers 0.
        $response->assertSee('name="suspend" value="0"', false);
    }

    public function test_suspending_still_works_from_the_redesigned_row(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.suspend', $customer), ['suspend' => 1, 'reason' => 'Abuse'])->assertSessionHas('status');

        $this->assertNotNull($customer->fresh()->suspended_at);
    }

    public function test_the_user_list_says_so_when_a_search_matches_nothing(): void
    {
        $this->actingAs($this->admin())->get('/admin/users?search=nobody-by-that-name')
            ->assertOk()
            ->assertSee('No accounts match that search');
    }

    public function test_assigning_a_plan_is_impossible_while_none_is_active(): void
    {
        $admin = $this->admin();
        User::factory()->create();
        Plan::create(['name' => 'Retired', 'slug' => 'retired', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => [], 'features' => [], 'active' => false, 'public' => false]);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('No active plans');
    }

    public function test_a_flag_reads_as_a_rollout_when_on_and_as_off_when_disabled(): void
    {
        $admin = $this->admin();
        FeatureFlag::create(['key' => 'monitoring', 'name' => 'Monitoring', 'enabled' => true, 'rollout_percentage' => 40]);
        FeatureFlag::create(['key' => 'beta_console', 'name' => 'Beta console', 'enabled' => false, 'rollout_percentage' => 100]);

        $this->actingAs($admin)->get('/admin/feature-flags')
            ->assertOk()
            ->assertSee('40% of customers')
            ->assertSee('Off for everyone')
            ->assertSee('monitoring');
    }

    public function test_the_flag_list_explains_itself_when_empty(): void
    {
        $this->actingAs($this->admin())->get('/admin/feature-flags')
            ->assertOk()
            ->assertSee('No feature flags');
    }

    public function test_creating_and_updating_a_flag_still_works(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/feature-flags', ['key' => 'beta_console', 'name' => 'Beta console', 'rollout_percentage' => 25, 'enabled' => '1'])->assertSessionHas('status');
        $flag = FeatureFlag::where('key', 'beta_console')->sole();
        $this->assertSame(25, $flag->rollout_percentage);

        $this->actingAs($admin)->patch("/admin/feature-flags/{$flag->id}", ['name' => 'Beta console', 'rollout_percentage' => 75])->assertSessionHas('status');

        $flag->refresh();
        $this->assertSame(75, $flag->rollout_percentage);
        // The checkbox is absent when unticked, which has to read as off rather than unchanged.
        $this->assertFalse($flag->enabled);
    }
}
