<?php

namespace Tests\Feature;

use App\Jobs\Servers\BootstrapServerJob;
use App\Jobs\Servers\CreateDropletJob;
use App\Jobs\Servers\FinalizeProvisioningJob;
use App\Jobs\Servers\WaitForServerJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cloud_credentials_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret-token']]);

        $this->assertSame('secret-token', $account->credentials['token']);
        $this->assertStringNotContainsString('secret-token', DB::table('cloud_accounts')->where('id', $account->id)->value('credentials'));
    }

    public function test_customer_cannot_read_another_customers_server(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $account = CloudAccount::create(['user_id' => $owner->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret']]);
        $server = Server::create(['user_id' => $owner->id, 'cloud_account_id' => $account->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64']);
        Sanctum::actingAs($intruder);

        $this->getJson("/api/servers/{$server->id}")->assertForbidden();
    }

    public function test_server_creation_dispatches_provisioning_chain(): void
    {
        Bus::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret']]);
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/servers', ['cloud_account_id' => $account->id, 'name' => 'Application', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64'])->assertCreated()->assertJsonPath('data.hostname', 'app-01');
        Bus::assertChained([CreateDropletJob::class, WaitForServerJob::class, BootstrapServerJob::class, FinalizeProvisioningJob::class]);
    }
}
