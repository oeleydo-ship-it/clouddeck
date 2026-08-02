<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Deployments\DeployLaravelJob;
use App\Jobs\Deployments\DeployWordPressJob;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WordPressConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressSiteTest extends TestCase
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

    private function wordpressSite(User $user, Server $server, bool $withDatabase = true): Site
    {
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'blog.example.com', 'platform' => 'wordpress', 'php_version' => '8.4', 'status' => 'active', 'webhook_secret' => Str::random(64)]);

        if ($withDatabase) {
            foreach (['DB_DATABASE' => 'blog', 'DB_USERNAME' => 'blog_user', 'DB_PASSWORD' => 'secret', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306'] as $key => $value) {
                $site->environmentVariables()->create(['key' => $key, 'value' => $value, 'is_secret' => $key === 'DB_PASSWORD']);
            }
        }

        return $site;
    }

    public function test_a_wordpress_site_is_created_without_a_repository(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/sites', [
            'server_id' => $server->id, 'domain' => 'blog.example.com', 'platform' => 'wordpress', 'php_version' => '8.4',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $site = Site::sole();
        $this->assertSame('wordpress', $site->platform);
        $this->assertNull($site->repository_url);
    }

    public function test_a_laravel_site_still_demands_a_repository(): void
    {
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/sites', [
            'server_id' => $server->id, 'domain' => 'app.example.com', 'platform' => 'laravel', 'php_version' => '8.4',
        ])->assertSessionHasErrors(['repository_url', 'branch']);
    }

    public function test_a_caller_that_predates_platforms_still_creates_a_laravel_site(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        // The API shipped before platforms existed; omitting it must not silently create
        // a WordPress site, nor start rejecting requests that used to work.
        $this->actingAs($user)->postJson('/api/sites', [
            'server_id' => $server->id, 'domain' => 'api.example.com', 'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main',
        ])->assertCreated();

        $this->assertSame('laravel', Site::sole()->platform);
    }

    public function test_wordpress_is_served_from_the_install_root_and_laravel_from_public(): void
    {
        [$user, $server] = $this->infrastructure();
        $wordpress = $this->wordpressSite($user, $server);
        $laravel = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'platform' => 'laravel', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => 'active', 'webhook_secret' => Str::random(64)]);

        $this->assertSame('current', $wordpress->documentRoot());
        $this->assertSame('current/public', $laravel->documentRoot());
    }

    public function test_deploying_picks_the_pipeline_that_matches_the_platform(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->wordpressSite($user, $server);

        $this->actingAs($user)->post("/sites/{$site->id}/deployments")->assertRedirect();

        Queue::assertPushedOn('deployments', DeployWordPressJob::class);
        Queue::assertNotPushed(DeployLaravelJob::class);
    }

    public function test_wordpress_cannot_be_deployed_before_a_database_exists(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = $this->wordpressSite($user, $server, withDatabase: false);

        // WordPress has no DB_CONNECTION to check, but needs a database just as much.
        $this->actingAs($user)->post("/sites/{$site->id}/deployments")->assertSessionHasErrors('deployment');
        Queue::assertNotPushed(DeployWordPressJob::class);
    }

    public function test_the_generated_config_carries_the_database_credentials(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->wordpressSite($user, $server);

        $config = app(WordPressConfig::class)->render($site);

        $this->assertStringContainsString("define('DB_NAME', 'blog')", $config);
        $this->assertStringContainsString("define('DB_USER', 'blog_user')", $config);
        $this->assertStringContainsString("define('DB_PASSWORD', 'secret')", $config);
        $this->assertStringContainsString("define('DB_HOST', '127.0.0.1:3306')", $config);
        // Nginx terminates TLS, so without this WordPress builds http:// URLs and loops.
        $this->assertStringContainsString('HTTP_X_FORWARDED_PROTO', $config);
        $this->assertStringContainsString("define('DISALLOW_FILE_MODS', true)", $config);
    }

    public function test_salts_are_generated_once_and_survive_later_deployments(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->wordpressSite($user, $server);
        $config = app(WordPressConfig::class);

        $config->render($site);
        $first = $site->environmentVariables()->where('key', 'WP_AUTH_KEY')->value('value');
        $config->render($site->fresh());
        $second = $site->environmentVariables()->where('key', 'WP_AUTH_KEY')->value('value');

        // Regenerating them would sign every user out on each release.
        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
        $this->assertSame(8, $site->environmentVariables()->where('key', 'like', 'WP_%')->count());
    }

    public function test_the_salts_are_stored_as_secrets(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = $this->wordpressSite($user, $server);

        app(WordPressConfig::class)->ensureSalts($site);

        $this->assertTrue((bool) $site->environmentVariables()->where('key', 'WP_AUTH_KEY')->value('is_secret'));
        $this->assertNotSame(
            $site->environmentVariables()->where('key', 'WP_AUTH_KEY')->value('value'),
            \DB::table('environment_variables')->where('site_id', $site->id)->where('key', 'WP_AUTH_KEY')->value('value'),
        );
    }

    public function test_a_wordpress_site_is_not_seeded_with_laravel_environment_keys(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/sites', ['server_id' => $server->id, 'domain' => 'blog.example.com', 'platform' => 'wordpress', 'php_version' => '8.4']);

        $keys = Site::sole()->environmentVariables()->pluck('key');
        $this->assertFalse($keys->contains('APP_KEY'));
        $this->assertFalse($keys->contains('QUEUE_CONNECTION'));
        $this->assertTrue($keys->contains('WP_AUTH_KEY'));
    }
}
