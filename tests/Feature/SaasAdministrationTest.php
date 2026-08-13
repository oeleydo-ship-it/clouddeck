<?php

namespace Tests\Feature;

use App\Models\CloudAccount;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\Services\EntitlementService;
use App\Services\FeatureManager;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SaasAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_open_control_center(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);

        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('SaaS control center');
    }

    public function test_server_and_api_token_quotas_are_enforced_across_api_and_web(): void
    {
        $plan = $this->plan(['servers' => 0, 'api_tokens' => 0]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'manual', 'current_period_ends_at' => now()->addMonth()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret']]);
        Sanctum::actingAs($user, ['servers:write']);

        $this->postJson('/api/servers', ['cloud_account_id' => $account->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64'])->assertUnprocessable()->assertJsonValidationErrors('quota');
        $this->actingAs($user)->post('/account/tokens', ['name' => 'CLI'])->assertSessionHasErrors('quota');
    }

    public function test_expired_subscription_falls_back_to_free_plan(): void
    {
        $free = $this->plan(['servers' => 1], 'free');
        $pro = $this->plan(['servers' => 10], 'pro');
        $user = User::factory()->create();
        $user->subscriptions()->create(['plan_id' => $pro->id, 'status' => 'active', 'provider' => 'manual', 'current_period_ends_at' => now()->subDay()]);

        $this->assertSame($free->id, app(EntitlementService::class)->plan($user)->id);
        $this->assertSame(1, app(EntitlementService::class)->limit($user, 'servers'));
    }

    public function test_plan_feature_and_user_override_are_respected(): void
    {
        $plan = $this->plan([], 'restricted', ['remote_management' => false]);
        $user = User::factory()->create();
        $user->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'manual']);
        $flag = FeatureFlag::create(['key' => 'remote_management', 'name' => 'Remote management', 'enabled' => true, 'rollout_percentage' => 100]);

        $this->assertFalse(app(FeatureManager::class)->enabled('remote_management', $user));
        $flag->overrides()->create(['user_id' => $user->id, 'enabled' => true]);
        cache()->forget('feature-flag:remote_management');
        $this->assertTrue(app(FeatureManager::class)->enabled('remote_management', $user));
    }

    public function test_admin_suspension_revokes_tokens_blocks_access_and_is_audited(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $customer->createToken('CLI');

        $this->actingAs($admin)->patch("/admin/users/{$customer->id}/suspension", ['suspend' => '1', 'reason' => 'Payment dispute'])->assertSessionHas('status');

        $this->assertNotNull($customer->fresh()->suspended_at);
        $this->assertSame(0, $customer->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'user.suspended', 'auditable_id' => (string) $customer->id]);
        $this->actingAs($customer->fresh())->get('/dashboard')->assertForbidden();
    }

    public function test_billing_request_approval_replaces_subscription_and_writes_encrypted_audit(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $free = $this->plan([], 'free');
        $pro = $this->plan(['servers' => 10], 'pro');
        $user->subscriptions()->create(['plan_id' => $free->id, 'status' => 'active', 'provider' => 'system']);
        $change = $user->billingRequests()->create(['plan_id' => $pro->id, 'status' => 'pending', 'billing_cycle' => 'monthly']);

        $this->actingAs($admin)->patch("/admin/billing-requests/{$change->id}", ['decision' => 'approve', 'period_days' => 30, 'admin_note' => 'Invoice paid'])->assertSessionHas('status');

        $this->assertSame('approved', $change->fresh()->status);
        $this->assertSame($pro->id, $user->subscriptions()->where('status', 'active')->firstOrFail()->plan_id);
        $raw = DB::table('audit_logs')->where('action', 'billing.reviewed')->value('new_values');
        $this->assertStringNotContainsString('Invoice paid', $raw);
    }

    public function test_team_invitation_is_hashed_email_bound_and_single_use(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email' => 'member@example.com', 'email_verified_at' => now()]);
        $this->actingAs($owner)->post('/teams', ['name' => 'Platform'])->assertSessionHas('status');
        $team = $owner->ownedTeams()->firstOrFail();
        $this->actingAs($owner)->post("/teams/{$team->id}/invitations", ['email' => $member->email, 'role' => 'admin'])->assertSessionHas('status');
        $token = null;
        Notification::assertSentOnDemand(TeamInvitationNotification::class, function ($notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $invitation = TeamInvitation::firstOrFail();

        $this->assertNotSame($token, $invitation->token_hash);
        $this->actingAs($member)->get("/team-invitations/{$invitation->id}/{$token}")->assertRedirect('/teams');
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'admin']);
        $this->actingAs($member)->get("/team-invitations/{$invitation->id}/{$token}")->assertForbidden();
    }

    public function test_registration_can_be_disabled_and_new_users_receive_free_plan(): void
    {
        $this->markInstalled();
        $free = $this->plan([], 'free');
        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'very-secure-password-1', 'password_confirmation' => 'very-secure-password-1'])->assertRedirect('/dashboard');
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame($free->id, $user->subscriptions()->firstOrFail()->plan_id);

        SystemSetting::create(['key' => 'registration_enabled', 'value' => '0', 'type' => 'boolean', 'is_public' => true]);
        auth()->logout();
        $this->get('/register')->assertForbidden();
    }

    public function test_admin_can_update_plan_limits_and_entitlements(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);
        $plan = $this->plan([], 'starter');

        $this->actingAs($admin)->patch("/admin/plans/{$plan->id}", [
            'name' => 'Starter Plus', 'slug' => 'starter-plus', 'currency' => 'usd',
            'monthly_price' => 19, 'yearly_price' => 190, 'sort_order' => 15,
            'servers' => 3, 'managed_servers' => 1, 'sites' => 12, 'managed_sites' => 8, 'databases' => 5, 'api_tokens' => 4,
            'teams' => 1, 'team_members' => 8, 'os_backup_gb' => 0, 'active' => '1', 'public' => '1',
            'feature_monitoring' => '1', 'feature_teams' => '1',
        ])->assertSessionHas('status');

        $plan->refresh();
        $this->assertSame('Starter Plus', $plan->name);
        // Prices are entered as customers see them and stored in minor units.
        $this->assertSame(1900, $plan->monthly_price);
        $this->assertSame(19000, $plan->yearly_price);
        $this->assertSame(3, $plan->limits['servers']);
        $this->assertSame(1, $plan->limits['managed_servers']);
        $this->assertTrue($plan->features['monitoring']);
        $this->assertFalse($plan->features['remote_management']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.updated', 'auditable_id' => $plan->id]);
    }

    public function test_suspended_user_cannot_complete_pending_two_factor_login(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $user = User::factory()->create(['suspended_at' => now(), 'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);

        $this->withSession(['login.id' => $user->id, 'login.remember' => true])
            ->post('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertFalse(session()->has('login.id'));
    }

    public function test_super_admin_can_disable_email_verification_for_registration_and_middleware(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);
        $this->actingAs($admin)->put('/admin/settings', ['registration_enabled' => '1'])->assertSessionHas('status');
        auth()->logout();

        $this->post('/register', ['name' => 'Development User', 'email' => 'development@example.com', 'password' => 'very-secure-password-1', 'password_confirmation' => 'very-secure-password-1'])->assertRedirect('/dashboard');
        $user = User::where('email', 'development@example.com')->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        $this->actingAs($user)->get('/dashboard')->assertOk();
        Notification::assertNothingSent();
        $this->assertSame('0', SystemSetting::whereKey('email_verification_required')->firstOrFail()->value);
    }

    public function test_super_admin_can_require_email_verification_again(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);
        $unverified = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($admin)->put('/admin/settings', ['registration_enabled' => '1', 'email_verification_required' => '1'])->assertSessionHas('status');
        $this->actingAs($unverified)->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_email_verification_middleware_is_skipped_in_local_environment(): void
    {
        $this->app['env'] = 'local';
        SystemSetting::updateOrCreate(
            ['key' => 'email_verification_required'],
            ['value' => '1', 'type' => 'boolean', 'is_public' => true],
        );
        $unverified = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($unverified)->get('/dashboard')->assertOk();
    }

    private function plan(array $limits = [], string $slug = 'test', array $features = []): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'limits' => array_merge(['servers' => 5, 'sites' => 5, 'databases' => 5, 'api_tokens' => 5, 'teams' => 2, 'team_members' => 5], $limits),
            // Tests that only care about quotas get a fully entitled plan unless they pass
            // an explicit features map (including false values).
            'features' => $features === []
                ? array_fill_keys(array_keys(config('plan-features.labels')), true)
                : $features,
            'active' => true,
            'public' => true,
        ]);
    }
}
