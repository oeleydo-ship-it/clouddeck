<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\CloudAccount;
use App\Models\ManagedDatabase;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A soft-deleted row must not reserve its name forever. This kept reaching customers as a
 * 500 from an integrity violation — on queue workers, then site domains, then databases.
 */
class SoftDeletedNameReuseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Server} */
    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::firstOrCreate(['slug' => 'unlimited'], ['name' => 'Unlimited', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1], 'features' => [], 'active' => true, 'public' => true]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);

        return [$user, $server];
    }

    public function test_a_database_name_is_free_again_once_the_database_is_deleted(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $payload = ['engine' => 'mysql', 'name' => 'wp', 'username' => 'wp'];

        $this->actingAs($user)->post("/servers/{$server->id}/databases", $payload)->assertSessionHasNoErrors();
        $database = ManagedDatabase::sole();
        // Deleting queues a remote drop and trashes the row once it succeeds; this is the
        // state the operator is in afterwards.
        $this->actingAs($user)->delete("/databases/{$database->id}", ['confirmation' => $database->name])->assertSessionHas('status');
        $database->delete();

        // Previously an integrity violation that reached the operator as a 500.
        $this->actingAs($user)->post("/servers/{$server->id}/databases", $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, ManagedDatabase::count());
    }

    public function test_deleting_a_database_requires_typing_its_name(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post("/servers/{$server->id}/databases", ['engine' => 'mysql', 'name' => 'wp', 'username' => 'wp'])->assertSessionHasNoErrors();
        $database = ManagedDatabase::sole();

        $this->actingAs($user)->delete("/databases/{$database->id}")->assertSessionHasErrors('confirmation');
        $this->actingAs($user)->delete("/databases/{$database->id}", ['confirmation' => 'wrong'])->assertSessionHasErrors('confirmation');
        $this->assertSame('pending', $database->fresh()->status);

        $this->actingAs($user)->delete("/databases/{$database->id}", ['confirmation' => 'wp'])->assertSessionHas('status');
        $this->assertSame('deleting', $database->fresh()->status);
    }

    public function test_a_database_name_already_live_on_that_server_is_refused_with_a_message(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $payload = ['engine' => 'mysql', 'name' => 'wp', 'username' => 'wp'];

        $this->actingAs($user)->post("/servers/{$server->id}/databases", $payload)->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/servers/{$server->id}/databases", $payload)->assertSessionHasErrors('name');

        $this->assertSame(1, ManagedDatabase::count());
    }

    public function test_the_same_database_name_is_allowed_under_a_different_engine(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post("/servers/{$server->id}/databases", ['engine' => 'mysql', 'name' => 'wp', 'username' => 'wp'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/servers/{$server->id}/databases", ['engine' => 'postgresql', 'name' => 'wp', 'username' => 'wp'])->assertSessionHasNoErrors();

        $this->assertSame(2, ManagedDatabase::count());
    }

    public function test_a_deleted_plan_frees_its_slug(): void
    {
        $attributes = ['name' => 'Pro', 'slug' => 'pro', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => [], 'features' => [], 'active' => true, 'public' => true];
        Plan::create($attributes)->delete();

        Plan::create($attributes);

        $this->assertSame(1, Plan::count());
    }

    public function test_a_deleted_post_frees_its_slug(): void
    {
        $attributes = ['title' => 'Hello', 'slug' => 'hello', 'body' => 'Words.'];
        Post::create($attributes)->delete();

        Post::create($attributes);

        $this->assertSame(1, Post::count());
    }

    public function test_a_deleted_team_frees_its_slug(): void
    {
        $user = User::factory()->create();
        $attributes = ['owner_id' => $user->id, 'name' => 'Platform', 'slug' => 'platform'];
        Team::create($attributes)->delete();

        Team::create($attributes);

        $this->assertSame(1, Team::count());
    }
}
