<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Operations\RefreshFirewallStatusJob;
use App\Jobs\Operations\SyncFirewallRuleJob;
use App\Models\CloudAccount;
use App\Models\FirewallRule;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\User;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FirewallTest extends TestCase
{
    use RefreshDatabase;

    public function test_firewall_index_requires_authentication(): void
    {
        $this->markInstalled();
        $this->get('/firewall')->assertRedirect('/login');
    }

    public function test_owner_can_view_firewall_and_create_a_rule(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $server->update([
            'firewall_synced_at' => now()->subMinutes(5),
            'firewall_status' => 'ok',
        ]);

        $this->actingAs($user)
            ->get('/firewall?server='.$server->id)
            ->assertOk()
            ->assertSee('Firewall')
            ->assertSee($server->name)
            ->assertSee('Last sync');

        $this->actingAs($user)->post('/firewall/rules', [
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => '8443',
            'from_ip' => '203.0.113.10',
            'description' => 'Custom TLS',
        ])->assertRedirect(route('firewall.index', ['server' => $server->id]));

        $rule = FirewallRule::query()->sole();
        $this->assertSame($server->id, $rule->server_id);
        $this->assertSame('allow', $rule->type);
        $this->assertSame('8443', $rule->port);
        $this->assertSame('203.0.113.10', $rule->from_ip);
        $this->assertSame('pending', $rule->status);
        Queue::assertPushedOn('operations', SyncFirewallRuleJob::class);
    }

    public function test_customer_cannot_manage_another_customers_firewall(): void
    {
        Queue::fake();
        [, $server] = $this->infrastructure();
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        $rule = FirewallRule::create([
            'user_id' => $server->user_id,
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => '22',
            'status' => 'synced',
        ]);

        $this->actingAs($intruder)->post('/firewall/rules', [
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => '443',
        ])->assertForbidden();

        $this->actingAs($intruder)->delete("/firewall/rules/{$rule->id}")->assertForbidden();
        $this->actingAs($intruder)->post("/firewall/servers/{$server->id}/sync")->assertForbidden();
        $this->actingAs($intruder)->post("/firewall/servers/{$server->id}/refresh")->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertSame(1, FirewallRule::query()->count());
    }

    public function test_invalid_ports_and_source_ips_are_rejected(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/firewall/rules', [
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => '80;rm -rf /',
        ])->assertSessionHasErrors('port');

        $this->actingAs($user)->post('/firewall/rules', [
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => '443',
            'from_ip' => 'not-an-ip',
        ])->assertSessionHasErrors('from_ip');

        $this->actingAs($user)->post('/firewall/rules', [
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => 'OpenSSH',
            'from_ip' => '10.0.0.1',
        ])->assertSessionHasErrors('from_ip');

        Queue::assertNothingPushed();
    }

    public function test_sync_and_refresh_jobs_are_dispatched(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        FirewallRule::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => '80',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post("/firewall/servers/{$server->id}/sync")->assertSessionHas('status');
        $this->actingAs($user)->post("/firewall/servers/{$server->id}/refresh")->assertSessionHas('status');

        Queue::assertPushedOn('operations', SyncFirewallRuleJob::class);
        Queue::assertPushedOn('operations', RefreshFirewallStatusJob::class);
    }

    public function test_sync_job_applies_script_and_marks_rule_synced(): void
    {
        [$user, $server] = $this->infrastructure();
        $rule = FirewallRule::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => '8443',
            'from_ip' => null,
            'status' => 'pending',
        ]);

        Process::fake(['*' => Process::result(output: "APPLIED\n", exitCode: 0)]);

        (new SyncFirewallRuleJob($rule->id))->handle(app(SshClient::class));

        $this->assertSame('synced', $rule->fresh()->status);
        $this->assertSame('ok', $server->fresh()->firewall_status);
        Process::assertRan(function ($process) use ($rule) {
            $input = (string) ($process->input ?? '');

            return str_contains(implode(' ', $process->command), 'bash -s')
                && str_contains($input, "uplary-fw-{$rule->id}")
                && str_contains($input, '8443')
                && str_contains($input, 'ACTION=')
                && str_contains($input, 'apply');
        });
    }

    public function test_sync_job_surfaces_missing_ufw(): void
    {
        [$user, $server] = $this->infrastructure();
        $rule = FirewallRule::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'type' => 'allow',
            'protocol' => 'tcp',
            'port' => '443',
            'status' => 'pending',
        ]);

        Process::fake(['*' => Process::result(output: "UFW_NOT_INSTALLED\n", exitCode: 0)]);

        try {
            (new SyncFirewallRuleJob($rule->id))->handle(app(SshClient::class));
            $this->fail('Expected RuntimeException for missing UFW');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('UFW is not installed', $exception->getMessage());
        }

        $this->assertSame('failed', $rule->fresh()->status);
        $this->assertSame('missing_ufw', $server->fresh()->firewall_status);
        $this->assertStringContainsString('UFW is not installed', (string) $server->fresh()->firewall_message);
    }

    public function test_refresh_job_stores_remote_status(): void
    {
        [, $server] = $this->infrastructure();
        Process::fake(['*' => Process::result(output: "Status: active\nTo\tAction\tFrom\n22/tcp\tALLOW\tAnywhere\n", exitCode: 0)]);

        (new RefreshFirewallStatusJob($server->id))->handle(app(SshClient::class));

        $this->assertSame('ok', $server->fresh()->firewall_status);
        $this->assertStringContainsString('Status: active', (string) $server->fresh()->firewall_remote_status);
    }

    public function test_delete_queues_removal_sync(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $rule = FirewallRule::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'type' => 'deny',
            'protocol' => 'tcp',
            'port' => '25',
            'status' => 'synced',
        ]);

        $this->actingAs($user)->delete("/firewall/rules/{$rule->id}")->assertRedirect(route('firewall.index', ['server' => $server->id]));

        $this->assertSoftDeleted($rule);
        Queue::assertPushedOn('operations', SyncFirewallRuleJob::class);
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

        return [$user, $server];
    }
}
