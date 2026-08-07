<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Servers\BootstrapServerJob;
use App\Jobs\Servers\ConnectCustomServerJob;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PhpVersionSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_creation_accepts_php_8_5(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/sites', [
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'php_version' => '8.5',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
        ])->assertRedirect();

        $this->assertSame('8.5', $user->sites()->firstOrFail()->php_version);
        Queue::assertPushed(ConfigureSiteJob::class);
    }

    public function test_site_creation_rejects_unknown_php_version(): void
    {
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/sites', [
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'php_version' => '8.1',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
        ])->assertSessionHasErrors('php_version');
    }

    public function test_create_site_form_defaults_to_php_8_5(): void
    {
        [$user] = $this->infrastructure();

        $this->actingAs($user)
            ->get('/sites/create')
            ->assertOk()
            ->assertSee('name="php_version"', false)
            ->assertSee('>8.5</option>', false)
            ->assertSee('selected>8.5</option>', false);
    }

    public function test_bootstrap_script_installs_managed_php_versions_including_8_5(): void
    {
        $script = file_get_contents(resource_path('scripts/bootstrap-ubuntu.sh'));

        $this->assertStringContainsString('PHP_VERSIONS={{PHP_VERSIONS}}', $script);
        $this->assertStringContainsString('php${ver}-fpm', $script);
        $this->assertStringContainsString('php${ver}-cli', $script);
        $this->assertStringContainsString('php${ver}-mysql', $script);
        $this->assertStringContainsString('php${ver}-xml', $script);
        $this->assertStringContainsString('php${ver}-mbstring', $script);
        $this->assertStringContainsString('php${ver}-curl', $script);
        $this->assertStringContainsString('php${ver}-zip', $script);
        $this->assertStringContainsString('php${ver}-bcmath', $script);
        $this->assertStringContainsString('php${ver}-redis', $script);
        $this->assertStringContainsString('php${ver}-gd', $script);
        $this->assertStringContainsString('update-alternatives --set php', $script);

        $this->assertSame(['8.5', '8.4', '8.3', '8.2'], config('clouddeck.php_versions'));
        $this->assertSame('8.5', config('clouddeck.default_php_version'));
    }

    public function test_provisioning_jobs_pass_default_php_8_5_and_all_managed_versions(): void
    {
        $bootstrap = file_get_contents((new \ReflectionClass(BootstrapServerJob::class))->getFileName());
        $connect = file_get_contents((new \ReflectionClass(ConnectCustomServerJob::class))->getFileName());

        foreach ([$bootstrap, $connect] as $source) {
            $this->assertStringContainsString("config('clouddeck.default_php_version')", $source);
            $this->assertStringContainsString("config('clouddeck.php_versions')", $source);
            $this->assertStringNotContainsString("'PHP_VERSION' => '8.4'", $source);
        }
    }

    public function test_site_update_accepts_php_8_5(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $user->sites()->create([
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
            'webhook_secret' => 'secret',
        ]);

        $this->actingAs($user)->patch("/sites/{$site->id}", [
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'php_version' => '8.5',
        ])->assertRedirect();

        $this->assertSame('8.5', $site->fresh()->php_version);
    }

    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);

        return [$user, $server];
    }
}
