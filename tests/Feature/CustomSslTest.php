<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Operations\InstallCustomSslCertificateJob;
use App\Jobs\Operations\InstallSslCertificateJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\SslCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomSslTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_ssl_upload_validates_pems_and_dispatches_install_job(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();
        [$fullchain, $privateKey] = $this->fixturePemPair();

        $this->actingAs($user)
            ->from(route('sites.show', ['site' => $site, 'tab' => 'ssl']))
            ->post(route('ssl.custom', $site), [
                '_tab' => 'ssl',
                'force_https' => '1',
                'fullchain_pem' => $fullchain,
                'private_key_pem' => $privateKey,
            ])
            ->assertRedirect(route('sites.show', ['site' => $site, 'tab' => 'ssl']))
            ->assertSessionHas('status', 'Custom SSL install queued.');

        $certificate = $site->sslCertificates()->sole();
        $this->assertSame('custom', $certificate->provider);
        $this->assertFalse($certificate->auto_renew);
        $this->assertSame('pending', $certificate->status);
        $this->assertSame($fullchain, $certificate->certificate_pem);
        $this->assertSame($privateKey, $certificate->private_key_pem);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', (string) DB::table('ssl_certificates')->where('id', $certificate->id)->value('certificate_pem'));
        $this->assertTrue($certificate->expires_at->isFuture());

        Queue::assertPushedOn('operations', InstallCustomSslCertificateJob::class);
    }

    public function test_custom_ssl_accepts_uploaded_pem_files(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();
        [$fullchain, $privateKey] = $this->fixturePemPair();

        $this->actingAs($user)
            ->post(route('ssl.custom', $site), [
                'force_https' => '1',
                'fullchain' => UploadedFile::fake()->createWithContent('fullchain.pem', $fullchain),
                'private_key' => UploadedFile::fake()->createWithContent('privkey.pem', $privateKey),
            ])
            ->assertSessionHas('status');

        $this->assertSame('custom', $site->sslCertificates()->sole()->provider);
        Queue::assertPushed(InstallCustomSslCertificateJob::class);
    }

    public function test_invalid_custom_pem_is_rejected(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $this->actingAs($user)
            ->post(route('ssl.custom', $site), [
                'fullchain_pem' => "-----BEGIN CERTIFICATE-----\nnot-a-cert\n-----END CERTIFICATE-----",
                'private_key_pem' => "-----BEGIN PRIVATE KEY-----\nnot-a-key\n-----END PRIVATE KEY-----",
            ])
            ->assertSessionHasErrors(['fullchain']);

        $this->assertSame(0, $site->sslCertificates()->count());
        Queue::assertNothingPushed();
    }

    public function test_mismatched_key_is_rejected(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();
        [$fullchain] = $this->fixturePemPair();
        $otherKey = trim((string) file_get_contents(base_path('tests/fixtures/ssl-other-privkey.pem')));

        $this->actingAs($user)
            ->post(route('ssl.custom', $site), [
                'fullchain_pem' => $fullchain,
                'private_key_pem' => $otherKey,
            ])
            ->assertSessionHasErrors(['private_key']);

        Queue::assertNothingPushed();
    }

    public function test_auto_renew_schedule_skips_custom_certificates(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $site->sslCertificates()->create([
            'user_id' => $user->id,
            'domains' => [$site->domain],
            'provider' => 'custom',
            'status' => 'active',
            'auto_renew' => true,
            'expires_at' => now()->addDays(10),
        ]);

        // Mirrors routes/console.php renew-expiring-certificates (provider=letsencrypt only).
        SslCertificate::query()
            ->where('provider', 'letsencrypt')
            ->where('auto_renew', true)
            ->where('status', 'active')
            ->where('expires_at', '<=', now()->addDays(30))
            ->each(function (SslCertificate $certificate) {
                $certificate->update(['status' => 'pending']);
                InstallSslCertificateJob::dispatch($certificate->id)->onQueue('operations');
            });

        Queue::assertNotPushed(InstallSslCertificateJob::class);
        Queue::assertNotPushed(InstallCustomSslCertificateJob::class);
        $this->assertSame('active', $site->sslCertificates()->sole()->fresh()->status);
    }

    public function test_lets_encrypt_renew_still_dispatches_for_le_certs(): void
    {
        Queue::fake();
        [$user, , $site] = $this->infrastructure();

        $certificate = $site->sslCertificates()->create([
            'user_id' => $user->id,
            'domains' => [$site->domain],
            'provider' => 'letsencrypt',
            'status' => 'active',
            'auto_renew' => true,
            'expires_at' => now()->addDays(10),
        ]);

        SslCertificate::query()
            ->where('provider', 'letsencrypt')
            ->where('auto_renew', true)
            ->where('status', 'active')
            ->where('expires_at', '<=', now()->addDays(30))
            ->each(function (SslCertificate $row) {
                $row->update(['status' => 'pending']);
                InstallSslCertificateJob::dispatch($row->id)->onQueue('operations');
            });

        $this->assertSame('pending', $certificate->fresh()->status);
        Queue::assertPushedOn('operations', InstallSslCertificateJob::class);
    }

    public function test_remote_paths_differ_for_custom_and_letsencrypt(): void
    {
        [$user, , $site] = $this->infrastructure();

        $custom = $site->sslCertificates()->create([
            'user_id' => $user->id,
            'domains' => [$site->domain],
            'provider' => 'custom',
            'status' => 'active',
        ]);
        $le = $site->sslCertificates()->create([
            'user_id' => $user->id,
            'domains' => [$site->domain],
            'provider' => 'letsencrypt',
            'status' => 'pending',
        ]);

        $this->assertSame('/etc/ssl/clouddeck/app.example.com/fullchain.pem', $custom->remoteCertificatePath());
        $this->assertSame('/etc/ssl/clouddeck/app.example.com/privkey.pem', $custom->remotePrivateKeyPath());
        $this->assertSame('/etc/letsencrypt/live/app.example.com/fullchain.pem', $le->remoteCertificatePath());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fixturePemPair(): array
    {
        return [
            trim((string) file_get_contents(base_path('tests/fixtures/ssl-fullchain.pem'))),
            trim((string) file_get_contents(base_path('tests/fixtures/ssl-privkey.pem'))),
        ];
    }

    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => 'active']);

        return [$user, $server, $site];
    }
}
