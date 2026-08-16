<?php

namespace Tests\Feature;

use App\Actions\Deployments\StartDeployment;
use App\Enums\ServerStatus;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\SystemSettings;
use App\Support\DatabaseBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBootstrapTest extends TestCase
{
    use RefreshDatabase;
    /** @var list<string>|null */
    private ?array $originalArgv = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalArgv = $_SERVER['argv'] ?? null;
    }

    protected function tearDown(): void
    {
        unset($_ENV['DB_CONNECTION'], $_ENV['DB_HOST'], $_ENV['DB_DATABASE'], $_ENV['DB_USERNAME']);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        if ($this->originalArgv === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $this->originalArgv;
        }

        parent::tearDown();
    }

    public function test_app_service_provider_boot_survives_without_a_database_file(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => database_path('missing-boot-test.sqlite'),
        ]);

        $_SERVER['argv'] = ['artisan', 'package:discover'];

        $this->app->make(AppServiceProvider::class, ['app' => $this->app])->boot();

        $this->assertTrue(true);
    }

    public function test_system_settings_returns_defaults_when_database_is_unavailable(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => database_path('missing-settings-test.sqlite'),
        ]);

        $_SERVER['argv'] = ['artisan', 'package:discover'];

        $this->assertSame('fallback-name', app(SystemSettings::class)->get('platform_name', 'fallback-name'));
    }

    public function test_database_bootstrap_detects_composer_autoload_commands(): void
    {
        $_SERVER['argv'] = ['artisan', 'package:discover'];

        $this->assertTrue(DatabaseBootstrap::runningComposerBootCommand());
        $this->assertTrue(DatabaseBootstrap::shouldDeferDatabaseAccess());
    }

    public function test_managed_database_without_credentials_does_not_throw_during_composer_boot(): void
    {
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_HOST'] = '';
        $_ENV['DB_DATABASE'] = '';
        $_ENV['DB_USERNAME'] = '';

        $_SERVER['argv'] = ['artisan', 'package:discover'];

        DatabaseBootstrap::enforceConfiguredConnection();

        $this->assertSame('mysql', config('database.default'));
    }

    public function test_managed_database_without_credentials_throws_outside_deferral(): void
    {
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_HOST'] = '';
        $_ENV['DB_DATABASE'] = '';
        $_ENV['DB_USERNAME'] = '';
        $_SERVER['argv'] = ['artisan', 'migrate'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SQLite fallback is not allowed');

        DatabaseBootstrap::enforceConfiguredConnection();
    }

    public function test_missing_credential_keys_detects_incomplete_site_environment(): void
    {
        $missing = DatabaseBootstrap::missingCredentialKeys('pgsql', [
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_DATABASE' => 'app',
        ]);

        $this->assertSame(['DB_USERNAME'], $missing);
    }

    public function test_deployment_rejects_incomplete_managed_database_environment(): void
    {
        [$user, $server] = $this->infrastructure();

        $site = \App\Models\Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'incomplete.example.com',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
        ]);
        $site->environmentVariables()->create(['key' => 'DB_CONNECTION', 'value' => 'mysql', 'is_secret' => false]);

        $this->actingAs($user)
            ->post("/sites/{$site->id}/deployments")
            ->assertSessionHasErrors('deployment');
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
