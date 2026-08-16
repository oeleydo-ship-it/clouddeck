<?php

namespace Tests\Feature;

use App\Actions\Deployments\StartDeployment;
use App\Enums\ServerStatus;
use App\Jobs\Deployments\DeployLaravelJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManagedDatabaseEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_deployment_replaces_stale_database_credentials_with_attached_managed_database(): void
    {
        Process::fake(['*ssh*' => Process::result(output: "Build complete\n", exitCode: 0)]);
        [$user, $server, $site] = $this->infrastructure();

        foreach ([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'staging',
            'DB_USERNAME' => 'staging',
            'DB_PASSWORD' => 'wrong-password',
        ] as $key => $value) {
            $site->environmentVariables()->create([
                'key' => $key,
                'value' => $value,
                'is_secret' => $key === 'DB_PASSWORD',
            ]);
        }

        $server->databases()->create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'engine' => 'postgresql',
            'name' => 'talaldocs',
            'username' => 'talaldocs_user',
            'password' => 'postgres-secret',
            'status' => 'ready',
        ]);

        $deployment = app(StartDeployment::class)->execute($site->fresh(['database', 'environmentVariables']), $user);
        (new DeployLaravelJob($deployment->id))->handle(app(SshClient::class));

        $site->refresh();
        $this->assertSame('pgsql', $site->environmentVariables()->where('key', 'DB_CONNECTION')->value('value'));
        $this->assertSame('talaldocs', $site->environmentVariables()->where('key', 'DB_DATABASE')->value('value'));
        $this->assertSame('talaldocs_user', $site->environmentVariables()->where('key', 'DB_USERNAME')->value('value'));
        $this->assertSame('postgres-secret', $site->environmentVariables()->where('key', 'DB_PASSWORD')->value('value'));
        $this->assertSame('5432', $site->environmentVariables()->where('key', 'DB_PORT')->value('value'));
    }

    public function test_environment_editor_cannot_override_managed_database_credentials(): void
    {
        [$user, $server, $site] = $this->infrastructure();

        $server->databases()->create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'engine' => 'postgresql',
            'name' => 'talaldocs',
            'username' => 'talaldocs_user',
            'password' => 'postgres-secret',
            'status' => 'ready',
        ])->syncAttachedSiteEnvironment();

        $this->actingAs($user)->put("/sites/{$site->id}/environment", [
            'environment' => implode("\n", [
                'APP_NAME="Talal Docs"',
                'DB_CONNECTION=mysql',
                'DB_HOST=127.0.0.1',
                'DB_PORT=3306',
                'DB_DATABASE=staging',
                'DB_USERNAME=staging',
                'DB_PASSWORD=wrong-password',
            ]),
        ])->assertSessionHas('status');

        $this->assertSame('pgsql', $site->environmentVariables()->where('key', 'DB_CONNECTION')->value('value'));
        $this->assertSame('talaldocs', $site->environmentVariables()->where('key', 'DB_DATABASE')->value('value'));
        $this->assertSame('Talal Docs', $site->environmentVariables()->where('key', 'APP_NAME')->value('value'));
    }

    public function test_reattaching_database_always_clears_db_env_from_previous_site(): void
    {
        [$user, $server, $production] = $this->infrastructure();
        $staging = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'staging.client.com',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'staging',
            'status' => 'active',
            'environment' => 'staging',
            'webhook_secret' => Str::random(64),
        ]);

        $database = $server->databases()->create([
            'user_id' => $user->id,
            'site_id' => $production->id,
            'engine' => 'mysql',
            'name' => 'staging',
            'username' => 'staging',
            'password' => 'secret-pass',
            'status' => 'ready',
        ]);
        $database->syncAttachedSiteEnvironment();

        $production->environmentVariables()->where('key', 'DB_DATABASE')->update(['value' => 'manual_override']);

        $this->actingAs($user)
            ->patch(route('databases.update', $database), ['site_id' => $staging->id])
            ->assertSessionHas('status');

        $this->assertNull($production->environmentVariables()->where('key', 'DB_CONNECTION')->value('value'));
        $this->assertSame('staging', $staging->environmentVariables()->where('key', 'DB_DATABASE')->value('value'));
    }

    public function test_deleting_managed_database_clears_site_environment_credentials(): void
    {
        Process::fake(['*' => Process::result(output: 'deleted', exitCode: 0)]);
        [$user, $server, $site] = $this->infrastructure();
        $database = $server->databases()->create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'engine' => 'postgresql',
            'name' => 'talaldocs',
            'username' => 'talaldocs_user',
            'password' => 'postgres-secret',
            'status' => 'ready',
        ]);
        $database->syncAttachedSiteEnvironment();

        (new \App\Jobs\Operations\DeleteDatabaseJob($database->id))->handle(app(SshClient::class));

        $this->assertNull($site->environmentVariables()->where('key', 'DB_CONNECTION')->value('value'));
        $this->assertNull($site->environmentVariables()->where('key', 'DB_DATABASE')->value('value'));
    }

    /**
     * @return array{0: User, 1: Server, 2: Site}
     */
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
        $site = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'talaldocs.com',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
            'environment' => 'production',
            'webhook_secret' => Str::random(64),
        ]);

        return [$user, $server, $site];
    }
}
