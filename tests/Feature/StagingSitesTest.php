<?php

namespace Tests\Feature;

use App\Actions\Sites\CreateStagingSite;
use App\Enums\ServerStatus;
use App\Jobs\Deployments\DeployLaravelJob;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Jobs\Sites\SyncPlatformStagingDnsJob;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class StagingSitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_routes_are_closed_when_disabled(): void
    {
        [, $site] = $this->productionSite();

        $this->actingAs($site->user)->post(route('sites.staging.store', $site), [
            'domain' => 'staging.example.com',
        ])->assertNotFound();
    }

    public function test_superadmin_can_enable_staging(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'platform_name' => 'Uplary',
            'staging_sites_enabled' => '1',
            'registration_enabled' => '1',
            'public_site_enabled' => '1',
            'dns_enabled' => '1',
        ])->assertRedirect();

        $this->assertTrue(app(SystemSettings::class)->stagingSitesEnabled());
    }

    public function test_customer_domain_staging_is_created_when_enabled(): void
    {
        Queue::fake();
        app(SystemSettings::class)->put('staging_sites_enabled', '1', 'boolean');
        [$user, $production] = $this->productionSite();

        $this->actingAs($user)->post(route('sites.staging.store', $production), [
            'domain' => 'staging.client.com',
            'branch' => 'staging',
        ])->assertRedirect()->assertSessionHas('status');

        $staging = Site::query()->where('environment', 'staging')->firstOrFail();
        $this->assertSame('staging.client.com', $staging->domain);
        $this->assertSame('custom', $staging->domain_source);
        $this->assertSame($production->id, $staging->production_site_id);
        $this->assertSame('staging', $staging->branch);
        $this->assertSame('staging', $staging->environmentVariables()->where('key', 'APP_ENV')->value('value'));
        $this->assertStringContainsString('192.0.2.10', session('status'));
        Queue::assertPushedOn('provisioning', ConfigureSiteJob::class);
        Queue::assertNotPushed(SyncPlatformStagingDnsJob::class);
    }

    public function test_platform_subdomain_staging_is_rejected(): void
    {
        Queue::fake();
        app(SystemSettings::class)->put('staging_sites_enabled', '1', 'boolean');
        [$user, $production] = $this->productionSite();

        $this->actingAs($user)->post(route('sites.staging.store', $production), [
            'domain_source' => 'platform',
            'staging_slug' => 'acme',
        ])->assertSessionHasErrors('domain');

        Queue::assertNothingPushed();
    }

    public function test_install_ssl_script_requires_nginx_site_and_falls_back_to_webroot(): void
    {
        $script = file_get_contents(resource_path('scripts/install-ssl.sh'));

        $this->assertStringContainsString('refusing to run Certbot until the site is configured', $script);
        $this->assertStringContainsString('certbot --nginx', $script);
        $this->assertStringContainsString('certonly --webroot', $script);
        $this->assertStringContainsString('scripts/configure-site.sh', file_get_contents(app_path('Jobs/Operations/InstallSslCertificateJob.php')));
    }

    public function test_promote_copies_settings_and_queues_production_deploy(): void
    {
        Queue::fake();
        app(SystemSettings::class)->put('staging_sites_enabled', '1', 'boolean');
        [$user, $production] = $this->productionSite();
        $production->environmentVariables()->create(['key' => 'DB_CONNECTION', 'value' => 'mysql', 'is_secret' => false]);
        foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'application', 'DB_USERNAME' => 'application_user', 'DB_PASSWORD' => 'secret'] as $key => $value) {
            $production->environmentVariables()->create(['key' => $key, 'value' => $value, 'is_secret' => $key === 'DB_PASSWORD']);
        }

        $staging = app(CreateStagingSite::class)->execute($production, [
            'domain' => 'staging.client.com',
            'branch' => 'release-candidate',
        ]);
        $staging->update(['status' => 'active', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'release-candidate']);
        $production->update(['status' => 'active', 'branch' => 'main']);

        $this->actingAs($user)->post(route('sites.promote', $staging))->assertRedirect();

        $this->assertSame('release-candidate', $production->fresh()->branch);
        Queue::assertPushedOn('deployments', DeployLaravelJob::class);
    }

    public function test_tenant_cannot_create_staging_for_another_users_site(): void
    {
        Queue::fake();
        app(SystemSettings::class)->put('staging_sites_enabled', '1', 'boolean');
        [, $production] = $this->productionSite();
        $intruder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($intruder)->post(route('sites.staging.store', $production), [
            'domain' => 'stolen.example.com',
        ])->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Site}
     */
    private function productionSite(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::firstOrCreate(['slug' => 'unlimited'], [
            'name' => 'Unlimited',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1],
            'features' => [],
            'active' => true,
            'public' => true,
        ]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);
        $account = CloudAccount::create([
            'user_id' => $user->id,
            'provider' => 'digitalocean',
            'name' => 'Production',
            'credentials' => ['token' => 'secret'],
            'validated_at' => now(),
        ]);
        $key = SshKey::create([
            'user_id' => $user->id,
            'name' => 'Managed',
            'public_key' => 'ssh-ed25519 AAAA test',
            'private_key' => 'private-key',
        ]);
        $server = Server::create([
            'user_id' => $user->id,
            'cloud_account_id' => $account->id,
            'ssh_key_id' => $key->id,
            'name' => 'App',
            'hostname' => 'app-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'public_ip' => '192.0.2.10',
            'status' => ServerStatus::Ready,
        ]);
        $site = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'platform' => 'laravel',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
            'environment' => 'production',
            'webhook_secret' => Str::random(64),
        ]);

        return [$user, $site];
    }
}
