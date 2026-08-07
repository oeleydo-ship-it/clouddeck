<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Operations\CheckQueueWorkerStatusJob;
use App\Jobs\Operations\CreateDatabaseJob;
use App\Jobs\Operations\ExportDatabaseJob;
use App\Jobs\Operations\ImportDatabaseJob;
use App\Jobs\Operations\InstallSslCertificateJob;
use App\Jobs\Operations\RunServerOperationJob;
use App\Jobs\Operations\SyncCronJob;
use App\Jobs\Operations\SyncQueueWorkerJob;
use App\Jobs\Servers\InstallPhpExtensionJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use App\Services\ReverbEnvironment;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServerOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_creation_is_queued_and_password_is_encrypted(): void
    {
        Queue::fake();
        [$user, $server, $site] = $this->infrastructure();

        $this->actingAs($user)->post("/servers/{$server->id}/databases", ['engine' => 'mysql', 'name' => 'application', 'username' => 'application_user', 'site_id' => $site->id])->assertSessionHas('database_password');
        $plain = session('database_password');
        $database = $server->databases()->firstOrFail();

        $this->assertSame($plain, $database->password);
        $this->assertStringNotContainsString($plain, DB::table('managed_databases')->where('id', $database->id)->value('password'));
        Queue::assertPushedOn('operations', CreateDatabaseJob::class);
    }

    public function test_database_job_provisions_remote_database_and_updates_site_environment(): void
    {
        Process::fake(['*' => Process::result(output: 'created', exitCode: 0)]);
        [$user, $server, $site] = $this->infrastructure();
        $database = $server->databases()->create(['user_id' => $user->id, 'site_id' => $site->id, 'engine' => 'mysql', 'name' => 'application', 'username' => 'application_user', 'password' => 'password123']);

        (new CreateDatabaseJob($database->id))->handle(app(SshClient::class));

        $this->assertSame('ready', $database->fresh()->status);
        $this->assertSame('application', $site->environmentVariables()->where('key', 'DB_DATABASE')->firstOrFail()->value);
        $this->assertSame('password123', $site->environmentVariables()->where('key', 'DB_PASSWORD')->firstOrFail()->value);
    }

    public function test_ssl_cron_worker_and_service_operations_are_dispatched_to_operations_queue(): void
    {
        Queue::fake();
        [$user, $server, $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/ssl", ['force_https' => '1', 'auto_renew' => '1'])->assertSessionHas('status');
        $this->actingAs($user)->post("/servers/{$server->id}/cron-jobs", ['name' => 'Scheduler', 'expression' => '* * * * *', 'command' => 'cd /var/www/app.example.com/current && php artisan schedule:run', 'site_id' => $site->id])->assertSessionHas('status');
        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'default-worker', 'type' => 'queue', 'connection' => 'redis', 'queue' => 'default', 'processes' => 2, 'tries' => 3, 'timeout' => 90, 'memory' => 128])->assertSessionHas('status');
        $this->actingAs($user)->post("/servers/{$server->id}/operations", ['type' => 'nginx:test'])->assertSessionHas('status');

        Queue::assertPushedOn('operations', InstallSslCertificateJob::class);
        Queue::assertPushedOn('operations', SyncCronJob::class);
        Queue::assertPushedOn('operations', SyncQueueWorkerJob::class);
        Queue::assertPushedOn('operations', RunServerOperationJob::class);
    }

    public function test_reverb_binds_to_loopback_and_publishes_on_the_sites_own_scheme(): void
    {
        [$user, $server, $site] = $this->infrastructure();

        app(ReverbEnvironment::class)->apply($site, 8080);
        $env = fn () => $site->environmentVariables()->pluck('value', 'key');

        $this->assertSame('127.0.0.1', $env()['REVERB_SERVER_HOST']);
        $this->assertSame('8080', $env()['REVERB_SERVER_PORT']);
        $this->assertSame('http', $env()['REVERB_SCHEME']);
        $this->assertSame('80', $env()['REVERB_PORT']);

        $site->sslCertificates()->create(['user_id' => $user->id, 'domains' => [$site->domain], 'status' => 'active']);
        app(ReverbEnvironment::class)->apply($site, 8080);

        $this->assertSame('https', $env()['REVERB_SCHEME']);
        $this->assertSame('443', $env()['REVERB_PORT']);
        $this->assertSame('443', $env()['VITE_REVERB_PORT']);
        $this->assertSame('8080', $env()['REVERB_SERVER_PORT']);
    }

    public function test_deleted_worker_frees_its_name_and_port_for_a_replacement(): void
    {
        Queue::fake();
        [$user, $server, $site] = $this->infrastructure();
        $payload = ['name' => 'reverb', 'type' => 'reverb', 'port' => 8080];

        $this->actingAs($user)->post("/sites/{$site->id}/workers", $payload)->assertSessionHas('status');
        $worker = $site->queueWorkers()->sole();
        $this->actingAs($user)->delete("/workers/{$worker->id}")->assertSessionHas('status');

        $this->actingAs($user)->post("/sites/{$site->id}/workers", $payload)->assertSessionHas('status');
        $this->assertSame(1, $site->queueWorkers()->count());
        $this->assertSame(8080, (int) $site->queueWorkers()->sole()->port);
    }

    public function test_live_workers_still_reject_a_duplicate_name_or_port(): void
    {
        Queue::fake();
        [$user, $server, $site] = $this->infrastructure();
        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'reverb', 'type' => 'reverb', 'port' => 8080])->assertSessionHas('status');

        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'reverb', 'type' => 'reverb', 'port' => 9090])->assertSessionHasErrors('name');
        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'reverb-two', 'type' => 'reverb', 'port' => 8080])->assertSessionHasErrors('port');
        $this->assertSame(1, $site->queueWorkers()->count());
    }

    public function test_site_cron_jobs_are_created_against_the_sites_server(): void
    {
        Queue::fake();
        [$user, $server, $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/cron-jobs", ['name' => 'Scheduler', 'expression' => '* * * * *', 'command' => 'cd /var/www/app.example.com/current && php artisan schedule:run'])->assertSessionHas('status');

        $cron = $site->cronJobs()->sole();
        $this->assertSame($server->id, $cron->server_id);
        $this->assertTrue($cron->enabled);
        Queue::assertPushedOn('operations', SyncCronJob::class);
        $this->actingAs($user)->get("/sites/{$site->id}")->assertOk()->assertSee('Scheduler');
    }

    public function test_cron_forms_expose_laravel_scheduler_presets(): void
    {
        [$user, $server, $site] = $this->infrastructure();
        $commandAttr = 'data-cron-command="cd /var/www/app.example.com/current &amp;&amp; php artisan schedule:run"';

        $this->actingAs($user)->get("/servers/{$server->id}/manage?tab=cron")
            ->assertOk()
            ->assertSee('data-cron-presets', false)
            ->assertSee('Laravel · app.example.com')
            ->assertSee($commandAttr, false);

        $this->actingAs($user)->get("/sites/{$site->id}?tab=cron")
            ->assertOk()
            ->assertSee('data-cron-presets', false)
            ->assertSee('>Laravel scheduler</button>', false)
            ->assertSee($commandAttr, false);
    }

    public function test_site_cron_jobs_cannot_be_created_by_another_user(): void
    {
        [$user, $server, $site] = $this->infrastructure();
        $this->actingAs(User::factory()->create())->post("/sites/{$site->id}/cron-jobs", ['name' => 'Scheduler', 'expression' => '* * * * *', 'command' => 'php artisan schedule:run'])->assertForbidden();
        $this->assertSame(0, $site->cronJobs()->count());
    }

    public function test_unsafe_cron_and_arbitrary_service_commands_are_rejected(): void
    {
        [$user, $server] = $this->infrastructure();
        $this->actingAs($user)->post("/servers/{$server->id}/cron-jobs", ['name' => 'Unsafe', 'expression' => '* * * * *', 'command' => "echo safe\nrm -rf /tmp/x"])->assertSessionHasErrors('command');
        $this->actingAs($user)->post("/servers/{$server->id}/operations", ['type' => 'shell:rm'])->assertSessionHasErrors('type');
    }

    public function test_maintenance_operations_are_queued_and_release_upgrade_requires_hostname(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->get("/servers/{$server->id}/manage?tab=services")
            ->assertOk()
            ->assertSee('Software hardening')
            ->assertSee('Update Ubuntu packages')
            ->assertSee('Major release upgrade');

        $this->actingAs($user)->post("/servers/{$server->id}/operations", ['type' => 'system:harden'])->assertSessionHas('status');
        $this->actingAs($user)->post("/servers/{$server->id}/operations", ['type' => 'system:update'])->assertSessionHas('status');
        $this->actingAs($user)->post("/servers/{$server->id}/operations", ['type' => 'system:release-upgrade'])->assertSessionHasErrors('confirmation');
        $this->actingAs($user)->post("/servers/{$server->id}/operations", [
            'type' => 'system:release-upgrade',
            'confirmation' => $server->hostname,
        ])->assertSessionHas('status');

        Queue::assertPushedOn('operations', RunServerOperationJob::class);
        $this->assertDatabaseHas('server_operations', ['server_id' => $server->id, 'type' => 'system:harden']);
        $this->assertDatabaseHas('server_operations', ['server_id' => $server->id, 'type' => 'system:update']);
        $this->assertDatabaseHas('server_operations', ['server_id' => $server->id, 'type' => 'system:release-upgrade']);
    }

    public function test_maintenance_operation_job_runs_the_matching_script(): void
    {
        Process::fake(['*' => Process::result(output: "CLOUDDECK_HARDEN_OK=1\n", exitCode: 0)]);
        [$user, $server] = $this->infrastructure();
        $operation = $server->operations()->create(['user_id' => $user->id, 'type' => 'system:harden', 'target' => 'system', 'status' => 'pending']);

        (new RunServerOperationJob($operation->id))->handle(app(SshClient::class));

        $this->assertSame('successful', $operation->fresh()->status);
        $this->assertStringContainsString('CLOUDDECK_HARDEN_OK=1', $operation->fresh()->output);
    }

    public function test_database_import_and_export_are_private_queued_operations(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$user, $server] = $this->infrastructure();
        $database = $server->databases()->create(['user_id' => $user->id, 'engine' => 'mysql', 'name' => 'application', 'username' => 'application_user', 'password' => 'secret', 'status' => 'ready']);

        $this->actingAs($user)->post("/databases/{$database->id}/export")->assertSessionHas('status');
        $this->actingAs($user)->post("/databases/{$database->id}/import", ['sql' => UploadedFile::fake()->createWithContent('dump.sql', 'CREATE TABLE examples (id INT);')])->assertSessionHas('status');

        Queue::assertPushedOn('operations', ExportDatabaseJob::class);
        Queue::assertPushedOn('operations', ImportDatabaseJob::class);
        $this->assertDatabaseHas('database_backups', ['managed_database_id' => $database->id, 'type' => 'export']);
        $this->assertDatabaseHas('database_backups', ['managed_database_id' => $database->id, 'type' => 'import']);
    }

    public function test_customer_cannot_manage_another_customers_server(): void
    {
        [, $server] = $this->infrastructure();
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($intruder)->get("/servers/{$server->id}/manage")->assertForbidden();
        $this->actingAs($intruder)->post("/servers/{$server->id}/operations", ['type' => 'nginx:test'])->assertForbidden();
    }

    public function test_servers_page_lists_only_accessible_servers(): void
    {
        [$user, $server] = $this->infrastructure();
        $intruder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/servers')->assertOk()
            ->assertSee($server->name)
            ->assertSee($server->public_ip)
            ->assertSee('Provision server');

        $this->actingAs($intruder)->get('/servers')->assertOk()
            ->assertDontSee($server->name)
            ->assertSee('No servers yet');
    }

    public function test_servers_page_requires_authentication(): void
    {
        $this->markInstalled();

        $this->get('/servers')->assertRedirect('/login');
    }

    public function test_server_with_sites_cannot_be_deleted(): void
    {
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->delete("/servers/{$server->id}", ['confirmation' => $server->hostname])
            ->assertSessionHasErrors('server');
        $this->assertNotSoftDeleted('servers', ['id' => $server->id]);
    }

    public function test_server_without_sites_can_be_deleted_and_destroys_its_droplet(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'provider_id' => '999', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        Http::fake(['https://api.digitalocean.com/v2/droplets/999' => Http::response([], 204)]);

        $this->actingAs($user)->delete("/servers/{$server->id}", ['confirmation' => $server->hostname])
            ->assertRedirect('/dashboard')->assertSessionHas('status');

        $this->assertSoftDeleted('servers', ['id' => $server->id]);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/droplets/999'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'server.deleted', 'auditable_id' => $server->id]);
    }

    public function test_server_can_be_deleted_when_the_droplet_is_already_gone_at_the_provider(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'provider_id' => '999', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        Http::fake(['https://api.digitalocean.com/v2/droplets/999' => Http::response(['id' => 'not_found', 'message' => 'The resource you were accessing could not be found.'], 404)]);

        $this->actingAs($user)->delete("/servers/{$server->id}", ['confirmation' => $server->hostname])
            ->assertRedirect('/dashboard')->assertSessionHas('status');

        $this->assertSoftDeleted('servers', ['id' => $server->id]);
    }

    public function test_server_deletion_still_blocks_on_a_genuine_provider_error(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'provider_id' => '999', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        Http::fake(['https://api.digitalocean.com/v2/droplets/999' => Http::response(['message' => 'server error'], 500)]);

        $this->actingAs($user)->delete("/servers/{$server->id}", ['confirmation' => $server->hostname])
            ->assertSessionHasErrors('server');

        $this->assertNotSoftDeleted('servers', ['id' => $server->id]);
    }

    public function test_server_deletion_requires_hostname_confirmation(): void
    {
        [$user, $server, $site] = $this->infrastructure();
        $site->delete();

        $this->actingAs($user)->delete("/servers/{$server->id}", ['confirmation' => 'wrong-hostname'])
            ->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted('servers', ['id' => $server->id]);
    }

    public function test_php_extension_install_is_queued_and_only_an_allowlisted_extension_is_accepted(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post("/servers/{$server->id}/php-extensions", ['extension' => 'rm -rf'])->assertSessionHasErrors('extension');
        Queue::assertNotPushed(InstallPhpExtensionJob::class);

        $this->actingAs($user)->post("/servers/{$server->id}/php-extensions", ['extension' => 'intl'])->assertSessionHas('status');
        Queue::assertPushedOn('operations', InstallPhpExtensionJob::class);
        $this->assertDatabaseHas('server_operations', ['server_id' => $server->id, 'type' => 'php:extension:intl']);
    }

    public function test_horizon_worker_can_be_created_without_queue_specific_fields(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'horizon', 'type' => 'horizon'])->assertSessionHas('status');

        $this->assertDatabaseHas('queue_workers', ['site_id' => $site->id, 'type' => 'horizon', 'processes' => 1, 'port' => null]);
        Queue::assertPushedOn('operations', SyncQueueWorkerJob::class);
    }

    public function test_reverb_worker_requires_a_port_and_rejects_collision_with_phpmyadmin(): void
    {
        Queue::fake();
        [$user, $server, $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'reverb', 'type' => 'reverb'])->assertSessionHasErrors('port');

        $server->update(['phpmyadmin_enabled' => true, 'phpmyadmin_port' => 6001]);
        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'reverb', 'type' => 'reverb', 'port' => 6001])->assertSessionHasErrors('port');

        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'reverb', 'type' => 'reverb', 'port' => 6002])->assertSessionHas('status');
        $this->assertDatabaseHas('queue_workers', ['site_id' => $site->id, 'type' => 'reverb', 'port' => 6002, 'processes' => 1]);
    }

    public function test_reverb_worker_syncs_the_port_into_the_site_environment(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'reverb', 'type' => 'reverb', 'port' => 9055])->assertSessionHas('status');

        $env = $site->environmentVariables()->pluck('value', 'key');
        $this->assertSame('9055', $env['REVERB_SERVER_PORT']);
        $this->assertSame('127.0.0.1', $env['REVERB_SERVER_HOST']);
        // Browsers reach the WebSocket through Nginx on the site's own port, never 9055.
        $this->assertSame('80', $env['REVERB_PORT']);
    }

    public function test_reverb_ports_cannot_collide_across_sites_on_the_same_server(): void
    {
        Queue::fake();
        [$user, $server, $site] = $this->infrastructure();
        $server->update(['status' => ServerStatus::Ready]);
        $second = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'second.example.com', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => 'active']);

        $this->actingAs($user)->post("/sites/{$site->id}/workers", ['name' => 'reverb', 'type' => 'reverb', 'port' => 6001])->assertSessionHas('status');
        $this->actingAs($user)->post("/sites/{$second->id}/workers", ['name' => 'reverb', 'type' => 'reverb', 'port' => 6001])->assertSessionHasErrors('port');
    }

    public function test_server_management_page_renders_with_a_checked_worker_status(): void
    {
        [$user, $server, $site] = $this->infrastructure();
        $site->queueWorkers()->create(['user_id' => $user->id, 'name' => 'horizon', 'type' => 'horizon', 'processes' => 1, 'tries' => 3, 'timeout' => 90, 'memory' => 256, 'runtime_status' => 'RUNNING', 'runtime_checked_at' => now()]);

        $this->actingAs($user)->get("/servers/{$server->id}/manage")
            ->assertOk()
            ->assertSee('RUNNING')
            ->assertSee('aria-label="Copy IP address"', false)
            ->assertSee($server->public_ip);
    }

    public function test_worker_status_check_is_queued_and_parses_supervisor_state(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();
        $worker = $site->queueWorkers()->create(['user_id' => $user->id, 'name' => 'horizon', 'type' => 'horizon', 'processes' => 1, 'tries' => 3, 'timeout' => 90, 'memory' => 256]);

        $this->actingAs($user)->post("/workers/{$worker->id}/status")->assertSessionHas('status');
        Queue::assertPushedOn('operations', CheckQueueWorkerStatusJob::class);

        Process::fake(['*' => Process::result(output: "Uplary-{$worker->id}:Uplary-{$worker->id}_00   BACKOFF   Exited too quickly", exitCode: 0)]);
        (new CheckQueueWorkerStatusJob($worker->id))->handle(app(SshClient::class));

        $this->assertSame('BACKOFF', $worker->fresh()->runtime_status);
        $this->assertNotNull($worker->fresh()->runtime_checked_at);
    }

    public function test_worker_status_check_parses_clouddeck_supervisor_program(): void
    {
        [$user, , $site] = $this->infrastructure();
        $worker = $site->queueWorkers()->create(['user_id' => $user->id, 'name' => 'default', 'type' => 'queue', 'connection' => 'redis', 'queue' => 'default', 'processes' => 1, 'tries' => 3, 'timeout' => 90, 'memory' => 256]);

        $line = "clouddeck-{$worker->id}:clouddeck-{$worker->id}_00   RUNNING   pid 4242, uptime 0:12:01";
        Process::fake(['*' => Process::result(output: $line, exitCode: 0)]);
        (new CheckQueueWorkerStatusJob($worker->id))->handle(app(SshClient::class));

        $this->assertSame('RUNNING', $worker->fresh()->runtime_status);
        $this->assertStringContainsString($line, (string) $worker->fresh()->runtime_output);
    }

    public function test_worker_status_prefers_clouddeck_program_over_legacy_uplary(): void
    {
        [, , $site] = $this->infrastructure();
        $worker = $site->queueWorkers()->create(['user_id' => $site->user_id, 'name' => 'horizon', 'type' => 'horizon', 'processes' => 1, 'tries' => 3, 'timeout' => 90, 'memory' => 256]);

        $output = "clouddeck-{$worker->id}:clouddeck-{$worker->id}_00   RUNNING   pid 10, uptime 1:00:00\n"
            ."Uplary-{$worker->id}: ERROR (no such process)";
        Process::fake(['*' => Process::result(output: $output, exitCode: 0)]);
        (new CheckQueueWorkerStatusJob($worker->id))->handle(app(SshClient::class));

        $this->assertSame('RUNNING', $worker->fresh()->runtime_status);
    }

    public function test_sync_worker_job_configures_supervisor_command_by_type(): void
    {
        [, , $site] = $this->infrastructure();
        $worker = $site->queueWorkers()->create(['user_id' => $site->user_id, 'name' => 'horizon', 'type' => 'horizon', 'processes' => 1, 'tries' => 3, 'timeout' => 90, 'memory' => 256]);
        Process::fake(['*' => Process::result(output: 'ok', exitCode: 0)]);

        (new SyncQueueWorkerJob($worker->id))->handle(app(SshClient::class));

        $this->assertSame('active', $worker->fresh()->status);
        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'bash -s'));
    }

    public function test_php_extension_job_installs_for_every_detected_php_fpm_version(): void
    {
        [$user, $server] = $this->infrastructure();
        $operation = $server->operations()->create(['user_id' => $user->id, 'type' => 'php:extension:intl', 'status' => 'pending']);
        Process::fake(['*' => Process::result(output: 'Installed php-intl for PHP version(s): 8.4', exitCode: 0)]);

        (new InstallPhpExtensionJob($operation->id))->handle(app(SshClient::class));

        $this->assertSame('successful', $operation->fresh()->status);
        $this->assertStringContainsString('8.4', $operation->fresh()->output);
    }

    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => 'active']);

        return [$user, $server, $site];
    }
}
