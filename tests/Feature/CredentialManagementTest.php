<?php

namespace Tests\Feature;

use App\Models\SshKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CredentialManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_digitalocean_account_is_validated_and_token_is_encrypted(): void
    {
        Http::fake([
            'https://api.digitalocean.com/v2/account' => Http::response(['account' => ['status' => 'active']]),
            'https://api.digitalocean.com/v2/droplets*' => Http::response(['droplets' => []]),
            'https://api.digitalocean.com/v2/account/keys*' => Http::response(['ssh_keys' => []]),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post('/cloud-accounts', ['name' => 'Production', 'provider' => 'digitalocean', 'token' => str_repeat('a', 64)])->assertSessionHas('status');

        $account = $user->cloudAccounts()->firstOrFail();
        $this->assertNotNull($account->validated_at);
        $this->assertStringNotContainsString(str_repeat('a', 64), DB::table('cloud_accounts')->where('id', $account->id)->value('credentials'));
    }

    public function test_validation_api_checks_token_without_storing_it(): void
    {
        Http::fake([
            'https://api.digitalocean.com/v2/account' => Http::response(['account' => ['status' => 'active']]),
            'https://api.digitalocean.com/v2/droplets*' => Http::response(['droplets' => []]),
            'https://api.digitalocean.com/v2/account/keys*' => Http::response(['ssh_keys' => []]),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $apiToken = $user->createToken('API', ['servers:write'])->plainTextToken;

        $this->withToken($apiToken)->postJson('/api/cloud-accounts/validate', [
            'provider' => 'digitalocean',
            'token' => str_repeat('b', 64),
        ])->assertOk()->assertJsonPath('valid', true)->assertJsonPath('checks.ssh_keys_read', true);

        $this->assertDatabaseCount('cloud_accounts', 0);
    }

    public function test_validation_api_returns_a_safe_authentication_error(): void
    {
        Http::fake(['https://api.digitalocean.com/v2/account' => Http::response(['message' => 'Unable to authenticate you'], 401)]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $apiToken = $user->createToken('API', ['servers:write'])->plainTextToken;

        $this->withToken($apiToken)->postJson('/api/cloud-accounts/validate', [
            'provider' => 'digitalocean',
            'token' => str_repeat('x', 64),
        ])->assertUnprocessable()->assertJsonPath('valid', false)->assertJsonPath('provider_status', 401);
    }

    public function test_generated_private_key_can_only_be_downloaded_once(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/ssh-keys/generate', ['name' => 'Primary'])->assertSessionHas('download_key');
        $key = $user->sshKeys()->firstOrFail();

        $this->actingAs($user)->get("/ssh-keys/{$key->id}/download")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($user)->get("/ssh-keys/{$key->id}/download")->assertNotFound();
    }

    public function test_user_cannot_download_another_users_private_key(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        $key = SshKey::create(['user_id' => $owner->id, 'name' => 'Owner', 'public_key' => 'ssh-rsa example', 'private_key' => 'private']);

        $this->actingAs($intruder)->get("/ssh-keys/{$key->id}/download")->assertNotFound();
    }

    public function test_provision_wizard_requires_verified_authentication(): void
    {
        $this->get('/servers/create')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/servers/create')->assertOk()->assertSee('Provision a server');
    }

    public function test_existing_provider_key_is_reused_despite_a_different_fingerprint_format(): void
    {
        $publicKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIexample clouddeck';
        Http::fake([
            'https://api.digitalocean.com/v2/account/keys*' => Http::response(['ssh_keys' => [
                ['id' => 555, 'fingerprint' => '3b:16:bf:e4:aa:bb:cc:dd', 'public_key' => $publicKey],
            ]]),
        ]);

        $provider = new \App\Cloud\DigitalOcean\DigitalOceanProvider('token');
        $id = $provider->ensureSshKey('Primary', $publicKey, 'SHA256:0Wh4u3VrA3XBBCKZWgKg3z');

        $this->assertSame('555', $id);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_provider_key_collision_resolves_to_the_existing_key(): void
    {
        $publicKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIexample clouddeck';
        $listed = false;
        Http::fake(function ($request) use ($publicKey, &$listed) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'unprocessable_entity', 'message' => 'SSH Key is already in use on your account'], 422);
            }
            // The first listing hides the key, forcing the upload attempt that collides.
            $keys = $listed ? [['id' => 777, 'fingerprint' => 'aa:bb', 'public_key' => $publicKey]] : [];
            $listed = true;

            return Http::response(['ssh_keys' => $keys]);
        });

        $provider = new \App\Cloud\DigitalOcean\DigitalOceanProvider('token');

        $this->assertSame('777', $provider->ensureSshKey('Primary', $publicKey, 'SHA256:mismatch'));
    }
}
