<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Monitoring\NotifyExpiringCertificatesJob;
use App\Jobs\Sites\CheckSiteQueueHealthJob;
use App\Models\CloudAccount;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\SslCertificate;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalAlertTriggerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Site} */
    private function site(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = Plan::firstOrCreate(['slug' => 'unlimited'], ['name' => 'Unlimited', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1], 'features' => [], 'active' => true, 'public' => true]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'system', 'status' => 'active']);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'platform' => 'laravel', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => 'active', 'webhook_secret' => Str::random(64)]);

        return [$user, $site];
    }

    private function certificate(Site $site, $expiresAt, bool $autoRenew = true): SslCertificate
    {
        return $site->sslCertificates()->create([
            'user_id' => $site->user_id,
            'domains' => [$site->domain], 'status' => 'active', 'auto_renew' => $autoRenew,
            'force_https' => true, 'issued_at' => now()->subDays(60), 'expires_at' => $expiresAt,
        ]);
    }

    public function test_a_certificate_close_to_expiry_warns_its_owner(): void
    {
        Notification::fake();
        [$user, $site] = $this->site();
        $this->certificate($site, now()->addDays(5));

        (new NotifyExpiringCertificatesJob)->handle();

        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification) {
            $this->assertSame('ssl_expiring', $notification->event);
            $this->assertStringContainsString('app.example.com', $notification->title);

            return true;
        });
    }

    public function test_a_certificate_with_time_left_is_left_alone(): void
    {
        Notification::fake();
        [, $site] = $this->site();
        $this->certificate($site, now()->addDays(60));

        (new NotifyExpiringCertificatesJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_an_already_expired_certificate_is_not_warned_about_again(): void
    {
        Notification::fake();
        [, $site] = $this->site();
        $this->certificate($site, now()->subDay());

        // Past the point where a warning helps; the site is already failing and says so.
        (new NotifyExpiringCertificatesJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_the_warning_says_whether_renewal_is_even_attempted(): void
    {
        Notification::fake();
        [$user, $site] = $this->site();
        $this->certificate($site, now()->addDays(2), autoRenew: false);

        (new NotifyExpiringCertificatesJob)->handle();

        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification) {
            $this->assertStringContainsString('by hand', $notification->body);
            $this->assertSame('critical', $notification->severity);

            return true;
        });
    }

    public function test_a_growing_failed_job_count_notifies(): void
    {
        Notification::fake();
        Process::fake(['*' => Process::result(output: "3\n", exitCode: 0)]);
        [$user, $site] = $this->site();

        (new CheckSiteQueueHealthJob($site->id))->handle(app(SshClient::class));

        $this->assertSame(3, $site->fresh()->queue_failed_count);
        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification) {
            $this->assertSame('queue_failed', $notification->event);

            return true;
        });
    }

    public function test_the_same_failures_are_not_reported_over_and_over(): void
    {
        Process::fake(['*' => Process::result(output: "3\n", exitCode: 0)]);
        [, $site] = $this->site();
        (new CheckSiteQueueHealthJob($site->id))->handle(app(SshClient::class));

        Notification::fake();
        (new CheckSiteQueueHealthJob($site->id))->handle(app(SshClient::class));

        // Repeating an unchanged count every hour teaches the reader to ignore the channel.
        Notification::assertNothingSent();
    }

    public function test_an_empty_failed_table_says_nothing(): void
    {
        Notification::fake();
        Process::fake(['*' => Process::result(output: "0\n", exitCode: 0)]);
        [, $site] = $this->site();

        (new CheckSiteQueueHealthJob($site->id))->handle(app(SshClient::class));

        Notification::assertNothingSent();
    }
}
