<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Monitoring\CheckSiteDnsJob;
use App\Jobs\Monitoring\CheckSiteUptimeJob;
use App\Jobs\Operations\CheckQueueWorkerStatusJob;
use App\Jobs\Sites\CheckSitePackagesJob;
use App\Jobs\Sites\CheckSiteQueueHealthJob;
use App\Jobs\Sites\UpdateHorizonAdminsJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActiveTabPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_show_initializes_the_requested_tab(): void
    {
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->get(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sites/Show')
                ->has('tabs.queue')
                ->has('tabs.environment'));
    }

    public function test_site_show_keeps_overview_panel_markup_and_tab_helper(): void
    {
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sites/Show')
                ->has('tabs.overview')
                ->where('site.repository_url', 'https://github.com/acme/app.git'));
    }

    public function test_server_manage_keeps_monitoring_panel_markup(): void
    {
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)
            ->get(route('servers.manage', $server))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servers/Manage')
                ->where('server.id', $server->id)
                ->where('server.name', $server->name));
    }

    public function test_server_manage_hides_operational_dashboard_until_ready(): void
    {
        [$user, $server] = $this->infrastructure();
        $server->update(['status' => ServerStatus::AwaitingPayment]);

        $this->actingAs($user)
            ->get(route('servers.manage', $server))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servers/Manage')
                ->where('server.status', 'awaiting_payment')
                ->missing('server.metrics')
                ->where('cronPresets', [])
                ->where('phpMyAdminPort', null)
                ->where('transferTeams', []));

        $server->update(['status' => ServerStatus::Ready]);

        $this->actingAs($user)
            ->get(route('servers.manage', $server))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servers/Manage')
                ->where('server.status', 'ready')
                ->has('server.metrics'));
    }

    public function test_environment_save_redirect_keeps_the_environment_tab(): void
    {
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'environment']))
            ->put(route('sites.environment', $site), [
                '_tab' => 'environment',
                'environment' => "APP_NAME=Demo\n",
            ])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'environment']))
            ->assertSessionHas('status');
    }

    public function test_horizon_access_save_redirect_keeps_the_queue_tab(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();
        $site->update(['installed_packages' => ['laravel/horizon' => 'v5.48.1']]);

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->post(route('site-horizon-admins.update', $site), [
                '_tab' => 'queue',
                'emails' => 'admin@example.com',
            ])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->assertSessionHas('status');

        Queue::assertPushed(UpdateHorizonAdminsJob::class);
    }

    public function test_queue_health_check_redirect_keeps_the_queue_tab(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->post(route('sites.queue-health', $site), ['_tab' => 'queue'])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->assertSessionHas('status');

        Queue::assertPushed(CheckSiteQueueHealthJob::class);
    }

    public function test_package_detection_refresh_redirect_keeps_the_queue_tab(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->post(route('site-packages.check', $site), ['_tab' => 'queue'])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->assertSessionHas('status');

        Queue::assertPushed(CheckSitePackagesJob::class);
    }

    public function test_ssl_and_monitoring_actions_keep_their_tabs(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'ssl']))
            ->post(route('ssl.store', $site), ['_tab' => 'ssl', 'force_https' => '1', 'auto_renew' => '1'])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'ssl']))
            ->assertSessionHas('status');

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'monitoring']))
            ->post(route('sites.monitoring.enable', $site), ['_tab' => 'monitoring', 'monitor_path' => '/'])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'monitoring']))
            ->assertSessionHas('status');

        Queue::assertPushed(CheckSiteUptimeJob::class);
        Queue::assertPushed(CheckSiteDnsJob::class);
    }

    public function test_read_log_redirect_keeps_the_logs_tab(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'logs']))
            ->post(route('site-logs.store', $site), ['source' => 'laravel', 'lines' => 200, '_tab' => 'logs'])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'logs']))
            ->assertSessionHas('status');
    }

    public function test_worker_status_check_from_the_site_keeps_the_queue_tab(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();
        $worker = $site->queueWorkers()->create([
            'user_id' => $user->id,
            'name' => 'default',
            'type' => 'queue',
            'connection' => 'redis',
            'queue' => 'default',
            'processes' => 1,
            'tries' => 3,
            'timeout' => 90,
            'memory' => 256,
        ]);

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->post(route('workers.status', $worker), ['_tab' => 'queue'])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->assertSessionHas('status');

        Queue::assertPushed(CheckQueueWorkerStatusJob::class);
    }

    public function test_server_manage_actions_keep_their_tabs(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)
            ->from(route('servers.manage', ['server' => $server, 'tab' => 'services']))
            ->post(route('server-operations.store', $server), ['_tab' => 'services', 'type' => 'nginx:test'])
            ->assertRedirect(route('servers.manage', ['server' => $server, 'tab' => 'services']))
            ->assertSessionHas('status');

        $this->actingAs($user)
            ->from(route('servers.manage', ['server' => $server, 'tab' => 'cron']))
            ->post(route('cron-jobs.store', $server), [
                '_tab' => 'cron',
                'name' => 'scheduler',
                'expression' => '* * * * *',
                'command' => 'php artisan schedule:run',
            ])
            ->assertRedirect(route('servers.manage', ['server' => $server, 'tab' => 'cron']))
            ->assertSessionHas('status');
    }

    public function test_intentional_remote_redirect_is_not_overwritten_by_site_tab(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'queue']))
            ->post(route('site-packages.store', $site), ['_tab' => 'queue', 'package' => 'laravel/horizon'])
            ->assertRedirect(route('sites.remote', ['site' => $site, 'tab' => 'terminal']))
            ->assertSessionHas('status');
    }

    public function test_back_without_tab_input_still_preserves_tab_from_referer_query(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'deploy']))
            ->patch(route('sites.update', $site), [
                'repository_url' => 'https://github.com/acme/app.git',
                'branch' => 'main',
                'php_version' => '8.4',
                'deployment_script' => null,
            ])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'deploy']))
            ->assertSessionHas('status');
    }

    private function infrastructure(): array
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
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
            'auto_deploy' => false,
            'zero_downtime' => true,
            'webhook_secret' => Str::random(64),
        ]);

        return [$user, $server, $site];
    }
}
