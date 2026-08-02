<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Servers\ManagePhpMyAdminJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\User;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PhpMyAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_is_queued_only_once_the_server_is_ready(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure(ServerStatus::Provisioning);

        $this->actingAs($user)->post("/servers/{$server->id}/phpmyadmin")->assertStatus(422);
        Queue::assertNotPushed(ManagePhpMyAdminJob::class);

        $server->update(['status' => ServerStatus::Ready]);
        $this->actingAs($user)->post("/servers/{$server->id}/phpmyadmin")->assertSessionHas('status');

        Queue::assertPushed(ManagePhpMyAdminJob::class);
        $this->assertDatabaseHas('server_operations', ['server_id' => $server->id, 'type' => 'phpmyadmin:install']);
    }

    public function test_install_job_runs_the_script_and_marks_the_server_enabled(): void
    {
        [$user, $server] = $this->infrastructure(ServerStatus::Ready);
        $operation = $server->operations()->create(['user_id' => $user->id, 'type' => 'phpmyadmin:install', 'status' => 'pending']);
        Process::fake(['*' => Process::result(output: 'phpMyAdmin installed on port 8080', exitCode: 0)]);

        (new ManagePhpMyAdminJob($operation->id))->handle(app(SshClient::class));

        $this->assertSame('successful', $operation->fresh()->status);
        $this->assertTrue($server->fresh()->phpmyadmin_enabled);
        $this->assertSame(8081, $server->fresh()->phpmyadmin_port);
    }

    public function test_phpmyadmin_cannot_take_the_port_reverb_defaults_to(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure(ServerStatus::Ready);

        $this->actingAs($user)->post("/servers/{$server->id}/phpmyadmin", ['port' => 8080])->assertSessionHasErrors('port');

        Queue::assertNotPushed(ManagePhpMyAdminJob::class);
        $this->assertNull($server->fresh()->phpmyadmin_port);
    }

    public function test_phpmyadmin_cannot_take_a_port_an_existing_reverb_worker_uses(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure(ServerStatus::Ready);
        $site = \App\Models\Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'branch' => 'main', 'status' => 'active']);
        $site->queueWorkers()->create(['user_id' => $user->id, 'name' => 'reverb', 'type' => 'reverb', 'port' => 9001, 'processes' => 1, 'tries' => 3, 'timeout' => 90, 'memory' => 256]);

        $this->actingAs($user)->post("/servers/{$server->id}/phpmyadmin", ['port' => 9001])->assertSessionHasErrors('port');
        Queue::assertNotPushed(ManagePhpMyAdminJob::class);
    }

    public function test_default_port_skips_anything_already_taken(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure(ServerStatus::Ready);
        $site = \App\Models\Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'branch' => 'main', 'status' => 'active']);
        $site->queueWorkers()->create(['user_id' => $user->id, 'name' => 'reverb', 'type' => 'reverb', 'port' => 8081, 'processes' => 1, 'tries' => 3, 'timeout' => 90, 'memory' => 256]);

        $this->actingAs($user)->post("/servers/{$server->id}/phpmyadmin")->assertSessionHas('status');

        $this->assertSame(8082, $server->fresh()->phpmyadmin_port);
    }

    public function test_remove_job_disables_the_server_flag(): void
    {
        [$user, $server] = $this->infrastructure(ServerStatus::Ready);
        $server->update(['phpmyadmin_enabled' => true, 'phpmyadmin_port' => 8080]);
        $operation = $server->operations()->create(['user_id' => $user->id, 'type' => 'phpmyadmin:remove', 'status' => 'pending']);
        Process::fake(['*' => Process::result(output: 'phpMyAdmin removed', exitCode: 0)]);

        (new ManagePhpMyAdminJob($operation->id))->handle(app(SshClient::class));

        $this->assertSame('successful', $operation->fresh()->status);
        $this->assertFalse($server->fresh()->phpmyadmin_enabled);
    }

    public function test_customer_cannot_install_phpmyadmin_on_another_customers_server(): void
    {
        [, $server] = $this->infrastructure(ServerStatus::Ready);
        $intruder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($intruder)->post("/servers/{$server->id}/phpmyadmin")->assertForbidden();
    }

    private function infrastructure(ServerStatus $status): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => $status]);

        return [$user, $server];
    }
}
