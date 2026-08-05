<?php

namespace Tests\Feature;

use App\Actions\Deployments\StartDeployment;
use App\Enums\DeploymentStatus;
use App\Enums\ServerStatus;
use App\Events\DeploymentFinished;
use App\Jobs\Deployments\DeployLaravelJob;
use App\Jobs\Deployments\RollbackDeploymentJob;
use App\Jobs\Sites\CheckSiteQueueHealthJob;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Jobs\Sites\DeleteSiteJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use App\Notifications\DeploymentFinishedNotification;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_creation_queues_remote_configuration_and_encrypts_default_environment(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/sites', ['server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'zero_downtime' => '1'])->assertRedirect();

        $site = $user->sites()->firstOrFail();
        $this->assertSame('configuring', $site->status);
        $this->assertSame('production', $site->environmentVariables()->where('key', 'APP_ENV')->firstOrFail()->value);
        $this->assertStringNotContainsString('production', \DB::table('environment_variables')->where('site_id', $site->id)->where('key', 'APP_ENV')->value('value'));
        Queue::assertPushedOn('provisioning', ConfigureSiteJob::class);
    }

    public function test_environment_editor_replaces_variables_and_keeps_values_encrypted(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);

        $this->actingAs($user)->put("/sites/{$site->id}/environment", ['environment' => "APP_NAME=Demo\nDB_PASSWORD=super-secret\n"])->assertSessionHas('status');

        $this->assertDatabaseMissing('environment_variables', ['site_id' => $site->id, 'key' => 'APP_ENV']);
        $this->assertSame('super-secret', $site->environmentVariables()->where('key', 'DB_PASSWORD')->firstOrFail()->value);
        $this->assertStringNotContainsString('super-secret', \DB::table('environment_variables')->where('site_id', $site->id)->where('key', 'DB_PASSWORD')->value('value'));
    }

    public function test_a_site_without_a_database_cannot_be_deployed(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);
        // Exactly the state Uplary leaves a new site in: cache, queue, session, and Redis
        // variables, but nothing describing a database.
        $site->environmentVariables()->where('key', 'DB_CONNECTION')->delete();

        $this->actingAs($user)->post("/sites/{$site->id}/deployments")->assertSessionHasErrors('deployment');

        $this->assertSame(0, $site->deployments()->count());
        Queue::assertNotPushed(DeployLaravelJob::class);
    }

    public function test_manual_deploy_creates_one_queued_deployment(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);

        $this->actingAs($user)->post("/sites/{$site->id}/deployments")->assertRedirect();

        $this->assertDatabaseHas('deployments', ['site_id' => $site->id, 'status' => 'pending', 'trigger' => 'manual']);
        Queue::assertPushedOn('deployments', DeployLaravelJob::class);
    }

    public function test_deployment_job_records_release_and_success(): void
    {
        Process::fake(['*ssh*' => Process::result(output: "Build complete\n", exitCode: 0)]);
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);
        $site->environmentVariables()->create(['key' => 'APP_ENV', 'value' => 'production', 'is_secret' => false]);
        $deployment = app(StartDeployment::class)->execute($site, $user);

        (new DeployLaravelJob($deployment->id))->handle(app(SshClient::class));

        $deployment->refresh();
        $this->assertSame(DeploymentStatus::Successful, $deployment->status);
        $this->assertNotNull($deployment->release);
        $this->assertSame(100, $deployment->progress);
        $this->assertDatabaseHas('deployment_logs', ['deployment_id' => $deployment->id, 'level' => 'info']);
        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'bash -s'));
    }

    public function test_application_key_is_generated_once_and_never_rotates_between_deployments(): void
    {
        Process::fake(['*ssh*' => Process::result(output: "Build complete\n", exitCode: 0)]);
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);
        $site->environmentVariables()->create(['key' => 'APP_KEY', 'value' => '', 'is_secret' => true]);

        (new DeployLaravelJob(app(StartDeployment::class)->execute($site, $user)->id))->handle(app(SshClient::class));
        $first = $site->environmentVariables()->where('key', 'APP_KEY')->value('value');

        $this->assertStringStartsWith('base64:', $first);
        $this->assertSame(32, strlen(base64_decode(substr($first, 7))));
        $this->assertTrue($site->environmentVariables()->where('key', 'APP_KEY')->value('is_secret'));

        (new DeployLaravelJob(app(StartDeployment::class)->execute($site, $user)->id))->handle(app(SshClient::class));

        $this->assertSame($first, $site->environmentVariables()->where('key', 'APP_KEY')->value('value'));
    }

    public function test_signed_webhook_queues_matching_branch_and_rejects_invalid_signature(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server, ['auto_deploy' => true, 'webhook_secret' => 'webhook-secret']);
        $body = json_encode(['ref' => 'refs/heads/main', 'after' => str_repeat('a', 40), 'head_commit' => ['message' => 'Ship it']]);

        $this->call('POST', "/webhooks/sites/{$site->id}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid'], $body)->assertForbidden();
        $signature = 'sha256='.hash_hmac('sha256', $body, 'webhook-secret');
        $this->call('POST', "/webhooks/sites/{$site->id}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature], $body)->assertAccepted()->assertJsonStructure(['deployment_id']);

        $this->assertDatabaseHas('deployments', ['site_id' => $site->id, 'trigger' => 'webhook', 'commit_hash' => str_repeat('a', 40)]);
        Queue::assertPushed(DeployLaravelJob::class);
    }

    public function test_successful_release_can_be_queued_for_rollback(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);
        $target = $site->deployments()->create(['user_id' => $user->id, 'status' => DeploymentStatus::Successful, 'trigger' => 'manual', 'release' => '20260801120000-release', 'finished_at' => now()]);

        $this->actingAs($user)->post("/sites/{$site->id}/rollbacks/{$target->id}")->assertRedirect();

        $this->assertDatabaseHas('deployments', ['site_id' => $site->id, 'trigger' => 'rollback', 'release' => '20260801120000-release', 'status' => 'pending']);
        Queue::assertPushedOn('deployments', RollbackDeploymentJob::class);
    }

    public function test_finished_event_notifies_site_owner(): void
    {
        Notification::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);
        $deployment = $site->deployments()->create(['user_id' => $user->id, 'status' => DeploymentStatus::Successful, 'trigger' => 'manual', 'release' => 'release-one']);

        DeploymentFinished::dispatch($deployment->load('site.user'));

        Notification::assertSentTo($user, DeploymentFinishedNotification::class);
    }

    public function test_customer_cannot_view_another_customers_site(): void
    {
        [$owner, $server] = $this->infrastructure();
        $site = $this->site($owner, $server);
        $intruder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($intruder)->get("/sites/{$site->id}")->assertForbidden();
    }

    public function test_owner_can_edit_repository_url_and_branch(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);

        $this->actingAs($user)->patch("/sites/{$site->id}", [
            'repository_url' => 'https://github.com/acme/renamed-app.git',
            'branch' => 'develop',
            'php_version' => '8.4',
        ])->assertSessionHas('status');

        $site->refresh();
        $this->assertSame('https://github.com/acme/renamed-app.git', $site->repository_url);
        $this->assertSame('develop', $site->branch);
    }

    public function test_repository_url_must_be_a_valid_git_or_https_reference(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);

        $this->actingAs($user)->patch("/sites/{$site->id}", [
            'repository_url' => 'not-a-url',
            'branch' => 'main',
            'php_version' => '8.4',
        ])->assertSessionHasErrors('repository_url');

        $this->assertSame('https://github.com/acme/app.git', $site->fresh()->repository_url);
    }

    public function test_queue_panel_renders_worker_state_and_failed_job_count(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server, [
            'queue_failed_count' => 4,
            'queue_checked_at' => now(),
            'managed_packages' => ['laravel/horizon'],
            'installed_packages' => ['laravel/horizon' => 'v5.48.1'],
            'horizon_admin_emails' => ['admin@example.com'],
        ]);
        $site->queueWorkers()->create(['user_id' => $user->id, 'name' => 'reverb', 'type' => 'reverb', 'port' => 6001, 'processes' => 1, 'tries' => 3, 'timeout' => 90, 'memory' => 256, 'runtime_status' => 'FATAL', 'runtime_checked_at' => now()]);

        $this->actingAs($user)->get("/sites/{$site->id}")->assertOk()
            ->assertSee('Queue &amp; Reverb', false)
            ->assertSee('4 failed')
            ->assertSee('FATAL')
            ->assertSee('v5.48.1 installed')
            ->assertSee('Kept on every deploy')
            ->assertSee('not detected')
            ->assertSee('Horizon dashboard access')
            ->assertSee('admin@example.com');
    }

    public function test_queue_health_check_is_queued_and_records_the_failed_count(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);

        $this->actingAs($user)->post("/sites/{$site->id}/queue-health")->assertSessionHas('status');
        Queue::assertPushedOn('operations', CheckSiteQueueHealthJob::class);

        Process::fake(['*' => Process::result(output: '7', exitCode: 0)]);
        (new CheckSiteQueueHealthJob($site->id))->handle(app(SshClient::class));

        $this->assertSame(7, $site->fresh()->queue_failed_count);
        $this->assertNotNull($site->fresh()->queue_checked_at);
    }

    public function test_owner_can_delete_a_site_and_remote_cleanup_is_queued(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);

        $this->actingAs($user)->delete("/sites/{$site->id}", ['confirmation' => $site->domain])
            ->assertRedirect('/sites')->assertSessionHas('status');

        $this->assertSoftDeleted('sites', ['id' => $site->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site.deleted', 'auditable_id' => $site->id]);
        Queue::assertPushedOn('provisioning', DeleteSiteJob::class);
    }

    public function test_site_deletion_requires_domain_confirmation(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);

        $this->actingAs($user)->delete("/sites/{$site->id}", ['confirmation' => 'wrong-domain'])
            ->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted('sites', ['id' => $site->id]);
    }

    public function test_site_cannot_be_deleted_while_a_deployment_is_in_progress(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);
        $site->deployments()->create(['user_id' => $user->id, 'status' => DeploymentStatus::Running, 'trigger' => 'manual']);

        $this->actingAs($user)->delete("/sites/{$site->id}", ['confirmation' => $site->domain])
            ->assertSessionHasErrors('site');
        $this->assertNotSoftDeleted('sites', ['id' => $site->id]);
    }

    public function test_delete_site_job_removes_remote_configuration_and_files(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->site($user, $server);
        $site->delete();
        Process::fake(['*' => Process::result(output: 'Site app.example.com removed', exitCode: 0)]);

        (new DeleteSiteJob($site->id))->handle(app(SshClient::class));

        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'ssh'));
    }

    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);

        return [$user, $server];
    }

    private function site(User $user, Server $server, array $attributes = []): Site
    {
        $site = Site::create([...['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => 'active', 'auto_deploy' => false, 'zero_downtime' => true, 'webhook_secret' => Str::random(64)], ...$attributes]);
        // A site is only deployable once it has a database connection, so the fixture for a
        // deployable site carries one. test_a_site_without_a_database_cannot_be_deployed
        // covers the case where it does not.
        $site->environmentVariables()->create(['key' => 'DB_CONNECTION', 'value' => 'mysql', 'is_secret' => false]);

        return $site;
    }
}
