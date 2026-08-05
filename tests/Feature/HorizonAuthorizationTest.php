<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_horizon(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'));
        $this->actingAs($admin)->get('/horizon')->assertOk();
    }

    public function test_allowlisted_email_can_view_horizon_case_insensitively(): void
    {
        config(['horizon.allowed_emails' => ['ops@uplary.com']]);

        $user = User::factory()->create([
            'email' => 'Ops@Uplary.com',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
        $this->actingAs($user)->get('/horizon')->assertOk();
    }

    public function test_random_authenticated_user_cannot_view_horizon(): void
    {
        config(['horizon.allowed_emails' => ['ops@uplary.com']]);

        $user = User::factory()->create([
            'email' => 'random@example.com',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'));
        $this->actingAs($user)->get('/horizon')->assertForbidden();
    }

    public function test_empty_allowlist_falls_back_to_super_admins_only(): void
    {
        config(['horizon.allowed_emails' => []]);

        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($customer)->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'));
    }

    public function test_unauthenticated_user_cannot_view_horizon(): void
    {
        $this->markInstalled();

        $this->get('/horizon')->assertRedirect(route('login'));
    }
}
