<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\ServerStatus;
use App\Jobs\Deployments\DeployLaravelJob;
use App\Jobs\Operations\ExportDatabaseJob;
use App\Jobs\RemoteManagement\ApplySiteConfigurationJob;
use App\Jobs\RemoteManagement\ExecuteFileOperationJob;
use App\Jobs\RemoteManagement\RunTerminalCommandJob;
use App\Jobs\Sites\CheckSitePackagesJob;
use App\Jobs\Sites\InstallLaravelPackageJob;
use App\Jobs\Sites\UpdateHorizonAdminsJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use App\Services\SafeTerminalCommand;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RemoteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_structured_configuration_is_encrypted_versioned_and_queued(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/configurations", [
            'type' => 'nginx',
            'client_max_body_mb' => 100,
            'static_cache' => '1',
            'include_www' => '1',
            'allow_iframe_embedding' => '1',
        ])->assertSessionHas('status');
        $configuration = $site->configurations()->firstOrFail();

        $this->assertSame(1, $configuration->version);
        $this->assertTrue($configuration->settings['static_cache']);
        $this->assertTrue($configuration->settings['allow_iframe_embedding']);
        $this->assertStringNotContainsString('client_max_body_mb', DB::table('site_configurations')->where('id', $configuration->id)->value('settings'));
        Queue::assertPushedOn('operations', ApplySiteConfigurationJob::class);
    }

    public function test_nginx_apply_accepts_iframe_embedding_setting(): void
    {
        Process::fake(['*' => Process::result(output: 'Nginx configuration applied', exitCode: 0)]);
        [$user, , $site] = $this->infrastructure();
        $configuration = $site->configurations()->create([
            'user_id' => $user->id,
            'type' => 'nginx',
            'version' => 1,
            'settings' => [
                'client_max_body_mb' => 100,
                'static_cache' => true,
                'include_www' => false,
                'allow_iframe_embedding' => true,
            ],
        ]);

        (new ApplySiteConfigurationJob($configuration->id))->handle(app(SshClient::class));

        $this->assertSame('active', $configuration->fresh()->status);
        Process::assertRan(fn () => true);
    }

    public function test_nginx_script_omits_x_frame_options_when_embedding_is_allowed(): void
    {
        $script = file_get_contents(resource_path('scripts/apply-nginx-settings.sh'));

        $this->assertStringContainsString('ALLOW_IFRAME_EMBEDDING={{ALLOW_IFRAME_EMBEDDING}}', $script);
        $this->assertStringContainsString('X-Frame-Options "SAMEORIGIN"', $script);
        $this->assertMatchesRegularExpression(
            '/ALLOW_IFRAME_EMBEDDING" == "1".*FRAME_OPTIONS_HEADER=\'\'/s',
            $script,
        );
    }

    public function test_configuration_job_applies_and_supersedes_previous_revision(): void
    {
        Process::fake(['*' => Process::result(output: 'configuration applied', exitCode: 0)]);
        [$user, , $site] = $this->infrastructure();
        $previous = $site->configurations()->create(['user_id' => $user->id, 'type' => 'php', 'version' => 1, 'settings' => $this->phpSettings(), 'status' => 'active']);
        $current = $site->configurations()->create(['user_id' => $user->id, 'type' => 'php', 'version' => 2, 'settings' => $this->phpSettings()]);

        (new ApplySiteConfigurationJob($current->id))->handle(app(SshClient::class));

        $this->assertSame('active', $current->fresh()->status);
        $this->assertSame('superseded', $previous->fresh()->status);
    }

    public function test_configuration_validation_rejects_unsafe_limits(): void
    {
        [$user, , $site] = $this->infrastructure();
        $this->actingAs($user)->post("/sites/{$site->id}/configurations", ['type' => 'php', 'memory_limit_mb' => 9000, 'upload_max_mb' => 100, 'post_max_mb' => 50, 'max_execution_time' => 60, 'max_children' => 10])->assertSessionHasErrors(['memory_limit_mb', 'post_max_mb']);
    }

    public function test_file_operations_are_site_scoped_queued_and_encrypted(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/files", ['action' => 'write', 'path' => 'shared/message.txt', 'content' => 'private contents'])->assertSessionHas('status');
        $operation = $site->fileOperations()->firstOrFail();

        $this->assertSame('private contents', $operation->payload);
        $this->assertStringNotContainsString('private contents', DB::table('file_operations')->where('id', $operation->id)->value('payload'));
        Queue::assertPushedOn('operations', ExecuteFileOperationJob::class);
        $this->actingAs($user)->post("/sites/{$site->id}/files", ['action' => 'read', 'path' => '../etc/passwd'])->assertSessionHasErrors('path');
    }

    public function test_file_job_parses_listing_and_stores_download_privately(): void
    {
        Storage::fake('local');
        [$user, , $site] = $this->infrastructure();
        $listing = base64_encode('artisan')."\tfile\t7\t100\n".base64_encode('storage')."\tdirectory\t0\t101\n";
        Process::fake(['*' => Process::result(output: $listing, exitCode: 0)]);
        $operation = $site->fileOperations()->create(['user_id' => $user->id, 'action' => 'list', 'path' => '.']);
        (new ExecuteFileOperationJob($operation->id))->handle(app(SshClient::class));

        $this->assertSame('artisan', json_decode($operation->fresh()->result, true, flags: JSON_THROW_ON_ERROR)[0]['name']);

        Process::fake(['*' => Process::result(output: base64_encode('download body'), exitCode: 0)]);
        $download = $site->fileOperations()->create(['user_id' => $user->id, 'action' => 'download', 'path' => 'shared/report.txt']);
        (new ExecuteFileOperationJob($download->id))->handle(app(SshClient::class));
        Storage::disk('local')->assertExists($download->fresh()->storage_path);
        $this->actingAs($user)->get("/file-operations/{$download->id}/download")->assertOk();
    }

    public function test_database_export_streams_to_configured_private_disk(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result(output: 'CREATE TABLE examples (id INT);', exitCode: 0)]);
        [$user, $server] = $this->infrastructure();
        $database = $server->databases()->create(['user_id' => $user->id, 'engine' => 'mysql', 'name' => 'application', 'username' => 'application_user', 'password' => 'secret', 'status' => 'ready']);
        $backup = $database->backups()->create(['user_id' => $user->id, 'type' => 'export', 'disk' => 'local']);

        (new ExportDatabaseJob($backup->id))->handle(app(SshClient::class));

        $this->assertSame('ready', $backup->fresh()->status);
        $this->assertGreaterThan(0, $backup->fresh()->size);
        Storage::disk('local')->assertExists($backup->fresh()->disk_path);
    }

    public function test_terminal_rejects_shell_syntax_and_queues_allowlisted_command(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/terminal", ['command' => 'php artisan about; id'])->assertSessionHasErrors('command');
        $this->actingAs($user)->post("/sites/{$site->id}/terminal", ['command' => 'php artisan about'])->assertSessionHas('status');
        $record = $site->terminalCommands()->firstOrFail();

        $this->assertSame('php artisan about', $record->command);
        $this->assertStringNotContainsString('artisan', DB::table('terminal_commands')->where('id', $record->id)->value('command'));
        Queue::assertPushedOn('operations', RunTerminalCommandJob::class);
    }

    public function test_horizon_and_reverb_install_is_restricted_to_the_allowlist(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/packages", ['package' => 'evil/package'])->assertSessionHasErrors('package');
        Queue::assertNotPushed(InstallLaravelPackageJob::class);

        $this->actingAs($user)->post("/sites/{$site->id}/packages", ['package' => 'laravel/horizon'])
            ->assertRedirect(route('sites.remote', ['site' => $site, 'tab' => 'terminal']))->assertSessionHas('status');

        $this->assertDatabaseHas('terminal_commands', ['site_id' => $site->id]);
        Queue::assertPushed(InstallLaravelPackageJob::class);
        $this->assertSame(['laravel/horizon'], $site->fresh()->managed_packages);
    }

    public function test_installing_reverb_writes_the_full_connection_environment(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/packages", ['package' => 'laravel/reverb'])->assertSessionHas('status');

        $env = $site->environmentVariables()->pluck('value', 'key');
        $this->assertSame('reverb', $env['BROADCAST_CONNECTION']);
        $this->assertSame('127.0.0.1', $env['REVERB_SERVER_HOST']);
        $this->assertSame($site->domain, $env['REVERB_HOST']);
        $this->assertSame('8080', $env['REVERB_SERVER_PORT']);
        $this->assertNotEmpty($env['REVERB_APP_ID']);
        $this->assertNotEmpty($env['REVERB_APP_KEY']);
        $this->assertNotEmpty($env['REVERB_APP_SECRET']);
        $this->assertSame($env['REVERB_APP_KEY'], $env['VITE_REVERB_APP_KEY']);
        $this->assertTrue($site->environmentVariables()->where('key', 'REVERB_APP_SECRET')->value('is_secret'));
    }

    public function test_reinstalling_reverb_keeps_existing_credentials(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/packages", ['package' => 'laravel/reverb']);
        $first = $site->environmentVariables()->pluck('value', 'key');

        $this->actingAs($user)->post("/sites/{$site->id}/packages", ['package' => 'laravel/reverb']);
        $second = $site->environmentVariables()->pluck('value', 'key');

        $this->assertSame($first['REVERB_APP_ID'], $second['REVERB_APP_ID']);
        $this->assertSame($first['REVERB_APP_SECRET'], $second['REVERB_APP_SECRET']);
    }

    public function test_installing_a_package_twice_does_not_duplicate_the_managed_list(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/packages", ['package' => 'laravel/horizon']);
        $this->actingAs($user)->post("/sites/{$site->id}/packages", ['package' => 'laravel/horizon']);

        $this->assertSame(['laravel/horizon'], $site->fresh()->managed_packages);
    }

    public function test_package_can_be_unmanaged_so_future_deploys_stop_reinstalling_it(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();
        $site->update(['managed_packages' => ['laravel/horizon', 'laravel/reverb']]);

        $this->actingAs($user)->delete("/sites/{$site->id}/packages", ['package' => 'laravel/horizon'])->assertSessionHas('status');

        $this->assertSame(['laravel/reverb'], $site->fresh()->managed_packages);
    }

    public function test_check_installed_packages_is_queued(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/packages/check")->assertSessionHas('status');

        Queue::assertPushed(CheckSitePackagesJob::class);
    }

    public function test_check_site_packages_job_detects_installed_versions(): void
    {
        [, , $site] = $this->infrastructure();
        Process::fake(['*' => Process::result(output: "laravel/horizon|v5.48.1\nlaravel/reverb|\n", exitCode: 0)]);

        (new CheckSitePackagesJob($site->id))->handle(app(SshClient::class));

        $this->assertSame(['laravel/horizon' => 'v5.48.1'], $site->fresh()->installed_packages);
    }

    public function test_horizon_admin_emails_are_validated_normalized_and_saved(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/horizon-admins", [
            'emails' => "Admin@Example.com, not-an-email\nadmin@example.com\nsecond@example.com",
        ])->assertSessionHas('status');

        $this->assertSame(['admin@example.com', 'second@example.com'], $site->fresh()->horizon_admin_emails);
        Queue::assertPushedOn('operations', UpdateHorizonAdminsJob::class);
    }

    public function test_update_horizon_admins_job_writes_the_admin_file(): void
    {
        [, , $site] = $this->infrastructure();
        $site->update(['horizon_admin_emails' => ['admin@example.com']]);
        Process::fake(['*' => Process::result(output: 'ok', exitCode: 0)]);

        (new UpdateHorizonAdminsJob($site->id))->handle(app(SshClient::class));

        Process::assertRan(function ($process) {
            $command = implode(' ', (array) $process->command);

            return str_contains($command, base64_encode("admin@example.com\n"))
                && str_contains($command, 'uplary-horizon-admins.txt')
                && str_contains($command, 'clouddeck-horizon-admins.txt');
        });
    }

    public function test_deploy_reinstalls_managed_packages(): void
    {
        [$user, $server, $site] = $this->infrastructure();
        $site->update(['managed_packages' => ['laravel/horizon']]);
        Process::fake(['*' => Process::result(output: 'CLOUDDECK_COMMIT='.str_repeat('a', 40), exitCode: 0)]);
        $deployment = $site->deployments()->create(['user_id' => $user->id, 'status' => DeploymentStatus::Pending, 'trigger' => 'manual']);

        (new DeployLaravelJob($deployment->id))->handle(app(SshClient::class));

        Process::assertRan(fn ($process) => (bool) preg_match('/MANAGED_PACKAGES=[\'"]laravel\/horizon[\'"]/', (string) ($process->input ?? '')));
    }

    public function test_package_install_job_runs_composer_require_and_the_artisan_install_command(): void
    {
        [, , $site] = $this->infrastructure();
        $command = $site->terminalCommands()->create(['user_id' => $site->user_id, 'command' => 'composer require laravel/horizon']);
        Process::fake(['*' => Process::result(output: 'Installed laravel/horizon into the current release.', exitCode: 0)]);

        (new InstallLaravelPackageJob($command->id, 'laravel/horizon', 'horizon:install'))->handle(app(SshClient::class));

        $this->assertSame('successful', $command->fresh()->status);
        $this->assertStringContainsString('laravel/horizon', $command->fresh()->output);
        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'bash -s'));
    }

    public function test_terminal_job_runs_as_unprivileged_site_user(): void
    {
        Process::fake(['*' => Process::result(output: 'Laravel application', exitCode: 0)]);
        [$user, , $site] = $this->infrastructure();
        $command = $site->terminalCommands()->create(['user_id' => $user->id, 'command' => 'php artisan about']);

        (new RunTerminalCommandJob($command->id))->handle(app(SshClient::class), app(SafeTerminalCommand::class));

        $this->assertSame('successful', $command->fresh()->status);
        $this->assertStringContainsString('Laravel application', $command->fresh()->output);
        Process::assertRan(function ($process): bool {
            $remoteCommand = (string) $process->command[count($process->command) - 1];

            return str_contains($remoteCommand, 'sudo -u www-data') && ! str_contains($remoteCommand, '; id');
        });
    }

    public function test_command_compiler_rejects_absolute_and_parent_paths(): void
    {
        $compiler = app(SafeTerminalCommand::class);
        foreach (['cat /etc/passwd', 'cat ../.env', 'bash script.sh'] as $command) {
            try {
                $compiler->compile($command);
                $this->fail('Unsafe command was accepted: '.$command);
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_remote_management_is_tenant_isolated(): void
    {
        [, , $site] = $this->infrastructure();
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($intruder)->get("/sites/{$site->id}/remote")->assertForbidden();
        $this->actingAs($intruder)->post("/sites/{$site->id}/terminal", ['command' => 'pwd'])->assertForbidden();
    }

    private function phpSettings(): array
    {
        return ['memory_limit_mb' => 256, 'upload_max_mb' => 100, 'post_max_mb' => 100, 'max_execution_time' => 60, 'max_children' => 10, 'display_errors' => false];
    }

    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret']]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'branch' => 'main', 'status' => 'active']);

        return [$user, $server, $site];
    }
}
