<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\CloudAccount;
use App\Models\NotificationChannel;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentsAndNotificationsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_incidents_and_notifications_require_authentication(): void
    {
        $this->markInstalled();

        $this->get('/incidents')->assertRedirect('/login');
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_sidebar_lists_notifications_after_firewall_without_incidents(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
        $sidebar = str($html)->after('<aside')->before('</aside>')->toString();

        $this->assertStringNotContainsString('>Incidents</a>', $sidebar);
        $this->assertStringNotContainsString(route('incidents.index'), $sidebar);
        $this->assertStringContainsString(route('notifications.index'), $sidebar);
        $this->assertMatchesRegularExpression('/href="[^"]*\/notifications"[^>]*>[\s\S]*?Notifications/', $sidebar);

        $firewallPos = strpos($sidebar, route('firewall.index'));
        $notificationsPos = strpos($sidebar, route('notifications.index'));
        $providersPos = strpos($sidebar, route('cloud-accounts'));

        $this->assertNotFalse($firewallPos);
        $this->assertNotFalse($notificationsPos);
        $this->assertNotFalse($providersPos);
        $this->assertTrue($firewallPos < $notificationsPos);
        $this->assertTrue($notificationsPos < $providersPos);
    }

    public function test_incidents_route_redirects_to_notifications_incidents_tab(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/incidents')
            ->assertRedirect(route('notifications.index', ['tab' => 'incidents']));

        [$owner, $server] = $this->infrastructure();
        $this->actingAs($owner)
            ->get('/incidents?server='.$server->id.'&status=all')
            ->assertRedirect(route('notifications.index', [
                'tab' => 'incidents',
                'status' => 'all',
                'server' => $server->id,
            ]));
    }

    public function test_notifications_page_shows_empty_state_and_accessible_server_incidents_only(): void
    {
        [$user, $server] = $this->infrastructure();
        [$intruder, $otherServer] = $this->infrastructure('other-01', 'Other');

        $rule = $server->alertRules()->create([
            'user_id' => $user->id,
            'name' => 'High CPU',
            'metric' => 'cpu_percent',
            'operator' => 'gte',
            'threshold' => 90,
            'consecutive_samples' => 3,
            'cooldown_minutes' => 30,
            'severity' => 'critical',
        ]);
        $server->alertIncidents()->create([
            'user_id' => $user->id,
            'alert_rule_id' => $rule->id,
            'status' => 'open',
            'severity' => 'critical',
            'metric' => 'cpu_percent',
            'value' => 95,
            'threshold' => 90,
            'message' => 'CPU critical on App',
            'started_at' => now(),
        ]);

        $otherRule = $otherServer->alertRules()->create([
            'user_id' => $intruder->id,
            'name' => 'Disk',
            'metric' => 'disk_percent',
            'operator' => 'gte',
            'threshold' => 90,
            'consecutive_samples' => 1,
            'cooldown_minutes' => 30,
            'severity' => 'warning',
        ]);
        $otherServer->alertIncidents()->create([
            'user_id' => $intruder->id,
            'alert_rule_id' => $otherRule->id,
            'status' => 'open',
            'severity' => 'warning',
            'metric' => 'disk_percent',
            'value' => 91,
            'threshold' => 90,
            'message' => 'Secret disk incident',
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/notifications?tab=incidents')
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Incidents')
            ->assertSee('CPU critical on App')
            ->assertDontSee('Secret disk incident');

        $this->actingAs($intruder)
            ->get('/notifications?tab=incidents')
            ->assertOk()
            ->assertSee('Secret disk incident')
            ->assertDontSee('CPU critical on App');

        $emptyUser = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($emptyUser)
            ->get('/notifications?tab=incidents')
            ->assertOk()
            ->assertSee('No open incidents');
    }

    public function test_notifications_page_includes_site_monitor_incidents(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->siteOn($user, $server);

        $site->monitorIncidents()->create([
            'user_id' => $user->id,
            'type' => 'site_down',
            'status' => 'open',
            'message' => 'example.test is down',
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/notifications?tab=incidents')
            ->assertOk()
            ->assertSee('example.test is down')
            ->assertSee('example.test');
    }

    public function test_stranger_cannot_filter_incidents_by_another_users_server(): void
    {
        [$user, $server] = $this->infrastructure();
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->get('/notifications?tab=incidents&server='.$server->id)
            ->assertNotFound();
    }

    public function test_notifications_page_empty_state_and_recipient_create_from_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/notifications?tab=email')
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Email recipients')
            ->assertSee('No recipients yet')
            ->assertSee($user->email);

        $this->actingAs($user)->post(route('notification-channels.store'), [
            'name' => 'Ops desk',
            'address' => 'ops@example.com',
            'events' => ['server_down', 'site_down'],
            '_tab' => 'email',
        ])->assertRedirect(route('notifications.index', ['tab' => 'email']))->assertSessionHas('status');

        $this->actingAs($user)
            ->get('/notifications?tab=email')
            ->assertOk()
            ->assertSee('Ops desk')
            ->assertSee('ops@example.com')
            ->assertSee('Server down')
            ->assertSee('Website down')
            ->assertDontSee('No recipients yet');

        $channel = NotificationChannel::sole();
        $this->assertSame($user->id, $channel->user_id);
        $this->assertSame(['server_down', 'site_down'], $channel->events);
    }

    public function test_server_monitoring_tab_links_out_instead_of_embedding_notification_form(): void
    {
        [$user, $server] = $this->infrastructure();
        $server->update(['monitoring_enabled' => true]);

        $this->actingAs($user)
            ->get(route('servers.manage', ['server' => $server, 'tab' => 'monitoring']))
            ->assertOk()
            ->assertSee('notifications?tab=incidents', false)
            ->assertSee('server='.$server->id, false)
            ->assertSee('notifications?tab=email', false)
            ->assertSee('View all incidents')
            ->assertSee('Manage notifications')
            ->assertDontSee('Add recipient');
    }

    /**
     * @return array{0: User, 1: Server}
     */
    private function infrastructure(string $hostname = 'app-01', string $name = 'App'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
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
            'name' => $name,
            'hostname' => $hostname,
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'public_ip' => '192.0.2.'.random_int(10, 200),
            'status' => ServerStatus::Ready,
        ]);

        return [$user, $server];
    }

    private function siteOn(User $user, Server $server): Site
    {
        $plan = Plan::create([
            'slug' => 'free',
            'name' => 'Free',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'currency' => 'USD',
            'limits' => ['servers' => 5, 'sites' => 10],
            'features' => [],
            'active' => true,
            'public' => true,
            'sort_order' => 10,
        ]);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'system',
            'status' => 'active',
        ]);

        return Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'example.test',
            'platform' => 'laravel',
            'php_version' => '8.3',
            'repository_url' => 'https://github.com/example/app',
            'branch' => 'main',
            'status' => 'active',
            'webhook_secret' => 'test-webhook-secret',
        ]);
    }
}
