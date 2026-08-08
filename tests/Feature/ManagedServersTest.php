<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Servers\BootstrapServerJob;
use App\Jobs\Servers\CreateDropletJob;
use App\Jobs\Servers\FinalizeProvisioningJob;
use App\Jobs\Servers\WaitForServerJob;
use App\Livewire\ManagedServerProvisionWizard;
use App\Models\Plan;
use App\Models\Server;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ManagedServersTest extends TestCase
{
    use RefreshDatabase;

    private function enableManagedPlatform(string $token = 'dop_v1_platform'): void
    {
        $settings = app(SystemSettings::class);
        $settings->put('managed_servers_enabled', '1', 'boolean', true);
        $settings->put('managed_cloud_provider', 'digitalocean', 'string', true);
        $settings->put('managed_cloud_token', $token, 'string', false);
    }

    private function fakeDigitalOceanCatalog(): void
    {
        Http::fake([
            'https://api.digitalocean.com/v2/regions*' => Http::response(['regions' => [
                ['slug' => 'nyc3', 'name' => 'New York 3', 'available' => true],
            ]], 200),
            'https://api.digitalocean.com/v2/sizes*' => Http::response(['sizes' => [
                ['slug' => 's-1vcpu-1gb', 'vcpus' => 1, 'memory' => 1024, 'price_monthly' => 6],
            ]], 200),
            'https://api.digitalocean.com/v2/images*' => Http::response(['images' => [
                ['slug' => 'ubuntu-24-04-x64', 'name' => 'Ubuntu 24.04', 'distribution' => 'Ubuntu', 'created_at' => now()->toIso8601String()],
            ]], 200),
        ]);
    }

    private function entitledUser(array $features = ['managed_servers' => true], array $limits = ['managed_servers' => 2, 'servers' => 5]): User
    {
        $plan = Plan::create([
            'name' => 'Managed Pro',
            'slug' => 'managed-pro',
            'monthly_price' => 2900,
            'yearly_price' => 29000,
            'currency' => 'USD',
            'limits' => array_merge(['sites' => 10, 'databases' => 10, 'api_tokens' => 5, 'teams' => 2, 'team_members' => 5], $limits),
            'features' => array_merge(array_fill_keys(array_keys(config('plan-features.labels')), true), $features),
            'active' => true,
            'public' => true,
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'provider' => 'manual',
            'current_period_ends_at' => now()->addMonth(),
        ]);

        return $user;
    }

    public function test_admin_can_enable_managed_servers_and_store_platform_token(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->put('/admin/settings/managed-servers', [
            'managed_servers_enabled' => '1',
            'managed_cloud_provider' => 'digitalocean',
            'managed_cloud_token' => 'dop_v1_secret',
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertTrue($settings->managedServersEnabled());
        $this->assertTrue($settings->managedServersReady());
        $this->assertSame('dop_v1_secret', $settings->managedCloudToken());
    }

    public function test_managed_route_is_closed_when_platform_disabled(): void
    {
        $user = $this->entitledUser();

        $this->actingAs($user)->get('/servers/managed')->assertNotFound();
    }

    public function test_managed_route_requires_plan_feature(): void
    {
        $this->enableManagedPlatform();
        $user = $this->entitledUser(['managed_servers' => false]);

        $this->actingAs($user)->get('/servers/managed')->assertForbidden();
    }

    public function test_entitled_customer_can_open_managed_wizard_when_ready(): void
    {
        $this->enableManagedPlatform();
        $user = $this->entitledUser();
        $this->fakeDigitalOceanCatalog();

        $this->actingAs($user)->get('/servers/managed')->assertOk()->assertSee('Provision a managed server');
    }

    public function test_managed_deploy_uses_platform_source_and_separate_quota(): void
    {
        Bus::fake();
        $this->enableManagedPlatform();
        $user = $this->entitledUser(['managed_servers' => true], ['managed_servers' => 1, 'servers' => 0]);
        $this->fakeDigitalOceanCatalog();

        Livewire::actingAs($user)
            ->test(ManagedServerProvisionWizard::class)
            ->set('region', 'nyc3')
            ->set('size', 's-1vcpu-1gb')
            ->set('image', 'ubuntu-24-04-x64')
            ->set('name', 'Managed App')
            ->set('hostname', 'managed-app-01')
            ->call('deploy')
            ->assertRedirect();

        $server = Server::firstOrFail();
        $this->assertSame('managed', $server->provisioning_source);
        $this->assertNull($server->cloud_account_id);
        $this->assertTrue($server->isManaged());
        $this->assertSame(ServerStatus::Pending, $server->status);
        Bus::assertChained([CreateDropletJob::class, WaitForServerJob::class, BootstrapServerJob::class, FinalizeProvisioningJob::class]);
    }

    public function test_servers_index_shows_managed_cta_when_ready_and_entitled(): void
    {
        $this->enableManagedPlatform();
        $user = $this->entitledUser();

        $this->actingAs($user)->get('/servers')
            ->assertOk()
            ->assertSee('Managed server')
            ->assertSee(route('servers.managed'), false);
    }

    public function test_admin_can_set_a_default_markup_and_per_size_price_overrides(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->put('/admin/settings/managed-servers/pricing', [
            'markup_percent' => 25,
            'prices' => ['s-1vcpu-1gb' => '12.50', 's-1vcpu-4gb' => ''],
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertSame(25.0, $settings->managedMarkupPercent());
        $this->assertSame(['s-1vcpu-1gb' => 12.5], $settings->managedSizePrices());

        // No override for this size: falls back to infra cost + default markup.
        $this->assertSame(7.5, $settings->managedServerPrice(['slug' => 's-1vcpu-4gb', 'price_monthly' => 6]));
        // Explicit override wins regardless of markup.
        $this->assertSame(12.5, $settings->managedServerPrice(['slug' => 's-1vcpu-1gb', 'price_monthly' => 6]));
    }

    public function test_managed_wizard_shows_marked_up_price_and_stores_it_on_the_server(): void
    {
        Bus::fake();
        $this->enableManagedPlatform();
        $settings = app(SystemSettings::class);
        $settings->put('managed_markup_percent', '50', 'string', true);
        $user = $this->entitledUser(['managed_servers' => true], ['managed_servers' => 1, 'servers' => 0]);
        $this->fakeDigitalOceanCatalog();

        Livewire::actingAs($user)
            ->test(ManagedServerProvisionWizard::class)
            ->set('region', 'nyc3')
            ->set('size', 's-1vcpu-1gb')
            ->set('image', 'ubuntu-24-04-x64')
            ->set('name', 'Managed App')
            ->set('hostname', 'managed-app-01')
            ->assertSet('step', 1)
            ->call('next')->call('next')->call('next')
            ->assertSee('9.00')
            ->call('deploy')
            ->assertRedirect();

        $server = Server::firstOrFail();
        $this->assertEquals(6.0, $server->metadata['infra_price_monthly']);
        $this->assertEquals(9.0, $server->metadata['customer_price_monthly']);
    }

    public function test_public_pricing_hides_managed_quotas_when_platform_managed_servers_are_disabled(): void
    {
        $this->markInstalled();
        Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => [
                'servers' => 1,
                'managed_servers' => 5,
                'sites' => 1,
                'managed_sites' => 5,
                'databases' => 3,
                'api_tokens' => 2,
                'teams' => 1,
                'team_members' => 3,
            ],
            'features' => array_merge(array_fill_keys(array_keys(config('plan-features.labels')), true), ['managed_servers' => true]),
            'active' => true,
            'public' => true,
            'sort_order' => 10,
        ]);

        app(SystemSettings::class)->put('managed_servers_enabled', '0', 'boolean', true);
        app(SystemSettings::class)->put('public_site_enabled', '1', 'boolean', true);

        $this->get('/')
            ->assertOk()
            ->assertSee('servers')
            ->assertDontSee('managed servers')
            ->assertDontSee('managed sites')
            ->assertDontSee('BYOS servers');

        $this->enableManagedPlatform();

        $this->get('/')
            ->assertOk()
            ->assertSee('BYOS servers')
            ->assertSee('managed servers')
            ->assertSee('managed sites');
    }

    public function test_byos_and_managed_site_quotas_are_enforced_separately(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->enableManagedPlatform();
        $user = $this->entitledUser(
            ['managed_servers' => true, 'providers' => true],
            ['managed_servers' => 2, 'servers' => 2, 'sites' => 1, 'managed_sites' => 5],
        );

        $byos = Server::create([
            'user_id' => $user->id,
            'name' => 'BYOS',
            'hostname' => 'byos-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'status' => ServerStatus::Ready,
            'provisioning_source' => 'byos',
            'public_ip' => '203.0.113.10',
        ]);
        $managed = Server::create([
            'user_id' => $user->id,
            'name' => 'Managed',
            'hostname' => 'managed-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'status' => ServerStatus::Ready,
            'provisioning_source' => 'managed',
            'public_ip' => '203.0.113.11',
        ]);

        $sitePayload = fn (string $serverId, string $domain) => [
            'server_id' => $serverId,
            'domain' => $domain,
            'php_version' => config('clouddeck.php_versions')[0] ?? '8.3',
            'platform' => 'laravel',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
        ];

        $this->actingAs($user)->post('/sites', $sitePayload($byos->id, 'byos.example.com'))->assertRedirect();
        $this->actingAs($user)->post('/sites', $sitePayload($byos->id, 'byos-2.example.com'))->assertSessionHasErrors('quota');

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)->post('/sites', $sitePayload($managed->id, "managed-{$i}.example.com"))->assertRedirect();
        }
        $this->actingAs($user)->post('/sites', $sitePayload($managed->id, 'managed-6.example.com'))->assertSessionHasErrors('quota');

        $this->assertSame(1, app(\App\Services\QuotaManager::class)->usage($user, 'sites'));
        $this->assertSame(5, app(\App\Services\QuotaManager::class)->usage($user, 'managed_sites'));
    }
}
