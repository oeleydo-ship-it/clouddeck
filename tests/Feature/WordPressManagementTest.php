<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Sites\BackupWordPressSiteJob;
use App\Jobs\Sites\RefreshWordPressInventoryJob;
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
use Illuminate\Support\Facades\Http;
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
        // A page render reaches the wordpress.org directory and can queue an inventory read
        // over SSH. Tests that set their own directory response stub it before calling this,
        // and the first matching stub wins, so this only covers the ones that do not care.
        Http::fake(['api.wordpress.org/*' => Http::response(['themes' => [], 'plugins' => []])]);
        Queue::fake();

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

    public function test_installed_plugins_and_themes_are_rendered_with_their_state(): void
    {
        [$user, $site] = $this->installedSite();
        Http::fake(['api.wordpress.org/*' => Http::response(['themes' => []])]);
        $site->update(['wordpress_inventory' => [
            'plugin' => [
                ['name' => 'wordfence', 'title' => 'Wordfence Security', 'status' => 'active', 'version' => '7.11.0', 'update' => 'available'],
                ['name' => 'akismet', 'title' => 'Akismet', 'status' => 'inactive', 'version' => '5.3', 'update' => 'none'],
            ],
            'theme' => [['name' => 'twentytwentyfour', 'title' => 'Twenty Twenty-Four', 'status' => 'active', 'version' => '1.0', 'update' => 'none']],
        ], 'wordpress_inventory_at' => now()]);

        $response = $this->actingAs($user)->get("/sites/{$site->id}")->assertOk();

        $response->assertSee('Wordfence Security')->assertSee('Akismet')->assertSee('Twenty Twenty-Four');
        $response->assertSee('Update available')->assertSee('Active')->assertSee('7.11.0');
    }

    public function test_the_list_is_read_from_the_site_and_stored(): void
    {
        $payload = json_encode([['name' => 'akismet', 'title' => 'Akismet', 'status' => 'inactive', 'version' => '5.3', 'update' => 'none']]);
        Process::fake(['*' => Process::result(output: $payload, exitCode: 0)]);
        [$user, $site] = $this->installedSite();

        (new RefreshWordPressInventoryJob($site->id))->handle(app(SshClient::class));

        $site->refresh();
        $this->assertSame('akismet', $site->wordpressInventory('plugin')[0]['name']);
        $this->assertSame('akismet', $site->wordpressInventory('theme')[0]['name']);
        $this->assertNotNull($site->wordpress_inventory_at);
    }

    public function test_one_target_failing_does_not_throw_away_the_other(): void
    {
        // wp theme list rejecting a field it does not have used to abort the job before
        // anything was saved, so a perfectly good plugin list was lost with it.
        // The script goes over stdin rather than the command line, so the theme read is
        // recognised by what it was handed.
        Process::fake(['*' => fn ($process) => preg_match('/TARGET=\S*theme/', (string) $process->input) === 1
            ? Process::result(output: '', errorOutput: 'Error: Invalid field', exitCode: 1)
            : Process::result(output: json_encode([['name' => 'akismet', 'status' => 'inactive']]), exitCode: 0),
        ]);
        [$user, $site] = $this->installedSite();

        (new RefreshWordPressInventoryJob($site->id))->handle(app(SshClient::class));

        $site->refresh();
        $this->assertSame('akismet', $site->wordpressInventory('plugin')[0]['name']);
        $this->assertSame([], $site->wordpressInventory('theme'));
        // And the page must be able to say why rather than reading forever.
        $this->assertNotNull($site->wordpress_inventory_at);
        $this->assertNotNull($site->wordpress_inventory_error);

        $this->actingAs($user)->get("/sites/{$site->id}")->assertOk()->assertSee('The last read failed');
    }

    public function test_a_successful_read_clears_an_earlier_failure(): void
    {
        Process::fake(['*' => Process::result(output: json_encode([['name' => 'akismet', 'status' => 'inactive']]), exitCode: 0)]);
        [$user, $site] = $this->installedSite();
        $site->update(['wordpress_inventory_error' => 'Error: Invalid field']);

        (new RefreshWordPressInventoryJob($site->id))->handle(app(SshClient::class));

        $this->assertNull($site->fresh()->wordpress_inventory_error);
    }

    public function test_the_list_is_read_without_naming_fields_wp_cli_may_reject(): void
    {
        $this->assertStringNotContainsString('--fields=', file_get_contents(resource_path('scripts/wp-cli.sh')));
    }

    public function test_warnings_printed_before_the_json_do_not_break_the_list(): void
    {
        // WP-CLI prints PHP notices ahead of its output often enough that assuming the
        // whole response parses would leave the list permanently empty.
        $payload = 'PHP Warning: something noisy
'.json_encode([['name' => 'akismet', 'title' => 'Akismet', 'status' => 'inactive']]);
        Process::fake(['*' => Process::result(output: $payload, exitCode: 0)]);
        [$user, $site] = $this->installedSite();

        (new RefreshWordPressInventoryJob($site->id))->handle(app(SshClient::class));

        $this->assertSame('akismet', $site->fresh()->wordpressInventory('plugin')[0]['name']);
    }

    public function test_available_themes_are_offered_for_installing_without_knowing_a_slug(): void
    {
        Http::fake(['api.wordpress.org/*' => Http::response(['themes' => [
            ['slug' => 'astra', 'name' => 'Astra', 'author' => ['display_name' => 'Brainstorm Force'], 'rating' => 98, 'active_installs' => 1000000, 'screenshot_url' => '//ts.w.org/astra.png'],
        ]])]);
        [$user, $site] = $this->installedSite();

        $this->actingAs($user)->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('Browse themes')
            ->assertSee('Astra')
            ->assertSee('Brainstorm Force')
            ->assertSee('1,000,000+ installs');
    }

    public function test_a_directory_name_is_escaped_once_rather_than_twice(): void
    {
        // wordpress.org returns names already HTML-encoded, and Blade encodes again, so the
        // entity itself was reaching the page: "Elementor &#8211; more than just a...".
        Http::fake(['api.wordpress.org/*' => Http::response(['plugins' => [
            ['slug' => 'elementor', 'name' => 'Elementor Website Builder &#8211; more than just a builder', 'author' => 'Elementor.com'],
        ]])]);
        [$user, $site] = $this->installedSite();

        $this->actingAs($user)->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('Elementor Website Builder – more than just a builder')
            ->assertDontSee('&#8211;', false);
    }

    public function test_a_site_without_its_own_server_block_is_configured_before_deploying(): void
    {
        // Without a block of its own, Nginx answers for the domain with whichever site it
        // lists first, so the deployment reports success while the domain serves another
        // application entirely.
        $configure = file_get_contents(resource_path('scripts/configure-site.sh'));

        $this->assertStringContainsString('NGINX_SITE="/etc/nginx/sites-available/${DOMAIN}"', $configure);
        $this->assertStringContainsString('write_http_vhost', $configure);
        // The link is made every time: the block existing is not the same as it being served.
        $this->assertStringContainsString('ln -sfn "${NGINX_SITE}" "/etc/nginx/sites-enabled/${DOMAIN}"', $configure);
        $this->assertStringContainsString('rm -f /etc/nginx/sites-enabled/default', $configure);
        $this->assertStringContainsString('acme-challenge', $configure);

        foreach (['DeployWordPressJob', 'DeployLaravelJob'] as $job) {
            $source = file_get_contents(app_path("Jobs/Deployments/{$job}.php"));
            $this->assertStringContainsString('scripts/configure-site.sh', $source, $job);
            $this->assertLessThan(
                strpos($source, 'runScriptStreaming'),
                strpos($source, 'configure-site.sh'),
                $job.' must confirm the site is served before it deploys anything to it.'
            );
        }
    }

    public function test_deploying_corrects_a_document_root_that_does_not_match_the_platform(): void
    {
        // The server block is written once, at creation, so a site configured from the wrong
        // root served "File not found" indefinitely with nothing able to put it right.
        $wordpress = file_get_contents(resource_path('scripts/deploy-wordpress.sh'));
        $laravel = file_get_contents(resource_path('scripts/deploy-laravel.sh'));

        $this->assertStringContainsString('expected="${ROOT}/current"', $wordpress);
        $this->assertStringContainsString('expected="${ROOT}/current/public"', $laravel);

        foreach (['deploy-wordpress.sh' => $wordpress, 'deploy-laravel.sh' => $laravel] as $name => $script) {
            $this->assertStringContainsString('ensure_document_root', $script, $name);
            // Rewriting the whole block would discard the lines Certbot adds for TLS, and a
            // block that no longer validates must be put back rather than left serving.
            $this->assertStringContainsString('nginx -t', $script, $name);
            $this->assertStringContainsString('.clouddeck-bak', $script, $name);
            $this->assertGreaterThan(
                strpos($script, 'Switching the current release atomically'),
                strrpos($script, 'ensure_document_root'),
                $name.' must correct the root once the release it describes is actually live.'
            );
        }
    }

    public function test_the_first_visit_reads_the_list_instead_of_waiting_to_be_asked(): void
    {
        [$user, $site] = $this->installedSite();

        $this->actingAs($user)->get("/sites/{$site->id}")->assertOk();
        Queue::assertPushed(RefreshWordPressInventoryJob::class, 1);

        // Once it has been read, every later page view must stay a page view.
        $site->update(['wordpress_inventory' => ['plugin' => [], 'theme' => []], 'wordpress_inventory_at' => now()]);
        $this->actingAs($user)->get("/sites/{$site->id}")->assertOk();
        Queue::assertPushed(RefreshWordPressInventoryJob::class, 1);
    }

    public function test_plugins_can_be_searched_for_rather_than_installed_by_slug(): void
    {
        Http::fake(['api.wordpress.org/*' => Http::response(['plugins' => [
            ['slug' => 'wordfence', 'name' => 'Wordfence Security', 'author' => 'Defiant', 'active_installs' => 4000000, 'short_description' => 'Firewall and malware scan.'],
        ]])]);
        [$user, $site] = $this->installedSite();

        $this->actingAs($user)->get("/sites/{$site->id}?plugin_search=firewall")
            ->assertOk()
            ->assertSee('Browse plugins')
            ->assertSee('Wordfence Security')
            ->assertSee('Firewall and malware scan.');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/plugins/info/1.2/') && str_contains(urldecode($request->url()), 'firewall'));
    }

    public function test_the_page_still_renders_when_the_directory_cannot_be_reached(): void
    {
        Http::fake(['api.wordpress.org/*' => Http::response(status: 503)]);
        [$user, $site] = $this->installedSite();

        // An outage at wordpress.org must not take the tab down with it.
        $this->actingAs($user)->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('could not be reached');
    }

    public function test_themes_plugins_and_backups_each_get_their_own_tab_once_installed(): void
    {
        [$user, $site] = $this->installedSite();
        $site->backups()->create(['user_id' => $user->id, 'label' => '20260802-120000', 'status' => 'completed', 'size' => 1048576, 'completed_at' => now()]);

        $this->actingAs($user)->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('Installed themes')
            ->assertSee('Installed plugins')
            ->assertSee('Browse themes')
            ->assertSee('Browse plugins')
            ->assertSee('Install and activate')
            ->assertSee('Back up now')
            ->assertSee('20260802-120000')
            ->assertSee('1 MB');
    }
}
