<?php

namespace Tests\Feature;

use App\Jobs\Servers\BootstrapServerJob;
use App\Jobs\Servers\FinalizeProvisioningJob;
use App\Models\CloudAccount;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudServerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_account_can_discover_droplets(): void
    {
        [$user, $account] = $this->account();
        Http::fake(['https://api.digitalocean.com/v2/droplets*' => Http::response(['droplets' => [$this->droplet()]])]);

        $this->actingAs($user)->get("/cloud-accounts/{$account->id}/servers")
            ->assertOk()->assertSee('application-01')->assertSee('203.0.113.10')->assertSee('Import and bootstrap');
    }

    public function test_active_droplet_is_imported_and_bootstrap_is_queued(): void
    {
        Bus::fake();
        [$user, $account] = $this->account();
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-rsa public', 'private_key' => 'private']);
        Http::fake(['https://api.digitalocean.com/v2/droplets/123' => Http::response(['droplet' => $this->droplet()])]);

        $this->actingAs($user)->post("/cloud-accounts/{$account->id}/servers", ['provider_id' => '123', 'ssh_key_id' => $key->id])
            ->assertRedirect('/dashboard')->assertSessionHas('status');

        $this->assertDatabaseHas('servers', ['user_id' => $user->id, 'cloud_account_id' => $account->id, 'provider_id' => '123', 'public_ip' => '203.0.113.10', 'status' => 'active']);
        Bus::assertChained([BootstrapServerJob::class, FinalizeProvisioningJob::class]);
    }

    public function test_import_does_not_assign_a_stale_team_id_when_membership_was_revoked(): void
    {
        Bus::fake();
        [$user, $account] = $this->account();
        $team = Team::create(['owner_id' => User::factory()->create()->id, 'name' => 'Platform', 'slug' => 'platform-'.str()->random(6)]);
        $user->update(['current_team_id' => $team->id]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-rsa public', 'private_key' => 'private']);
        Http::fake(['https://api.digitalocean.com/v2/droplets/123' => Http::response(['droplet' => $this->droplet()])]);

        $this->actingAs($user)->post("/cloud-accounts/{$account->id}/servers", ['provider_id' => '123', 'ssh_key_id' => $key->id])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('servers', ['user_id' => $user->id, 'provider_id' => '123', 'team_id' => null]);
    }

    public function test_customer_cannot_discover_another_customers_droplets(): void
    {
        [, $account] = $this->account();
        $intruder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($intruder)->get("/cloud-accounts/{$account->id}/servers")->assertForbidden();
    }

    private function account(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'name' => 'Production', 'provider' => 'digitalocean', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);

        return [$user, $account];
    }

    private function droplet(): array
    {
        return ['id' => 123, 'name' => 'application-01', 'status' => 'active', 'region' => ['slug' => 'nyc3'], 'size_slug' => 's-1vcpu-1gb', 'image' => ['slug' => 'ubuntu-24-04-x64'], 'networks' => ['v4' => [['type' => 'public', 'ip_address' => '203.0.113.10']]]];
    }
}
