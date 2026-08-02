<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiteDomainUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function server(User $user, string $name = 'production'): Server
    {
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => $name, 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed '.$name, 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);

        return Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => $name, 'hostname' => $name.'-01', 'region' => 'ams3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
    }

    private function user(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::create(['name' => 'Unlimited', 'slug' => 'unlimited', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1], 'features' => [], 'active' => true, 'public' => true]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);

        return $user;
    }

    private function payload(Server $server, string $domain = 'demo.example.com'): array
    {
        return [
            'server_id' => $server->id,
            'domain' => $domain,
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
        ];
    }

    public function test_a_domain_belonging_to_a_deleted_site_can_be_used_again(): void
    {
        Queue::fake();
        $user = $this->user();
        $server = $this->server($user);

        $this->actingAs($user)->post('/sites', $this->payload($server))->assertRedirect();
        $site = Site::where('domain', 'demo.example.com')->sole();
        $this->actingAs($user)->delete("/sites/{$site->id}", ['confirmation' => 'demo.example.com']);
        $this->assertSoftDeleted('sites', ['id' => $site->id]);

        // Previously the index still held the trashed row, so this came back as a 500.
        $this->actingAs($user)->post('/sites', $this->payload($server))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Site::where('domain', 'demo.example.com')->count());
    }

    public function test_a_domain_already_live_on_that_server_is_refused_with_a_message(): void
    {
        Queue::fake();
        $user = $this->user();
        $server = $this->server($user);
        $this->actingAs($user)->post('/sites', $this->payload($server))->assertRedirect();

        // MySQL treats NULL deleted_at values as distinct, so the widened index does not
        // catch this — validation is the only thing standing between the operator and a
        // second site fighting over the same Nginx server block.
        $this->actingAs($user)->post('/sites', $this->payload($server))->assertSessionHasErrors('domain');

        $this->assertSame(1, Site::where('domain', 'demo.example.com')->count());
    }

    public function test_the_same_domain_is_allowed_on_a_different_server(): void
    {
        Queue::fake();
        $user = $this->user();
        $first = $this->server($user, 'production');
        $second = $this->server($user, 'staging');

        $this->actingAs($user)->post('/sites', $this->payload($first))->assertRedirect();
        $this->actingAs($user)->post('/sites', $this->payload($second))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, Site::where('domain', 'demo.example.com')->count());
    }

    public function test_the_api_refuses_a_duplicate_domain_too(): void
    {
        Queue::fake();
        $user = $this->user();
        $server = $this->server($user);
        $this->actingAs($user)->post('/sites', $this->payload($server))->assertRedirect();

        $this->actingAs($user)->postJson('/api/sites', $this->payload($server))->assertStatus(422)->assertJsonValidationErrors('domain');
    }
}
