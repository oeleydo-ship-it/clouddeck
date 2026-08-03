<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Monitoring\ManageMonitoringAgentJob;
use App\Jobs\Servers\FinalizeProvisioningJob;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A server nobody is watching is the one that surprises you, so a finished provision
 * arrives already reporting instead of waiting for somebody to click Enable.
 */
class MonitoringAutoEnableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_finished_provision_installs_the_agent_without_being_asked(): void
    {
        Bus::fake();
        Notification::fake();
        $server = $this->server();

        (new FinalizeProvisioningJob($server->id))->handle();

        $server->refresh();
        $this->assertTrue($server->monitoring_enabled);
        $this->assertNotNull($server->monitoring_secret);
        $this->assertSame(ServerStatus::Ready, $server->status);

        // The install job is what writes the agent, its config, and the every-minute cron.
        Bus::assertDispatched(ManageMonitoringAgentJob::class);
        $this->assertDatabaseHas('server_operations', ['server_id' => $server->id, 'type' => 'monitoring:install', 'status' => 'pending']);
    }

    public function test_a_server_already_reporting_keeps_the_secret_its_agent_is_using(): void
    {
        Bus::fake();
        Notification::fake();
        $server = $this->server();
        $server->update(['monitoring_enabled' => true, 'monitoring_secret' => 'already-configured-secret']);

        (new FinalizeProvisioningJob($server->id))->handle();

        // Rotating here would leave the agent on the box signing with a secret this side
        // no longer accepts, which reads as a server that silently went offline.
        $this->assertSame('already-configured-secret', $server->fresh()->monitoring_secret);
        Bus::assertNotDispatched(ManageMonitoringAgentJob::class);
    }

    private function server(): Server
    {
        $user = User::factory()->create();
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-rsa public', 'private_key' => 'private']);

        return Server::create([
            'user_id' => $user->id,
            'ssh_key_id' => $key->id,
            'name' => 'Production',
            'hostname' => 'production',
            'public_ip' => '203.0.113.10',
            'region' => 'ams3',
            'size' => 's-1vcpu-1gb',
            'status' => ServerStatus::Provisioning,
        ]);
    }
}
