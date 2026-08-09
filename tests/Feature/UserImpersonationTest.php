<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Models\UserImpersonationSession;
use App\Services\ImpersonationManager;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'super_admin',
            'email_verified_at' => now(),
            'password' => Hash::make('admin-secret'),
        ], $overrides));
    }

    private function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'password' => Hash::make('customer-secret'),
        ], $overrides));
    }

    public function test_super_admin_can_impersonate_a_customer(): void
    {
        $admin = $this->admin();
        $customer = $this->customer(['name' => 'Jhay Eleydo']);
        $passwordBefore = $customer->password;

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertAuthenticatedAs($customer->fresh());
        $this->assertTrue(app(ImpersonationManager::class)->isImpersonating());
        $this->assertSame($admin->id, app(ImpersonationManager::class)->adminId());
        $this->assertSame($passwordBefore, $customer->fresh()->password);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You are impersonating Jhay Eleydo', false)
            ->assertSee($customer->email, false)
            ->assertSee('Exit impersonation', false);

        $this->assertDatabaseHas('user_impersonation_sessions', [
            'admin_user_id' => $admin->id,
            'target_user_id' => $customer->id,
            'status' => 'active',
            'support_mode' => 'full',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'impersonation.started',
            'auditable_id' => (string) $customer->id,
        ]);
    }

    public function test_customer_cannot_access_impersonation_routes(): void
    {
        $customer = $this->customer();
        $target = $this->customer();

        $this->actingAs($customer)->get(route('admin.users.show', $target))->assertForbidden();
        $this->actingAs($customer)
            ->post(route('admin.users.impersonate', $target), ['support_mode' => 'full'])
            ->assertForbidden();
    }

    public function test_administrator_cannot_impersonate_another_admin_without_permission(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin(['email' => 'other-admin@example.test']);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $otherAdmin), ['support_mode' => 'full'])
            ->assertSessionHasErrors('impersonate');

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(app(ImpersonationManager::class)->isImpersonating());
    }

    public function test_administrator_can_impersonate_another_admin_when_enabled(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin(['email' => 'other-admin@example.test', 'name' => 'Other Admin']);
        app(SystemSettings::class)->put('allow_impersonate_admins', '1', 'boolean', true);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $otherAdmin), ['support_mode' => 'read_only'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($otherAdmin->fresh());
        $this->assertTrue(app(ImpersonationManager::class)->isReadOnly());
    }

    public function test_recursive_impersonation_is_prevented(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $other = $this->customer(['email' => 'other@example.test']);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full'])
            ->assertRedirect(route('dashboard'));

        $this->post(route('admin.users.impersonate', $other), ['support_mode' => 'full'])
            ->assertForbidden();
    }

    public function test_exit_impersonation_restores_administrator_and_audits_end(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full']);

        $this->post(route('impersonation.exit'))
            ->assertRedirect();

        $this->assertAuthenticatedAs($admin->fresh());
        $this->assertFalse(app(ImpersonationManager::class)->isImpersonating());

        $session = UserImpersonationSession::query()->sole();
        $this->assertSame('ended', $session->status);
        $this->assertNotNull($session->ended_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'impersonation.ended',
        ]);
    }

    public function test_full_access_mode_allows_customer_writes(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full'])
            ->assertRedirect(route('dashboard'));

        $response = $this->post(route('servers.custom.store'), [
            'name' => 'Support box',
            'hostname' => 'support-box',
            'public_ip' => '203.0.113.20',
            'ssh_port' => 22,
        ]);

        $this->assertNotSame(403, $response->status());
    }

    public function test_read_only_mode_blocks_destructive_actions(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'read_only'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('servers.index'))->assertOk();

        $this->post(route('servers.custom.store'), [
            'name' => 'Nope',
            'hostname' => 'nope',
            'public_ip' => '203.0.113.10',
            'ssh_port' => 22,
        ])->assertForbidden();
    }

    public function test_suspended_users_cannot_be_impersonated(): void
    {
        $admin = $this->admin();
        $customer = $this->customer(['suspended_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full'])
            ->assertSessionHasErrors('impersonate');
    }

    public function test_second_active_impersonation_of_same_user_is_blocked(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin(['email' => 'second@example.test']);
        $customer = $this->customer();

        UserImpersonationSession::query()->create([
            'admin_user_id' => $otherAdmin->id,
            'target_user_id' => $customer->id,
            'support_mode' => 'full',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'session_identifier' => 'other-session',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full'])
            ->assertSessionHasErrors('impersonate');
    }

    public function test_impersonation_cannot_open_admin_routes(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full']);

        $this->get('/admin/users')->assertForbidden();
    }

    public function test_logout_terminates_impersonation_session(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full']);

        $this->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
        $this->assertSame('terminated', UserImpersonationSession::query()->sole()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'impersonation.ended']);
    }

    public function test_stale_impersonation_session_is_abandoned(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer), ['support_mode' => 'full']);

        UserImpersonationSession::query()->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_management_page_shows_impersonation_history(): void
    {
        $admin = $this->admin(['name' => 'Orlando Eleydo']);
        $customer = $this->customer();

        UserImpersonationSession::query()->create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $customer->id,
            'support_mode' => 'read_only',
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(30),
            'ip_address' => '203.0.113.50',
            'user_agent' => 'phpunit',
            'session_identifier' => 'hist',
            'status' => 'ended',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', ['user' => $customer, 'tab' => 'impersonation']))
            ->assertOk()
            ->assertSee('Orlando Eleydo')
            ->assertSee('203.0.113.50')
            ->assertSee('Read only');
    }
}
