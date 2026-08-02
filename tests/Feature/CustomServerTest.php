<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Servers\ConnectCustomServerJob;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomServerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::firstOrCreate(['slug' => 'unlimited'], ['name' => 'Unlimited', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1], 'features' => [], 'active' => true, 'public' => true]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return ['name' => 'production', 'public_ip' => '203.0.113.10', 'ssh_port' => 22, 'image' => 'ubuntu-24-04-x64', ...$overrides];
    }

    public function test_the_page_shows_a_command_carrying_the_operators_own_public_key(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->get('/servers/custom')->assertOk();

        $key = $user->sshKeys()->sole();
        $response->assertSee('authorized_keys', false)->assertSee(trim($key->public_key), false);
        // The private half is what CloudDeck authenticates with; it must never be rendered.
        $response->assertDontSee($key->private_key, false);
    }

    public function test_the_same_key_is_reused_for_a_second_server_rather_than_minting_another(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/servers/custom')->assertOk();
        $this->actingAs($user)->get('/servers/custom')->assertOk();

        // Otherwise the instructions the operator already followed would go stale.
        $this->assertSame(1, $user->sshKeys()->count());
    }

    public function test_attaching_a_server_records_its_address_and_queues_the_connection(): void
    {
        Queue::fake();
        $user = $this->user();

        $this->actingAs($user)->post('/servers/custom', $this->payload(['ssh_port' => 2222]))->assertRedirect();

        $server = Server::sole();
        $this->assertSame('203.0.113.10', $server->public_ip);
        $this->assertSame(2222, $server->ssh_port);
        $this->assertNull($server->cloud_account_id, 'A custom server belongs to no provider account.');
        $this->assertNull($server->provider_id);
        Queue::assertPushedOn('provisioning', ConnectCustomServerJob::class);
    }

    public function test_the_same_address_cannot_be_attached_twice(): void
    {
        Queue::fake();
        $user = $this->user();
        $this->actingAs($user)->post('/servers/custom', $this->payload())->assertRedirect();

        $this->actingAs($user)->post('/servers/custom', $this->payload(['name' => 'again']))->assertSessionHasErrors('public_ip');

        $this->assertSame(1, Server::count());
    }

    public function test_a_hostname_is_refused_because_dns_can_move_under_us(): void
    {
        $this->actingAs($this->user())->post('/servers/custom', $this->payload(['public_ip' => 'server.example.com']))->assertSessionHasErrors('public_ip');
    }

    public function test_the_connection_is_verified_as_root_on_ubuntu_before_anything_is_installed(): void
    {
        Queue::fake();
        $user = $this->user();
        // Posted before Process is faked: attaching a server generates the managed key with
        // a real ssh-keygen, which a blanket process fake would swallow.
        $this->actingAs($user)->post('/servers/custom', $this->payload(['ssh_port' => 2222]));
        $server = Server::sole();
        Process::fake(['*' => Process::result(output: "0\nubuntu:24.04\n", exitCode: 0)]);

        (new ConnectCustomServerJob($server->id))->handle(app(SshClient::class));

        $this->assertSame(ServerStatus::Provisioning, $server->fresh()->status);
        // The port travels with the server, so a box on 2222 is still reachable.
        Process::assertRan(fn ($process) => in_array('-p', $process->command, true));
    }

    public function test_connecting_as_a_non_root_user_stops_before_installing(): void
    {
        Queue::fake();
        $user = $this->user();
        $this->actingAs($user)->post('/servers/custom', $this->payload());
        $server = Server::sole();
        Process::fake(['*' => Process::result(output: "1000\nubuntu:24.04\n", exitCode: 0)]);

        $this->expectExceptionMessage('not as root');
        (new ConnectCustomServerJob($server->id))->handle(app(SshClient::class));
    }

    public function test_a_server_that_is_not_ubuntu_is_refused(): void
    {
        Queue::fake();
        $user = $this->user();
        $this->actingAs($user)->post('/servers/custom', $this->payload());
        $server = Server::sole();
        Process::fake(['*' => Process::result(output: "0\ndebian:12\n", exitCode: 0)]);

        $this->expectExceptionMessage('not running Ubuntu');
        (new ConnectCustomServerJob($server->id))->handle(app(SshClient::class));
    }

    public function test_an_ip_provider_asks_for_an_address_and_hands_over_to_the_ssh_step(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->post('/cloud-accounts', [
            'name' => 'Hetzner main', 'provider' => 'hetzner', 'public_ip' => '203.0.113.20', 'ssh_port' => 2222,
        ])->assertSessionHasNoErrors();

        $account = $user->cloudAccounts()->sole();
        $response->assertRedirect(route('servers.custom', [
            'cloud_account' => $account->id, 'public_ip' => '203.0.113.20', 'ssh_port' => 2222,
        ]));
        // No token was asked for, so none should have been invented.
        $this->assertSame([], $account->credentials);
    }

    public function test_an_ip_provider_will_not_accept_a_missing_or_malformed_address(): void
    {
        $this->actingAs($this->user())->post('/cloud-accounts', ['name' => 'Hetzner', 'provider' => 'hetzner'])
            ->assertSessionHasErrors('public_ip');

        $this->actingAs($this->user())->post('/cloud-accounts', ['name' => 'Hetzner', 'provider' => 'hetzner', 'public_ip' => 'not-an-ip', 'ssh_port' => 22])
            ->assertSessionHasErrors('public_ip');
    }

    public function test_an_api_provider_still_demands_a_token_rather_than_an_address(): void
    {
        $this->actingAs($this->user())->post('/cloud-accounts', ['name' => 'DO', 'provider' => 'digitalocean', 'public_ip' => '203.0.113.30'])
            ->assertSessionHasErrors('token');
    }

    public function test_the_ssh_step_carries_the_address_over_and_files_the_server_under_the_account(): void
    {
        Queue::fake();
        $user = $this->user();
        $this->actingAs($user)->post('/cloud-accounts', ['name' => 'Hetzner main', 'provider' => 'hetzner', 'public_ip' => '203.0.113.20', 'ssh_port' => 2222]);
        $account = $user->cloudAccounts()->sole();

        $this->actingAs($user)->get(route('servers.custom', ['cloud_account' => $account->id, 'public_ip' => '203.0.113.20', 'ssh_port' => 2222]))
            ->assertOk()
            ->assertSee('203.0.113.20', false)
            ->assertSee('Hetzner main');

        $this->actingAs($user)->post('/servers/custom', $this->payload([
            'public_ip' => '203.0.113.20', 'ssh_port' => 2222, 'cloud_account_id' => $account->id,
        ]))->assertRedirect();

        $this->assertSame($account->id, Server::sole()->cloud_account_id);
    }

    public function test_a_server_cannot_be_filed_under_someone_elses_provider_connection(): void
    {
        $stranger = $this->user();
        $this->actingAs($stranger)->post('/cloud-accounts', ['name' => 'Theirs', 'provider' => 'hetzner', 'public_ip' => '203.0.113.40', 'ssh_port' => 22]);
        $theirAccount = $stranger->cloudAccounts()->sole();

        $this->actingAs($this->user())->post('/servers/custom', $this->payload(['cloud_account_id' => $theirAccount->id]))
            ->assertSessionHasErrors('cloud_account_id');
    }

    public function test_an_unknown_provider_is_still_refused(): void
    {
        $this->actingAs($this->user())
            ->post('/cloud-accounts', ['name' => 'Nonsense', 'provider' => 'not-a-provider', 'token' => str_repeat('x', 32)])
            ->assertSessionHasErrors('provider');
    }
}
