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

    private function fakeCatalogAndStripeCheckout(string $checkoutId = 'cs_managed_1', string $checkoutUrl = 'https://checkout.stripe.com/c/pay/cs_managed_1'): void
    {
        Http::fake(function ($request) use ($checkoutId, $checkoutUrl) {
            $url = $request->url();
            if (str_contains($url, 'api.stripe.com/v1/checkout/sessions')) {
                return Http::response(['id' => $checkoutId, 'url' => $checkoutUrl]);
            }
            if (str_contains($url, '/regions')) {
                return Http::response(['regions' => [['slug' => 'nyc3', 'name' => 'New York 3', 'available' => true]]]);
            }
            if (str_contains($url, '/sizes')) {
                return Http::response(['sizes' => [['slug' => 's-1vcpu-1gb', 'vcpus' => 1, 'memory' => 1024, 'price_monthly' => 6]]]);
            }
            if (str_contains($url, '/images')) {
                return Http::response(['images' => [['slug' => 'ubuntu-24-04-x64', 'name' => 'Ubuntu 24.04', 'distribution' => 'Ubuntu', 'created_at' => now()->toIso8601String()]]]);
            }

            return Http::response(['message' => 'unexpected '.$url], 404);
        });
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

    public function test_managed_deploy_redirects_to_stripe_checkout_before_provisioning(): void
    {
        Bus::fake();
        config(['services.stripe.secret' => 'sk_test_managed', 'services.stripe.automatic_tax' => true]);
        $this->enableManagedPlatform();
        $user = $this->entitledUser(['managed_servers' => true], ['managed_servers' => 1, 'servers' => 0]);
        $this->fakeCatalogAndStripeCheckout();

        Livewire::actingAs($user)
            ->test(ManagedServerProvisionWizard::class)
            ->set('region', 'nyc3')
            ->set('size', 's-1vcpu-1gb')
            ->set('image', 'ubuntu-24-04-x64')
            ->set('name', 'Managed App')
            ->set('hostname', 'managed-app-01')
            ->call('deploy')
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_managed_1');

        $server = Server::firstOrFail();
        $this->assertSame('managed', $server->provisioning_source);
        $this->assertNull($server->cloud_account_id);
        $this->assertTrue($server->isManaged());
        $this->assertSame(ServerStatus::AwaitingPayment, $server->status);
        $this->assertSame('unpaid', $server->metadata['payment_status']);
        $this->assertSame('cs_managed_1', $server->metadata['stripe_checkout_session_id']);
        Bus::assertNotDispatched(CreateDropletJob::class);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'checkout/sessions')
            && $request['mode'] === 'subscription'
            && (int) data_get($request, 'line_items.0.price_data.unit_amount') === 600
            && data_get($request, 'metadata.purpose') === 'managed_server'
            && data_get($request, 'metadata.server_id') === (string) $server->id);
    }

    public function test_managed_checkout_success_confirms_payment_without_webhook(): void
    {
        Bus::fake();
        config(['services.stripe.secret' => 'sk_test_managed']);
        $this->enableManagedPlatform();
        $user = $this->entitledUser(['managed_servers' => true], ['managed_servers' => 1, 'servers' => 0]);
        $server = $user->servers()->create([
            'provisioning_source' => 'managed',
            'name' => 'Managed App',
            'hostname' => 'managed-app-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'status' => ServerStatus::AwaitingPayment,
            'current_step' => 'Awaiting payment',
            'metadata' => [
                'billed_as' => 'managed',
                'customer_price_monthly' => 6.0,
                'payment_status' => 'unpaid',
                'stripe_checkout_session_id' => 'cs_return',
            ],
        ]);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_return' => Http::response([
                'id' => 'cs_return',
                'status' => 'complete',
                'payment_status' => 'paid',
                'customer' => 'cus_return',
                'subscription' => 'sub_return',
                'client_reference_id' => (string) $user->id,
                'metadata' => [
                    'purpose' => 'managed_server',
                    'user_id' => (string) $user->id,
                    'server_id' => (string) $server->id,
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('servers.managed.checkout-success', $server).'?session_id=cs_return')
            ->assertRedirect(route('servers.manage', $server));

        $server->refresh();
        $this->assertSame(ServerStatus::Pending, $server->status);
        $this->assertSame('paid', $server->metadata['payment_status']);
        $this->assertSame('sub_return', $server->metadata['stripe_subscription_id']);
        Bus::assertChained([CreateDropletJob::class, WaitForServerJob::class, BootstrapServerJob::class, FinalizeProvisioningJob::class]);
    }

    public function test_complete_payment_reuses_paid_checkout_session_instead_of_charging_again(): void
    {
        Bus::fake();
        config(['services.stripe.secret' => 'sk_test_managed']);
        $this->enableManagedPlatform();
        $user = $this->entitledUser(['managed_servers' => true], ['managed_servers' => 1, 'servers' => 0]);
        $server = $user->servers()->create([
            'provisioning_source' => 'managed',
            'name' => 'Managed App',
            'hostname' => 'managed-app-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'status' => ServerStatus::AwaitingPayment,
            'current_step' => 'Awaiting payment',
            'metadata' => [
                'billed_as' => 'managed',
                'customer_price_monthly' => 6.0,
                'payment_status' => 'unpaid',
                'stripe_checkout_session_id' => 'cs_already_paid',
            ],
        ]);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_already_paid' => Http::response([
                'id' => 'cs_already_paid',
                'status' => 'complete',
                'payment_status' => 'paid',
                'customer' => 'cus_paid',
                'subscription' => 'sub_paid',
                'metadata' => [
                    'purpose' => 'managed_server',
                    'user_id' => (string) $user->id,
                    'server_id' => (string) $server->id,
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('servers.managed.checkout', $server))
            ->assertRedirect(route('servers.manage', $server));

        $this->assertSame(ServerStatus::Pending, $server->fresh()->status);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), 'checkout/sessions'));
        Bus::assertChained([CreateDropletJob::class, WaitForServerJob::class, BootstrapServerJob::class, FinalizeProvisioningJob::class]);
    }

    public function test_managed_checkout_webhook_starts_provisioning(): void
    {
        Bus::fake();
        $this->enableManagedPlatform();
        $user = $this->entitledUser(['managed_servers' => true], ['managed_servers' => 1, 'servers' => 0]);
        $server = $user->servers()->create([
            'provisioning_source' => 'managed',
            'name' => 'Managed App',
            'hostname' => 'managed-app-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'status' => ServerStatus::AwaitingPayment,
            'current_step' => 'Awaiting payment',
            'metadata' => [
                'billed_as' => 'managed',
                'customer_price_monthly' => 6.0,
                'payment_status' => 'unpaid',
            ],
        ]);

        $event = \App\Models\BillingWebhookEvent::create([
            'provider_event_id' => 'evt_managed_checkout',
            'provider' => 'stripe',
            'type' => 'checkout.session.completed',
            'payload' => [
                'id' => 'evt_managed_checkout',
                'type' => 'checkout.session.completed',
                'data' => ['object' => [
                    'id' => 'cs_managed_paid',
                    'status' => 'complete',
                    'payment_status' => 'paid',
                    'customer' => 'cus_managed',
                    'subscription' => 'sub_managed',
                    'client_reference_id' => (string) $user->id,
                    'metadata' => [
                        'purpose' => 'managed_server',
                        'user_id' => (string) $user->id,
                        'server_id' => (string) $server->id,
                    ],
                ]],
            ],
        ]);

        (new \App\Jobs\Billing\ProcessStripeWebhookJob($event->id))->handle(app(\App\Billing\Stripe\StripeWebhookHandler::class));

        $server->refresh();
        $this->assertSame(ServerStatus::Pending, $server->status);
        $this->assertSame('paid', $server->metadata['payment_status']);
        $this->assertSame('sub_managed', $server->metadata['stripe_subscription_id']);
        $this->assertSame('cus_managed', $user->fresh()->stripe_customer_id);
        $this->assertSame(1, $user->subscriptions()->where('status', 'active')->count());
        Bus::assertChained([CreateDropletJob::class, WaitForServerJob::class, BootstrapServerJob::class, FinalizeProvisioningJob::class]);
    }

    public function test_managed_server_subscription_events_do_not_replace_plan_entitlements(): void
    {
        $user = $this->entitledUser();
        $plan = $user->subscriptions()->first()->plan;
        $server = $user->servers()->create([
            'provisioning_source' => 'managed',
            'name' => 'Managed App',
            'hostname' => 'managed-app-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'status' => ServerStatus::Ready,
            'metadata' => ['stripe_subscription_id' => 'sub_managed_keep', 'payment_status' => 'paid'],
        ]);

        $event = \App\Models\BillingWebhookEvent::create([
            'provider_event_id' => 'evt_managed_sub',
            'provider' => 'stripe',
            'type' => 'customer.subscription.updated',
            'payload' => [
                'id' => 'evt_managed_sub',
                'type' => 'customer.subscription.updated',
                'data' => ['object' => [
                    'id' => 'sub_managed_keep',
                    'customer' => 'cus_x',
                    'status' => 'active',
                    'metadata' => [
                        'purpose' => 'managed_server',
                        'user_id' => (string) $user->id,
                        'server_id' => (string) $server->id,
                    ],
                    'items' => ['data' => [['price' => ['id' => 'price_dynamic']]]],
                ]],
            ],
        ]);

        (new \App\Jobs\Billing\ProcessStripeWebhookJob($event->id))->handle(app(\App\Billing\Stripe\StripeWebhookHandler::class));

        $this->assertSame(1, $user->subscriptions()->where('status', 'active')->count());
        $this->assertSame($plan->id, $user->subscriptions()->where('status', 'active')->value('plan_id'));
        $this->assertSame('active', $server->fresh()->metadata['stripe_subscription_status']);
        $this->assertSame('paid', $server->fresh()->metadata['payment_status']);
    }

    public function test_servers_index_shows_managed_cta_when_ready_and_entitled(): void
    {
        $this->enableManagedPlatform();
        $user = $this->entitledUser();

        $this->actingAs($user)->get('/servers')
            ->assertOk()
            ->assertSee('Provision')
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
        config(['services.stripe.secret' => 'sk_test_managed']);
        $this->enableManagedPlatform();
        $settings = app(SystemSettings::class);
        $settings->put('managed_markup_percent', '50', 'string', true);
        $user = $this->entitledUser(['managed_servers' => true], ['managed_servers' => 1, 'servers' => 0]);
        $this->fakeCatalogAndStripeCheckout();

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
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_managed_1');

        $server = Server::firstOrFail();
        $this->assertEquals(6.0, $server->metadata['infra_price_monthly']);
        $this->assertEquals(9.0, $server->metadata['customer_price_monthly']);
        $this->assertSame(ServerStatus::AwaitingPayment, $server->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'checkout/sessions')
            && (int) data_get($request, 'line_items.0.price_data.unit_amount') === 900);
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

    public function test_admin_can_enable_hetzner_as_managed_cloud_provider(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->put('/admin/settings/managed-servers', [
            'managed_servers_enabled' => '1',
            'managed_cloud_provider' => 'hetzner',
            'managed_cloud_token' => 'hetzner_platform_token',
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertSame('hetzner', $settings->managedCloudProvider());
        $this->assertTrue($settings->managedServersReady());
        $this->assertSame('hetzner_platform_token', $settings->managedCloudToken());
    }

    public function test_managed_hetzner_wizard_loads_normalized_catalog(): void
    {
        $this->enableManagedPlatform('hetzner_platform_token');
        app(SystemSettings::class)->put('managed_cloud_provider', 'hetzner', 'string', true);
        $user = $this->entitledUser();

        Http::fake([
            'https://api.hetzner.cloud/v1/locations' => Http::response(['locations' => [
                ['id' => 1, 'name' => 'nbg1', 'description' => 'Nuremberg', 'country' => 'DE', 'city' => 'Nuremberg'],
            ]]),
            'https://api.hetzner.cloud/v1/server_types' => Http::response(['server_types' => [
                [
                    'id' => 1,
                    'name' => 'cx22',
                    'cores' => 2,
                    'memory' => 4.0,
                    'disk' => 40,
                    'deprecated' => false,
                    'prices' => [['location' => 'nbg1', 'price_monthly' => ['gross' => '6.49']]],
                ],
            ]]),
            'https://api.hetzner.cloud/v1/images*' => Http::response(['images' => [
                [
                    'id' => 10,
                    'name' => 'ubuntu-24.04',
                    'description' => 'Ubuntu 24.04',
                    'os_flavor' => 'ubuntu',
                    'created' => now()->toIso8601String(),
                    'status' => 'available',
                ],
            ]]),
        ]);

        Livewire::actingAs($user)
            ->test(ManagedServerProvisionWizard::class)
            ->assertSet('regions.0.slug', 'nbg1')
            ->assertSet('sizes.0.slug', 'cx22')
            ->assertSet('sizes.0.memory', 4096)
            ->assertSet('sizes.0.price_monthly', 6.49)
            ->assertSet('images.0.slug', 'ubuntu-24.04')
            ->assertSet('image', 'ubuntu-24.04');
    }

    public function test_managed_hetzner_create_and_wait_jobs_use_normalized_server_payload(): void
    {
        Bus::fake();
        $this->enableManagedPlatform('hetzner_platform_token');
        app(SystemSettings::class)->put('managed_cloud_provider', 'hetzner', 'string', true);
        $user = $this->entitledUser();
        $key = $user->sshKeys()->create([
            'name' => 'Uplary managed',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKeyMaterialHere000000000000000000000000 managed@uplary',
            'fingerprint' => 'SHA256:test',
            'private_key' => 'secret',
        ]);

        $server = $user->servers()->create([
            'provisioning_source' => 'managed',
            'ssh_key_id' => $key->id,
            'name' => 'Hetzner App',
            'hostname' => 'hetzner-app-01',
            'region' => 'nbg1',
            'size' => 'cx22',
            'image' => 'ubuntu-24.04',
            'status' => ServerStatus::Pending,
            'metadata' => [
                'platform_provider' => 'hetzner',
                'billed_as' => 'managed',
                'payment_status' => 'paid',
            ],
        ]);

        Http::fake(function ($request) use ($key) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, '/ssh_keys') && $method === 'GET') {
                return Http::response(['ssh_keys' => []]);
            }
            if (str_contains($url, '/ssh_keys') && $method === 'POST') {
                return Http::response(['ssh_key' => ['id' => 55, 'public_key' => $key->public_key]]);
            }
            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/servers') && $method === 'POST') {
                return Http::response([
                    'server' => [
                        'id' => 9001,
                        'name' => 'hetzner-app-01',
                        'status' => 'initializing',
                        'public_net' => ['ipv4' => null],
                        'server_type' => ['name' => 'cx22'],
                        'datacenter' => ['location' => ['name' => 'nbg1']],
                        'image' => ['name' => 'ubuntu-24.04'],
                        'created' => now()->toIso8601String(),
                    ],
                ]);
            }
            if (str_contains($url, '/servers/9001') && $method === 'GET') {
                return Http::response([
                    'server' => [
                        'id' => 9001,
                        'name' => 'hetzner-app-01',
                        'status' => 'running',
                        'public_net' => ['ipv4' => ['ip' => '203.0.113.10']],
                        'server_type' => ['name' => 'cx22'],
                        'datacenter' => ['location' => ['name' => 'nbg1']],
                        'image' => ['name' => 'ubuntu-24.04'],
                        'created' => now()->toIso8601String(),
                    ],
                ]);
            }

            return Http::response(['error' => 'unexpected '.$method.' '.$url], 500);
        });

        (new CreateDropletJob($server->id))->handle(app(\App\Cloud\CloudProviderManager::class));
        $server->refresh();
        $this->assertSame('9001', $server->provider_id);
        $this->assertSame('paid', $server->metadata['payment_status']);
        $this->assertSame('hetzner', $server->metadata['platform_provider']);

        (new WaitForServerJob($server->id))->handle(app(\App\Cloud\CloudProviderManager::class));
        $server->refresh();
        $this->assertSame('203.0.113.10', $server->public_ip);
        $this->assertSame(ServerStatus::Active, $server->status);
        $this->assertSame('paid', $server->metadata['payment_status']);
    }
}
