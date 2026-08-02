<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\CloudAccount;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every authenticated page must render. This is the guard rail for theme/markup refactors:
 * a Blade or view-data mistake surfaces here instead of in the browser.
 */
class PageRenderSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_primary_page_renders_for_an_authenticated_admin(): void
    {
        Http::fake([
            'https://api.digitalocean.com/v2/droplets*' => Http::response(['droplets' => []]),
            'https://api.digitalocean.com/v2/*' => Http::response([]),
        ]);
        [$user, $server, $site, $account, $deployment] = $this->world();

        $urls = [
            '/',
            '/dashboard',
            '/servers',
            '/sites',
            '/sites/create',
            '/cloud-accounts',
            '/ssh-keys',
            '/teams',
            '/billing',
            '/account',
            '/admin',
            '/servers/create',
            "/servers/{$server->id}/manage",
            "/cloud-accounts/{$account->id}/servers",
            "/sites/{$site->id}",
            "/sites/{$site->id}/remote",
            "/deployments/{$deployment->id}",
        ];

        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_guest_pages_render(): void
    {
        foreach (['/', '/login', '/register', '/forgot-password'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    private function world(): array
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => 'active']);
        $deployment = $site->deployments()->create(['user_id' => $user->id, 'status' => \App\Enums\DeploymentStatus::Successful, 'trigger' => 'manual', 'release' => '20260101000000-abc']);

        return [$user, $server, $site, $account, $deployment];
    }
}
