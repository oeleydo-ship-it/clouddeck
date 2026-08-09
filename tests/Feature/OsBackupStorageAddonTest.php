<?php

namespace Tests\Feature;

use App\Actions\Billing\ConfirmOsBackupAddon;
use App\Enums\ServerStatus;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\QuotaManager;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OsBackupStorageAddonTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_included_gb_plus_addon_sets_effective_limit(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro-os',
            'monthly_price' => 2900,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => ['os_backup_gb' => 50, 'servers' => 5],
            'features' => array_merge(array_fill_keys(array_keys(config('plan-features.labels')), true), ['os_backups' => true]),
            'active' => true,
            'public' => true,
        ]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'manual', 'status' => 'active']);

        $this->assertSame(50, app(EntitlementService::class)->limit($user, 'os_backup_gb'));

        $user->forceFill([
            'os_backup_addon_gb' => 100,
            'os_backup_stripe_subscription_status' => 'active',
        ])->save();

        $this->assertSame(150, app(EntitlementService::class)->limit($user->fresh(), 'os_backup_gb'));
    }

    public function test_snapshot_create_is_blocked_when_os_backup_capacity_is_full(): void
    {
        [$user, $server] = $this->serverWithPlan(['os_backup_gb' => 2]);
        $server->snapshots()->create([
            'user_id' => $user->id,
            'name' => 'existing',
            'status' => 'ready',
            'size_gigabytes' => 2,
        ]);

        $this->actingAs($user)->post("/servers/{$server->id}/snapshots", [
            'name' => 'too-big',
        ])->assertSessionHasErrors('quota');
    }

    public function test_billing_page_shows_os_backup_addon_purchase(): void
    {
        config(['services.stripe.secret' => 'sk_test_os']);
        [$user] = $this->serverWithPlan(['os_backup_gb' => 10]);

        $this->actingAs($user)->get('/billing')
            ->assertOk()
            ->assertSee('OS backup storage')
            ->assertSee('Buy with Stripe')
            ->assertSee(route('billing.os-backup'), false);
    }

    public function test_checkout_session_completion_applies_addon_gb(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        app(ConfirmOsBackupAddon::class)->fromCheckoutSession([
            'metadata' => [
                'purpose' => 'os_backup_storage',
                'user_id' => (string) $user->id,
                'gb' => '75',
            ],
            'payment_status' => 'paid',
            'status' => 'complete',
            'subscription' => 'sub_os_1',
            'customer' => 'cus_os_1',
        ], true);

        $user->refresh();
        $this->assertSame(75, $user->os_backup_addon_gb);
        $this->assertSame('sub_os_1', $user->os_backup_stripe_subscription_id);
        $this->assertSame('active', $user->os_backup_stripe_subscription_status);
        $this->assertSame('cus_os_1', $user->stripe_customer_id);
    }

    public function test_admin_can_save_os_backup_gb_price(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->put(route('admin.settings.os-backup-pricing'), [
            'os_backup_gb_price' => '1.25',
        ])->assertSessionHas('status');

        $this->assertSame(125, app(SystemSettings::class)->osBackupGbPriceCents());
    }

    public function test_stripe_checkout_redirects_for_os_backup_addon(): void
    {
        config(['services.stripe.secret' => 'sk_test_os', 'services.stripe.automatic_tax' => false]);
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_os_1',
                'url' => 'https://checkout.stripe.com/c/pay/cs_os_1',
            ]),
        ]);
        [$user] = $this->serverWithPlan(['os_backup_gb' => 10]);

        $this->actingAs($user)->post(route('billing.os-backup'), [
            'gigabytes' => 40,
        ])->assertRedirect('https://checkout.stripe.com/c/pay/cs_os_1');
    }

    /**
     * @return array{0: User, 1: Server}
     */
    private function serverWithPlan(array $limits): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::create([
            'name' => 'Capped',
            'slug' => 'capped-'.uniqid(),
            'monthly_price' => 0,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => array_merge(['servers' => 5, 'sites' => 5, 'databases' => 5], $limits),
            'features' => array_merge(array_fill_keys(array_keys(config('plan-features.labels')), true), ['os_backups' => true]),
            'active' => true,
            'public' => true,
        ]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'manual', 'status' => 'active']);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Prod', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Key', 'public_key' => 'ssh-ed25519 AAAA', 'private_key' => 'priv']);
        $server = Server::create([
            'user_id' => $user->id,
            'cloud_account_id' => $account->id,
            'ssh_key_id' => $key->id,
            'provider_id' => '99',
            'name' => 'App',
            'hostname' => 'app-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'public_ip' => '192.0.2.20',
            'status' => ServerStatus::Ready,
        ]);

        return [$user, $server];
    }
}
