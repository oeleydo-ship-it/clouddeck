<?php

namespace Tests\Feature;

use App\Actions\Sites\QueueSiteSslIssuance;
use App\Enums\ServerStatus;
use App\Jobs\Deployments\DeployLaravelJob;
use App\Jobs\Operations\InstallSslCertificateJob;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\SslCertificate;
use App\Models\User;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteVhostSslTest extends TestCase
{
    use RefreshDatabase;

    public function test_configure_site_script_adds_temporary_https_listener(): void
    {
        $script = file_get_contents(resource_path('scripts/configure-site.sh'));

        $this->assertStringContainsString('ensure_https_listener', $script);
        $this->assertStringContainsString('listen 443 ssl', $script);
        $this->assertStringContainsString('/etc/ssl/clouddeck/${DOMAIN}', $script);
    }

    public function test_configure_site_job_queues_lets_encrypt_issuance(): void
    {
        Queue::fake();
        Process::fake(['*ssh*' => Process::result(output: 'Added temporary HTTPS listener', exitCode: 0)]);
        [$user, $server] = $this->infrastructure();
        $site = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'talaldocs.com',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/docs.git',
            'branch' => 'main',
            'status' => 'configuring',
        ]);

        (new ConfigureSiteJob($site->id))->handle(app(SshClient::class));

        $this->assertSame('active', $site->fresh()->status);
        Queue::assertPushedOn('operations', InstallSslCertificateJob::class);
        $this->assertDatabaseHas('ssl_certificates', [
            'site_id' => $site->id,
            'provider' => 'letsencrypt',
            'status' => 'pending',
        ]);
    }

    public function test_successful_deploy_queues_lets_encrypt_when_no_certificate_exists(): void
    {
        Queue::fake();
        Process::fake(['*ssh*' => Process::result(output: "Build complete\n", exitCode: 0)]);
        [$user, $server] = $this->infrastructure();
        $site = $this->deployableSite($user, $server);
        $deployment = $site->deployments()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'trigger' => 'manual',
        ]);

        (new DeployLaravelJob($deployment->id))->handle(app(SshClient::class));

        Queue::assertPushedOn('operations', InstallSslCertificateJob::class);
    }

    public function test_ssl_issuance_is_not_queued_when_an_active_certificate_exists(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $site = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'talaldocs.com',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/docs.git',
            'branch' => 'main',
            'status' => 'active',
        ]);
        SslCertificate::create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'domains' => [$site->domain],
            'provider' => 'letsencrypt',
            'status' => 'active',
            'force_https' => true,
            'auto_renew' => true,
            'issued_at' => now(),
            'expires_at' => now()->addMonths(3),
        ]);

        $queued = app(QueueSiteSslIssuance::class)->handle($site);

        $this->assertFalse($queued);
        Queue::assertNothingPushed();
    }

    /** @return array{0: User, 1: Server} */
    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);

        return [$user, $server];
    }

    private function deployableSite(User $user, Server $server): Site
    {
        $site = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
            'webhook_secret' => Str::random(64),
        ]);
        foreach ([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'application',
            'DB_USERNAME' => 'application_user',
            'DB_PASSWORD' => 'secret',
        ] as $key => $value) {
            $site->environmentVariables()->create(['key' => $key, 'value' => $value, 'is_secret' => $key === 'DB_PASSWORD']);
        }

        return $site;
    }
}
