<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Servers\ReconnectServerJob;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerReconnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_removed_custom_server_can_be_reconnected_by_ip(): void
    {
        Queue::fake();
        [$user, $server] = $this->customServer();

        $server->delete();

        $this->actingAs($user)->post('/servers/custom', [
            'reconnect_server_id' => $server->id,
            'name' => 'production',
            'public_ip' => '203.0.113.10',
            'ssh_port' => 22,
            'image' => 'ubuntu-24-04-x64',
        ])->assertRedirect(route('servers.manage', $server));

        $this->assertNull($server->fresh()->deleted_at);
        $this->assertSame('203.0.113.10', $server->fresh()->public_ip);
        Queue::assertPushedOn('provisioning', ReconnectServerJob::class);
        $this->assertSame(1, Server::withTrashed()->count());
    }

    public function test_reusing_a_removed_ip_without_reconnect_is_rejected(): void
    {
        Queue::fake();
        [$user, $server] = $this->customServer();
        $server->delete();

        $this->actingAs($user)->post('/servers/custom', [
            'name' => 'production',
            'public_ip' => '203.0.113.10',
            'ssh_port' => 22,
            'image' => 'ubuntu-24-04-x64',
        ])->assertSessionHasErrors('public_ip')
            ->assertSessionHas('reconnect_server_id', $server->id);

        $this->assertNotNull($server->fresh()->deleted_at);
        Queue::assertNothingPushed();
    }

    public function test_a_removed_provider_droplet_can_be_reconnected_on_import(): void
    {
        Queue::fake();
        [$user, $account, $server] = $this->providerServer();
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-rsa public', 'private_key' => 'private']);
        $server->delete();

        Http::fake(['https://api.digitalocean.com/v2/droplets/123' => Http::response(['droplet' => $this->droplet()])]);

        $this->actingAs($user)->post("/cloud-accounts/{$account->id}/servers", [
            'provider_id' => '123',
            'ssh_key_id' => $key->id,
        ])->assertRedirect(route('servers.manage', $server));

        $this->assertNull($server->fresh()->deleted_at);
        $this->assertSame('203.0.113.10', $server->fresh()->public_ip);
        Queue::assertPushedOn('provisioning', ReconnectServerJob::class);
    }

    public function test_removed_servers_appear_on_the_custom_server_page(): void
    {
        [$user, $server] = $this->customServer();
        $server->delete();

        $this->actingAs($user)->get('/servers/custom')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Servers/Custom')
                ->has('removedServers', 1)
                ->where('removedServers.0.id', $server->id));
    }

    /** @return array{0: User, 1: Server} */
    private function customServer(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::firstOrCreate(['slug' => 'unlimited'], ['name' => 'Unlimited', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1], 'features' => [], 'active' => true, 'public' => true]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create([
            'user_id' => $user->id,
            'ssh_key_id' => $key->id,
            'name' => 'production',
            'hostname' => 'production',
            'region' => 'custom',
            'size' => 'custom',
            'image' => 'ubuntu-24-04-x64',
            'public_ip' => '203.0.113.10',
            'status' => ServerStatus::Ready,
            'provisioned_at' => now(),
            'provisioning_source' => 'custom',
        ]);

        return [$user, $server];
    }

    /** @return array{0: User, 1: CloudAccount, 2: Server} */
    private function providerServer(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::firstOrCreate(['slug' => 'unlimited'], ['name' => 'Unlimited', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1], 'features' => [], 'active' => true, 'public' => true]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);
        $account = CloudAccount::create(['user_id' => $user->id, 'name' => 'Production', 'provider' => 'digitalocean', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create([
            'user_id' => $user->id,
            'cloud_account_id' => $account->id,
            'ssh_key_id' => $key->id,
            'provider_id' => '123',
            'name' => 'application-01',
            'hostname' => 'application-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'public_ip' => '203.0.113.10',
            'status' => ServerStatus::Ready,
            'provisioned_at' => now(),
            'provisioning_source' => 'byos',
        ]);

        return [$user, $account, $server];
    }

    private function droplet(): array
    {
        return ['id' => 123, 'name' => 'application-01', 'status' => 'active', 'region' => ['slug' => 'nyc3'], 'size_slug' => 's-1vcpu-1gb', 'image' => ['slug' => 'ubuntu-24-04-x64'], 'networks' => ['v4' => [['type' => 'public', 'ip_address' => '203.0.113.10']]]];
    }
}
