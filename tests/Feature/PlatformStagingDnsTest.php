<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Sites\SyncPlatformStagingDnsJob;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlatformStagingDns;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformStagingDnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_dns_can_resolve_and_store_zone_id(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/user/tokens/verify' => Http::response(['success' => true, 'result' => ['status' => 'active']]),
            'https://api.cloudflare.com/client/v4/zones*' => Http::response(['success' => true, 'result' => [
                ['id' => 'zone-uplary', 'name' => 'uplary.com', 'status' => 'active'],
            ]]),
        ]);

        $settings = app(SystemSettings::class);
        $settings->put('staging_platform_domain', 'uplary.com', 'string');
        $settings->put('platform_dns_cloudflare_token', str_repeat('t', 40), 'string', false);

        $zoneId = app(PlatformStagingDns::class)->resolveAndStoreZoneId(str_repeat('t', 40));

        $this->assertSame('zone-uplary', $zoneId);
        $this->assertSame('zone-uplary', $settings->platformDnsCloudflareZoneId());
    }

    public function test_sync_creates_an_a_record_pointing_at_the_server_ip(): void
    {
        $site = $this->platformStagingSite('203.0.113.50');
        $this->connectPlatformDns();

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/zone-uplary/dns_records*' => Http::sequence()
                ->push(['success' => true, 'result' => []])
                ->push(['success' => true, 'result' => ['id' => 'rec-1', 'type' => 'A', 'name' => $site->domain, 'content' => '203.0.113.50', 'ttl' => 300, 'proxied' => false]]),
        ]);

        app(PlatformStagingDns::class)->sync($site);

        $this->assertSame('rec-1', $site->fresh()->platform_dns_record_id);
        Http::assertSent(function ($request) use ($site) {
            if ($request->method() !== 'POST') {
                return false;
            }

            $data = $request->data();

            return ($data['type'] ?? null) === 'A'
                && ($data['name'] ?? null) === $site->domain
                && ($data['content'] ?? null) === '203.0.113.50'
                && ($data['proxied'] ?? true) === false;
        });
    }

    public function test_sync_updates_an_existing_a_record_when_the_ip_changes(): void
    {
        $site = $this->platformStagingSite('203.0.113.50');
        $site->update(['platform_dns_record_id' => 'rec-1']);
        $this->connectPlatformDns();

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/zone-uplary/dns_records*' => Http::sequence()
                ->push(['success' => true, 'result' => [
                    ['id' => 'rec-1', 'type' => 'A', 'name' => $site->domain, 'content' => '198.51.100.10', 'ttl' => 300, 'proxied' => false],
                ]])
                ->push(['success' => true, 'result' => ['id' => 'rec-1', 'type' => 'A', 'name' => $site->domain, 'content' => '203.0.113.50', 'ttl' => 300, 'proxied' => false]]),
        ]);

        app(PlatformStagingDns::class)->sync($site->fresh());

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/dns_records/rec-1')
            && ($request->data()['content'] ?? null) === '203.0.113.50');
    }

    public function test_forget_deletes_the_cloudflare_record(): void
    {
        $site = $this->platformStagingSite('203.0.113.50');
        $site->update(['platform_dns_record_id' => 'rec-1']);
        $this->connectPlatformDns();

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/zone-uplary/dns_records/rec-1' => Http::response(['success' => true, 'result' => []]),
        ]);

        app(PlatformStagingDns::class)->forget($site->fresh());

        $this->assertNull($site->fresh()->platform_dns_record_id);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/dns_records/rec-1'));
    }

    public function test_delete_site_job_removes_platform_dns_before_remote_cleanup(): void
    {
        $source = file_get_contents(app_path('Jobs/Sites/DeleteSiteJob.php'));

        $this->assertStringContainsString('PlatformStagingDns', $source);
        $this->assertLessThan(
            strpos($source, 'runScript'),
            strpos($source, '$dns->forget'),
        );
    }

    public function test_changing_server_ip_requeues_platform_staging_dns_sync(): void
    {
        Queue::fake();
        $site = $this->platformStagingSite('203.0.113.50');

        $site->server->update(['public_ip' => '203.0.113.99']);

        Queue::assertPushedOn('operations', SyncPlatformStagingDnsJob::class);
    }

    private function connectPlatformDns(): void
    {
        $settings = app(SystemSettings::class);
        $settings->put('staging_platform_domain', 'uplary.com', 'string');
        $settings->put('platform_dns_cloudflare_token', str_repeat('t', 40), 'string', false);
        $settings->put('platform_dns_cloudflare_zone_id', 'zone-uplary', 'string', false);
    }

    private function platformStagingSite(string $ip): Site
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
            'public_ip' => $ip,
            'status' => ServerStatus::Ready,
        ]);
        $production = Site::create([
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

        return Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'marketing.staging.uplary.com',
            'platform' => 'laravel',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'staging',
            'status' => 'active',
            'environment' => 'staging',
            'domain_source' => 'platform',
            'staging_slug' => 'marketing',
            'production_site_id' => $production->id,
            'webhook_secret' => Str::random(64),
        ]);
    }
}
