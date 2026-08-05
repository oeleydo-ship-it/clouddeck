<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\ServerStatus;
use App\Jobs\Monitoring\CheckSiteDnsJob;
use App\Jobs\Monitoring\CheckSiteUptimeJob;
use App\Jobs\Monitoring\DispatchSiteChecksJob;
use App\Jobs\Sites\CheckSiteQueueHealthJob;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use App\Services\SiteDnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_sites_are_not_dispatched(): void
    {
        Queue::fake();
        [, $site] = $this->site(['site_monitoring_enabled' => false]);

        (new DispatchSiteChecksJob)->handle();

        Queue::assertNotPushed(CheckSiteUptimeJob::class);
        Queue::assertNotPushed(CheckSiteDnsJob::class);
        $this->assertSame($site->id, $site->id);
    }

    public function test_consecutive_http_failures_open_incident_and_notify(): void
    {
        Notification::fake();
        [$user, $site] = $this->site([
            'site_monitoring_enabled' => true,
            'monitor_consecutive_failures' => 3,
            'monitor_consecutive_down' => 2,
        ]);

        Http::fake(['http://app.example.com/*' => Http::response('down', 503)]);

        (new CheckSiteUptimeJob($site->id))->handle();

        $site->refresh();
        $this->assertSame('down', $site->monitor_last_status);
        $this->assertSame(3, $site->monitor_consecutive_down);
        $this->assertDatabaseHas('site_monitor_incidents', [
            'site_id' => $site->id,
            'type' => 'site_down',
            'status' => 'open',
        ]);
        Notification::assertSentTo($user, OperationalEventNotification::class, fn ($n) => $n->event === 'site_down');
    }

    public function test_recovery_resolves_incident_and_notifies(): void
    {
        Notification::fake();
        [$user, $site] = $this->site([
            'site_monitoring_enabled' => true,
            'monitor_consecutive_down' => 5,
            'monitor_last_status' => 'down',
        ]);
        $site->monitorIncidents()->create([
            'user_id' => $user->id,
            'type' => 'site_down',
            'status' => 'open',
            'message' => 'down',
            'started_at' => now()->subHour(),
            'last_notified_at' => now()->subHour(),
        ]);

        Http::fake(['http://app.example.com/*' => Http::response('ok', 200)]);

        (new CheckSiteUptimeJob($site->id))->handle();

        $this->assertSame('up', $site->fresh()->monitor_last_status);
        $this->assertSame(0, $site->fresh()->monitor_consecutive_down);
        $this->assertDatabaseHas('site_monitor_incidents', ['site_id' => $site->id, 'type' => 'site_down', 'status' => 'resolved']);
        Notification::assertSentTo($user, OperationalEventNotification::class, fn ($n) => $n->event === 'site_recovered');
    }

    public function test_cooldown_suppresses_duplicate_down_notifications(): void
    {
        Notification::fake();
        [$user, $site] = $this->site([
            'site_monitoring_enabled' => true,
            'monitor_consecutive_failures' => 1,
            'monitor_cooldown_minutes' => 30,
        ]);
        $site->monitorIncidents()->create([
            'user_id' => $user->id,
            'type' => 'site_down',
            'status' => 'open',
            'message' => 'still down',
            'started_at' => now()->subMinutes(10),
            'last_notified_at' => now()->subMinutes(5),
        ]);

        Http::fake(['http://app.example.com/*' => Http::response('down', 500)]);

        (new CheckSiteUptimeJob($site->id))->handle();

        Notification::assertNotSentTo($user, OperationalEventNotification::class);
    }

    public function test_dns_mismatch_when_a_record_differs_from_public_ip(): void
    {
        Notification::fake();
        [$user, $site] = $this->site(['site_monitoring_enabled' => true]);

        $this->mock(SiteDnsResolver::class, function ($mock): void {
            $mock->shouldReceive('resolve')->once()->with('app.example.com')->andReturn(['198.51.100.1']);
        });

        (new CheckSiteDnsJob($site->id))->handle(app(SiteDnsResolver::class));

        $this->assertSame('mismatch', $site->fresh()->dns_last_status);
        $this->assertDatabaseHas('site_monitor_incidents', [
            'site_id' => $site->id,
            'type' => 'dns_mismatch',
            'status' => 'open',
        ]);
        Notification::assertSentTo($user, OperationalEventNotification::class, fn ($n) => $n->event === 'dns_mismatch');
    }

    public function test_deploy_in_progress_skips_probe(): void
    {
        Notification::fake();
        Queue::fake();
        [, $site] = $this->site(['site_monitoring_enabled' => true]);
        $site->deployments()->create([
            'user_id' => $site->user_id,
            'status' => DeploymentStatus::Running,
            'trigger' => 'manual',
        ]);

        Http::fake();
        (new CheckSiteUptimeJob($site->id))->handle();
        (new DispatchSiteChecksJob)->handle();

        Http::assertNothingSent();
        Queue::assertNotPushed(CheckSiteUptimeJob::class);
        $this->assertNull($site->fresh()->monitor_last_checked_at);
    }

    public function test_toggle_is_tenant_authorized(): void
    {
        Queue::fake();
        [$owner, $site] = $this->site(['site_monitoring_enabled' => false]);
        $intruder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($intruder)->post("/sites/{$site->id}/monitoring")->assertForbidden();
        $this->actingAs($owner)->post("/sites/{$site->id}/monitoring", ['monitor_path' => '/up'])->assertSessionHas('status');
        $this->assertTrue($site->fresh()->site_monitoring_enabled);
        $this->assertSame('/up', $site->fresh()->monitor_path);

        $this->actingAs($intruder)->delete("/sites/{$site->id}/monitoring")->assertForbidden();
        $this->actingAs($owner)->delete("/sites/{$site->id}/monitoring")->assertSessionHas('status');
        $this->assertFalse($site->fresh()->site_monitoring_enabled);
    }

    public function test_dispatch_schedules_queue_health_for_laravel_only(): void
    {
        Queue::fake();
        $this->travelTo(now()->startOfHour()->addMinutes(15));

        [, $laravel] = $this->site(['site_monitoring_enabled' => true, 'platform' => 'laravel']);
        [, $wordpress] = $this->site([
            'site_monitoring_enabled' => true,
            'platform' => 'wordpress',
            'domain' => 'wp.example.com',
            'repository_url' => null,
        ]);

        (new DispatchSiteChecksJob)->handle();

        Queue::assertPushed(CheckSiteQueueHealthJob::class, fn ($job) => $job->siteId === $laravel->id);
        Queue::assertNotPushed(CheckSiteQueueHealthJob::class, fn ($job) => $job->siteId === $wordpress->id);
    }

    public function test_check_now_requires_monitoring_enabled(): void
    {
        [$user, $site] = $this->site(['site_monitoring_enabled' => false]);

        $this->actingAs($user)->post("/sites/{$site->id}/monitoring/check")->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Site}
     */
    private function site(array $overrides = []): array
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
            'webhook_secret' => Str::random(64),
            'monitor_path' => '/',
            'monitor_consecutive_failures' => 3,
            'monitor_cooldown_minutes' => 30,
            ...$overrides,
        ]);

        return [$user, $site];
    }
}
