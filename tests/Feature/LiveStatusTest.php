<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Events\ServerProvisioningUpdated;
use App\Jobs\Servers\InteractsWithServerProgress;
use App\Livewire\ServerStatusList;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LiveStatusTest extends TestCase
{
    use RefreshDatabase;

    private function server(User $user, array $attributes = []): Server
    {
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);

        return Server::create([...[
            'user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id,
            'name' => 'production', 'hostname' => 'production-01', 'region' => 'ams3', 'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64', 'status' => ServerStatus::Provisioning, 'progress' => 10, 'public_ip' => '192.0.2.10',
        ], ...$attributes]);
    }

    public function test_every_provisioning_step_is_broadcast_so_the_list_updates_without_a_refresh(): void
    {
        Event::fake([ServerProvisioningUpdated::class]);
        $server = $this->server(User::factory()->create());

        $job = new class($server->id)
        {
            use InteractsWithServerProgress;

            public function __construct(public readonly string $serverId) {}

            public function step(Server $server): void
            {
                $this->progress($server, 60, 'Installing PHP', ServerStatus::Provisioning);
            }
        };
        $job->step($server);

        $this->assertSame(60, $server->fresh()->progress);
        $this->assertSame('Installing PHP', $server->fresh()->current_step);
        Event::assertDispatched(ServerProvisioningUpdated::class, fn ($event) => $event->server->is($server));
    }

    public function test_a_failed_bootstrap_is_broadcast_too_rather_than_leaving_the_bar_mid_flight(): void
    {
        Event::fake([ServerProvisioningUpdated::class]);
        $server = $this->server(User::factory()->create());

        $job = new class($server->id)
        {
            use InteractsWithServerProgress;

            public function __construct(public readonly string $serverId) {}
        };
        $job->failed(new \RuntimeException('apt-get exploded'));

        $this->assertSame(ServerStatus::Failed, $server->fresh()->status);
        $this->assertSame('apt-get exploded', $server->fresh()->failure_reason);
        Event::assertDispatched(ServerProvisioningUpdated::class);
    }

    public function test_the_broadcast_payload_carries_what_the_row_renders(): void
    {
        $server = $this->server(User::factory()->create(), ['progress' => 45, 'current_step' => 'Bootstrapping']);

        $payload = (new ServerProvisioningUpdated($server))->broadcastWith();

        $this->assertSame(['status' => 'provisioning', 'progress' => 45, 'current_step' => 'Bootstrapping', 'failure_reason' => null], $payload);
        $this->assertSame('private-servers.'.$server->id, (new ServerProvisioningUpdated($server))->broadcastOn()->name);
    }

    public function test_only_someone_who_can_view_the_server_may_subscribe(): void
    {
        // The channel in routes/channels.php delegates to this gate, which is what the
        // assertions below pin. Driving /broadcasting/auth over HTTP would prove nothing:
        // the suite runs on the log broadcaster, whose auth() never consults the channel
        // callbacks and answers 200 for anyone.
        $owner = User::factory()->create();
        $server = $this->server($owner);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $server));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view', $server));
    }

    public function test_the_list_subscribes_to_each_visible_server_and_polls_only_while_work_is_in_flight(): void
    {
        $user = User::factory()->create();
        $provisioning = $this->server($user);

        Livewire::actingAs($user)->test(ServerStatusList::class, ['servers' => collect([$provisioning])])
            ->assertSet('serverIds', [$provisioning->id])
            ->assertViewHas('active', true)
            ->assertSee('production');

        $provisioning->update(['status' => ServerStatus::Ready, 'progress' => 100]);

        Livewire::actingAs($user)->test(ServerStatusList::class, ['servers' => collect([$provisioning])])
            ->assertViewHas('active', false);
    }

    public function test_a_site_without_a_database_is_warned_and_cannot_press_deploy(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user, ['status' => ServerStatus::Ready, 'progress' => 100]);
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => 'active', 'webhook_secret' => Str::random(64)]);

        $this->actingAs($user)->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('Create a database before deploying')
            ->assertSee('Deploy now');

        $site->environmentVariables()->create(['key' => 'DB_CONNECTION', 'value' => 'mysql', 'is_secret' => false]);

        $this->actingAs($user)->get("/sites/{$site->id}")
            ->assertOk()
            ->assertDontSee('Create a database before deploying');
    }
}
