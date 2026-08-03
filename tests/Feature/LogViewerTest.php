<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Http\Controllers\LogController;
use App\Jobs\Sites\FetchLogJob;
use App\Livewire\LogViewer;
use App\Models\CloudAccount;
use App\Models\LogSnapshot;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LogViewerTest extends TestCase
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

    public function test_every_supported_log_can_be_requested(): void
    {
        Queue::fake();
        [$user, $site] = $this->site();

        foreach (array_keys(LogController::SOURCES) as $source) {
            $this->actingAs($user)->post(route('site-logs.store', $site), ['source' => $source])->assertSessionHas('status');
        }

        $this->assertSame(count(LogController::SOURCES), LogSnapshot::count());
        Queue::assertPushedOn('operations', FetchLogJob::class);
    }

    public function test_a_source_outside_the_list_is_refused(): void
    {
        Queue::fake();
        [$user, $site] = $this->site();

        // The browser names a source, never a path. Anything else would turn the log viewer
        // into a way to read any file the server can reach.
        foreach (['/etc/passwd', '../../etc/shadow', 'laravel;cat /etc/passwd', 'auth'] as $source) {
            $this->actingAs($user)->post(route('site-logs.store', $site), ['source' => $source])->assertSessionHasErrors('source');
        }

        Queue::assertNothingPushed();
    }

    public function test_the_line_count_is_bounded(): void
    {
        Queue::fake();
        [$user, $site] = $this->site();

        $this->actingAs($user)->post(route('site-logs.store', $site), ['source' => 'laravel', 'lines' => 100000])->assertSessionHasErrors('lines');
        $this->actingAs($user)->post(route('site-logs.store', $site), ['source' => 'laravel', 'lines' => 0])->assertSessionHasErrors('lines');
    }

    public function test_the_output_and_the_path_it_came_from_are_recorded(): void
    {
        Process::fake(['*' => Process::result(output: "CLOUDDECK_LOG_PATH=/var/log/nginx/app.example.com.error.log\n[error] upstream timed out\n", exitCode: 0)]);
        [$user, $site] = $this->site();
        $snapshot = $site->logSnapshots()->create(['server_id' => $site->server_id, 'user_id' => $user->id, 'source' => 'nginx', 'lines' => 200, 'status' => 'pending']);

        (new FetchLogJob($snapshot->id))->handle(app(SshClient::class));

        $snapshot->refresh();
        $this->assertSame('completed', $snapshot->status);
        $this->assertSame('/var/log/nginx/app.example.com.error.log', $snapshot->path);
        $this->assertStringContainsString('upstream timed out', $snapshot->output);
        // The marker is CloudDeck's own and has no business in what the operator reads.
        $this->assertStringNotContainsString('CLOUDDECK_LOG_PATH', $snapshot->output);
    }

    public function test_a_log_that_does_not_exist_is_reported_rather_than_failing(): void
    {
        Process::fake(['*' => Process::result(output: "CLOUDDECK_LOG_PATH=none\nNo redis log exists on this server yet.\n", exitCode: 0)]);
        [$user, $site] = $this->site();
        $snapshot = $site->logSnapshots()->create(['server_id' => $site->server_id, 'user_id' => $user->id, 'source' => 'redis', 'lines' => 200, 'status' => 'pending']);

        (new FetchLogJob($snapshot->id))->handle(app(SshClient::class));

        $snapshot->refresh();
        $this->assertSame('completed', $snapshot->status);
        $this->assertNull($snapshot->path);
        $this->assertStringContainsString('No redis log exists', $snapshot->output);
    }

    public function test_a_failed_read_says_why_instead_of_staying_pending(): void
    {
        [$user, $site] = $this->site();
        $snapshot = $site->logSnapshots()->create(['server_id' => $site->server_id, 'user_id' => $user->id, 'source' => 'laravel', 'lines' => 200, 'status' => 'running']);

        (new FetchLogJob($snapshot->id))->failed(new \RuntimeException('Connection refused'));

        $snapshot->refresh();
        $this->assertSame('failed', $snapshot->status);
        $this->assertStringContainsString('Connection refused', $snapshot->output);
    }

    public function test_the_component_bounds_what_the_browser_sends(): void
    {
        Queue::fake();
        [$user, $site] = $this->site();

        // Livewire properties are input like any other, so the controller's rules alone would
        // not cover this path.
        Livewire::actingAs($user)->test(LogViewer::class, ['site' => $site])
            ->set('lines', 99999)
            ->call('read')
            ->assertSet('lines', 2000);

        $this->assertSame(2000, LogSnapshot::sole()->lines);
    }

    public function test_a_stranger_cannot_read_someone_elses_logs(): void
    {
        Queue::fake();
        [, $site] = $this->site();
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->post(route('site-logs.store', $site), ['source' => 'laravel'])->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_the_script_reads_only_from_a_fixed_set_of_paths(): void
    {
        $script = file_get_contents(resource_path('scripts/read-log.sh'));

        $this->assertStringContainsString('Unsupported log source', $script);
        // No placeholder may carry a path: the source name is what selects the file.
        $this->assertStringNotContainsString('{{PATH}}', $script);
        $this->assertStringContainsString('tail -n "${LINES}"', $script);
    }
}
