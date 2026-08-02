<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\ServerStatus;
use App\Jobs\Deployments\DeployWordPressJob;
use App\Models\CloudAccount;
use App\Models\Deployment;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WordPressConfig;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentCancelTest extends TestCase
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
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'blog.example.com', 'platform' => 'wordpress', 'php_version' => '8.4', 'status' => 'active', 'webhook_secret' => Str::random(64)]);
        foreach (['DB_DATABASE' => 'blog', 'DB_USERNAME' => 'blog_user', 'DB_PASSWORD' => 'secret'] as $k => $v) {
            $site->environmentVariables()->create(['key' => $k, 'value' => $v, 'is_secret' => false]);
        }

        return [$user, $site];
    }

    private function pending(Site $site, User $user): Deployment
    {
        return $site->deployments()->create(['user_id' => $user->id, 'status' => DeploymentStatus::Pending, 'trigger' => 'manual']);
    }

    public function test_a_deployment_stuck_pending_can_be_cancelled(): void
    {
        [$user, $site] = $this->site();
        $deployment = $this->pending($site, $user);

        $this->actingAs($user)->post(route('deployments.cancel', $deployment))->assertRedirect()->assertSessionHas('status');

        $deployment->refresh();
        $this->assertSame(DeploymentStatus::Cancelled, $deployment->status);
        $this->assertNotNull($deployment->finished_at);
        $this->assertStringContainsString('Cancelled by', $deployment->logs()->latest()->first()->output);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deployment.cancelled']);
    }

    public function test_cancelling_releases_the_lock_that_blocked_every_later_deployment(): void
    {
        Queue::fake();
        [$user, $site] = $this->site();
        $deployment = $this->pending($site, $user);

        // This is the trap: one job that never reached a worker locked the site out for good.
        $this->actingAs($user)->post("/sites/{$site->id}/deployments")->assertSessionHasErrors('deployment');

        $this->actingAs($user)->post(route('deployments.cancel', $deployment));
        $this->actingAs($user)->post("/sites/{$site->id}/deployments")->assertSessionHasNoErrors();

        Queue::assertPushedOn('deployments', DeployWordPressJob::class);
    }

    public function test_deploying_again_starts_a_fresh_deployment_from_the_old_one(): void
    {
        Queue::fake();
        [$user, $site] = $this->site();
        $failed = $site->deployments()->create(['user_id' => $user->id, 'status' => DeploymentStatus::Failed, 'trigger' => 'manual', 'finished_at' => now()]);

        $this->actingAs($user)->post(route('deployments.retry', $failed))->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, $site->deployments()->count());
        $this->assertSame(1, $site->deployments()->where('status', DeploymentStatus::Pending)->count());
        Queue::assertPushedOn('deployments', DeployWordPressJob::class);
    }

    public function test_a_finished_deployment_cannot_be_cancelled(): void
    {
        [$user, $site] = $this->site();
        $done = $site->deployments()->create(['user_id' => $user->id, 'status' => DeploymentStatus::Successful, 'trigger' => 'manual', 'finished_at' => now()]);

        // The page renders its buttons at load but updates status live, so this button can
        // outlive the deployment it belongs to.
        $this->actingAs($user)->post(route('deployments.cancel', $done))->assertSessionHasErrors('deployment');
        $this->assertSame(DeploymentStatus::Successful, $done->fresh()->status);
    }

    public function test_a_cancelled_deployment_is_not_resurrected_if_its_job_runs_later(): void
    {
        Process::fake();
        [$user, $site] = $this->site();
        $deployment = $this->pending($site, $user);
        $deployment->update(['status' => DeploymentStatus::Cancelled, 'finished_at' => now()]);

        // The job can still be sitting in the queue when the cancellation happens.
        (new DeployWordPressJob($deployment->id))->handle(app(SshClient::class), app(WordPressConfig::class));

        $this->assertSame(DeploymentStatus::Cancelled, $deployment->fresh()->status);
        $this->assertNull($site->fresh()->last_deployed_at);
        Process::assertNothingRan();
    }

    public function test_a_stranger_cannot_cancel_or_restart_someone_elses_deployment(): void
    {
        [$user, $site] = $this->site();
        $deployment = $this->pending($site, $user);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->post(route('deployments.cancel', $deployment))->assertForbidden();
        $this->actingAs($stranger)->post(route('deployments.retry', $deployment))->assertForbidden();
        $this->assertSame(DeploymentStatus::Pending, $deployment->fresh()->status);
    }
}
