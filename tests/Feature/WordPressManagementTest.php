<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Sites\BackupWordPressSiteJob;
use App\Jobs\Sites\RestoreWordPressSiteJob;
use App\Jobs\Sites\RunWordPressCommandJob;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SiteBackup;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Site} */
    private function installedSite(bool $installed = true): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::firstOrCreate(['slug' => 'unlimited'], ['name' => 'Unlimited', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1], 'features' => [], 'active' => true, 'public' => true]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'blog.example.com', 'platform' => 'wordpress', 'php_version' => '8.4', 'status' => 'active', 'webhook_secret' => Str::random(64), 'wordpress_installed_at' => $installed ? now() : null]);

        return [$user, $site];
    }

    public function test_a_plugin_is_installed_through_wp_cli_so_it_is_actually_activated(): void
    {
        Queue::fake();
        [$user, $site] = $this->installedSite();

        $this->actingAs($user)->post(route('wordpress.manage', $site), ['target' => 'plugin', 'action' => 'install', 'slug' => 'wordfence'])
            ->assertSessionHas('status');

        $this->assertSame('wp plugin install wordfence', $site->terminalCommands()->sole()->command);
        Queue::assertPushedOn('operations', RunWordPressCommandJob::class);
    }

    public function test_a_theme_can_be_installed_and_managed_too(): void
    {
        Queue::fake();
        [$user, $site] = $this->installedSite();

        $this->actingAs($user)->post(route('wordpress.manage', $site), ['target' => 'theme', 'action' => 'install', 'slug' => 'twentytwentyfour'])->assertSessionHas('status');
        $this->actingAs($user)->post(route('wordpress.manage', $site), ['target' => 'theme', 'action' => 'delete', 'slug' => 'twentytwentythree'])->assertSessionHas('status');

        $this->assertSame(2, $site->terminalCommands()->count());
    }

    public function test_a_slug_that_could_reach_the_shell_is_refused(): void
    {
        Queue::fake();
        [$user, $site] = $this->installedSite();

        foreach (['wordfence; rm -rf /', '../../etc/passwd', 'Plugin Name', '$(whoami)'] as $slug) {
            $this->actingAs($user)->post(route('wordpress.manage', $site), ['target' => 'plugin', 'action' => 'install', 'slug' => $slug])
                ->assertSessionHasErrors('slug');
        }

        Queue::assertNotPushed(RunWordPressCommandJob::class);
    }

    public function test_managing_is_refused_until_the_install_is_finished(): void
    {
        Queue::fake();
        [$user, $site] = $this->installedSite(installed: false);

        // WP-CLI acts on the live install; without the tables it fails unhelpfully.
        $this->actingAs($user)->post(route('wordpress.manage', $site), ['target' => 'plugin', 'action' => 'install', 'slug' => 'wordfence'])->assertStatus(422);
        $this->actingAs($user)->post(route('wordpress.backup', $site))->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_a_backup_is_recorded_and_queued(): void
    {
        Queue::fake();
        [$user, $site] = $this->installedSite();

        $this->actingAs($user)->post(route('wordpress.backup', $site))->assertSessionHas('status');

        $backup = SiteBackup::sole();
        $this->assertSame('pending', $backup->status);
        $this->assertSame($site->id, $backup->site_id);
        Queue::assertPushedOn('operations', BackupWordPressSiteJob::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site.backup_started']);
    }

    public function test_the_backup_job_records_the_size_it_reports(): void
    {
        Process::fake(['*' => Process::result(output: "CLOUDDECK_BACKUP_BYTES=5242880\nBackup complete\n", exitCode: 0)]);
        [$user, $site] = $this->installedSite();
        $backup = $site->backups()->create(['user_id' => $user->id, 'label' => '20260802-120000', 'status' => 'pending']);

        (new BackupWordPressSiteJob($backup->id))->handle(app(SshClient::class));

        $backup->refresh();
        $this->assertSame('completed', $backup->status);
        $this->assertSame(5242880, $backup->size);
        $this->assertSame('5 MB', $backup->size_for_humans);
        $this->assertNotNull($backup->completed_at);
    }

    public function test_a_failed_backup_says_why_rather_than_looking_finished(): void
    {
        [$user, $site] = $this->installedSite();
        $backup = $site->backups()->create(['user_id' => $user->id, 'label' => '20260802-120000', 'status' => 'running']);

        (new BackupWordPressSiteJob($backup->id))->failed(new \RuntimeException('No space left on device'));

        $backup->refresh();
        $this->assertSame('failed', $backup->status);
        $this->assertStringContainsString('No space left', $backup->failure_reason);
    }

    public function test_only_a_finished_backup_can_be_restored(): void
    {
        Queue::fake();
        [$user, $site] = $this->installedSite();
        $pending = $site->backups()->create(['user_id' => $user->id, 'label' => '20260802-120000', 'status' => 'pending']);

        // Restoring a half-written archive would replace a working site with nothing.
        $this->actingAs($user)->post(route('wordpress.restore', $pending))->assertStatus(422);

        $pending->update(['status' => 'completed']);
        $this->actingAs($user)->post(route('wordpress.restore', $pending))->assertSessionHas('status');
        Queue::assertPushedOn('operations', RestoreWordPressSiteJob::class);
    }

    public function test_a_stranger_cannot_manage_or_restore_someone_elses_site(): void
    {
        Queue::fake();
        [, $site] = $this->installedSite();
        $backup = $site->backups()->create(['label' => '20260802-120000', 'status' => 'completed']);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->post(route('wordpress.manage', $site), ['target' => 'plugin', 'action' => 'install', 'slug' => 'wordfence'])->assertForbidden();
        $this->actingAs($stranger)->post(route('wordpress.backup', $site))->assertForbidden();
        $this->actingAs($stranger)->post(route('wordpress.restore', $backup))->assertForbidden();
    }

    public function test_the_tab_offers_plugins_themes_and_backups_once_installed(): void
    {
        [$user, $site] = $this->installedSite();
        $site->backups()->create(['user_id' => $user->id, 'label' => '20260802-120000', 'status' => 'completed', 'size' => 1048576, 'completed_at' => now()]);

        $this->actingAs($user)->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('Plugins &amp; backups', false)
            ->assertSee('Install and activate')
            ->assertSee('Back up now')
            ->assertSee('20260802-120000')
            ->assertSee('1 MB');
    }
}
