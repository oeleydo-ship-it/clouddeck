<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Deployments\DeployLaravelJob;
use App\Jobs\Deployments\DeployReactJob;
use App\Jobs\Deployments\DeployWordPressJob;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReactSiteTest extends TestCase
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

    public function test_a_react_site_is_created_from_git_without_laravel_env(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/sites', [
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'platform' => 'react',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $site = Site::sole();
        $this->assertSame('react', $site->platform);
        $this->assertTrue($site->isReact());
        $this->assertFalse($site->usesPhp());
        $this->assertSame('current/dist', $site->documentRoot());
        $this->assertSame('https://github.com/acme/app.git', $site->repository_url);
        $this->assertEqualsCanonicalizing(['VITE_APP_URL', 'NODE_ENV'], $site->environmentVariables()->pluck('key')->all());
        Queue::assertPushedOn('provisioning', ConfigureSiteJob::class);
    }

    public function test_a_react_site_requires_a_repository(): void
    {
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post('/sites', [
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'platform' => 'react',
        ])->assertSessionHasErrors(['repository_url', 'branch']);
    }

    public function test_deploying_a_react_site_does_not_require_a_database(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'platform' => 'react',
            'php_version' => '8.5',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
            'webhook_secret' => Str::random(64),
        ]);

        $this->actingAs($user)->post("/sites/{$site->id}/deployments")->assertRedirect();

        Queue::assertPushedOn('deployments', DeployReactJob::class);
        Queue::assertNotPushed(DeployLaravelJob::class);
        Queue::assertNotPushed(DeployWordPressJob::class);
    }

    public function test_configure_and_deploy_scripts_use_spa_fallback(): void
    {
        $configure = file_get_contents(resource_path('scripts/configure-site.sh'));
        $deploy = file_get_contents(resource_path('scripts/deploy-react.sh'));
        $nginx = file_get_contents(resource_path('scripts/apply-nginx-settings.sh'));
        $removeSsl = file_get_contents(resource_path('scripts/remove-ssl.sh'));
        $rollback = file_get_contents(resource_path('scripts/rollback-release.sh'));

        $this->assertStringContainsString('PLATFORM={{PLATFORM}}', $configure);
        $this->assertStringContainsString('try_files \$uri \$uri/ /index.html;', $configure);
        $this->assertStringContainsString('if [ "${PLATFORM}" != "react" ]; then', $configure);
        $this->assertStringContainsString('npm run build', $deploy);
        $this->assertStringContainsString('dist/index.html', $deploy);
        $this->assertStringContainsString('build/index.html', $deploy);
        $this->assertStringContainsString('try_files \$uri \$uri/ /index.html;', $nginx);
        $this->assertStringContainsString('try_files \$uri \$uri/ /index.html;', $removeSsl);
        $this->assertStringContainsString('if [ "${PLATFORM}" != "react" ]; then', $rollback);
        $this->assertStringContainsString('test -f "${TARGET}/artisan"', $rollback);
        $this->assertStringContainsString('scripts/deploy-react.sh', file_get_contents(app_path('Jobs/Deployments/DeployReactJob.php')));
    }

    public function test_api_can_create_a_react_site(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->postJson('/api/sites', [
            'server_id' => $server->id,
            'domain' => 'spa.example.com',
            'platform' => 'react',
            'repository_url' => 'https://github.com/acme/spa.git',
            'branch' => 'main',
        ])->assertCreated()->assertJsonPath('data.platform', 'react');

        $this->assertSame('react', Site::sole()->platform);
        $this->assertFalse(Site::sole()->environmentVariables()->where('key', 'APP_KEY')->exists());
    }

    public function test_react_site_show_page_omits_php_queue_tabs_and_allows_deploy_without_a_database(): void
    {
        [$user, $server] = $this->infrastructure();
        $site = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'spa.example.com',
            'platform' => 'react',
            'repository_url' => 'https://github.com/acme/spa.git',
            'branch' => 'main',
            'status' => 'active',
            'webhook_secret' => Str::random(64),
        ]);

        $this->actingAs($user)
            ->get("/sites/{$site->id}")
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Sites/Show')
                ->where('meta.is_react', true)
                ->where('meta.has_database', false)
                ->where('meta.uses_php', false)
                ->has('tabs.overview')
                ->has('tabs.deploy')
                ->has('tabs.webhook')
                ->missing('tabs.queue')
                ->missing('tabs.cron')
                ->has('logSources.nginx')
                ->missing('logSources.laravel')
                ->missing('logSources.php'));
    }
}
