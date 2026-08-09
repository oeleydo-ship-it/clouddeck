<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Sites\BackupApplicationSiteJob;
use App\Jobs\Sites\RestoreApplicationSiteJob;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Site;
use App\Models\SiteBackup;
use App\Models\SshKey;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteApplicationBackupTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Site} */
    private function customLaravelSite(array $featureOverrides = []): array
    {
        $features = array_merge(
            array_fill_keys(array_keys(config('plan-features.labels')), true),
            $featureOverrides,
        );
        $plan = Plan::create([
            'name' => 'Site Backup Plan',
            'slug' => 'site-backup-plan-'.Str::random(6),
            'monthly_price' => 2900,
            'yearly_price' => 29000,
            'currency' => 'USD',
            'limits' => ['servers' => 5, 'sites' => 10, 'databases' => 10, 'api_tokens' => 5, 'teams' => 2, 'team_members' => 5],
            'features' => $features,
            'active' => true,
            'public' => true,
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'manual', 'status' => 'active']);
        $key = SshKey::create([
            'user_id' => $user->id,
            'name' => 'Custom',
            'public_key' => 'ssh-ed25519 AAAA test',
            'private_key' => 'private-key',
        ]);
        $server = Server::create([
            'user_id' => $user->id,
            'ssh_key_id' => $key->id,
            'name' => 'BYO',
            'hostname' => 'byo-01',
            'region' => 'custom',
            'size' => 'custom',
            'image' => 'ubuntu-24-04-x64',
            'public_ip' => '203.0.113.10',
            'provider_id' => null,
            'status' => ServerStatus::Ready,
        ]);
        $site = Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'app.example.com',
            'platform' => 'laravel',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
            'webhook_secret' => Str::random(64),
        ]);

        return [$user, $site];
    }

    public function test_entitled_user_can_queue_full_backup_for_laravel_on_custom_server(): void
    {
        Queue::fake();
        [$user, $site] = $this->customLaravelSite(['site_backups' => true]);

        $this->assertNull($site->server->provider_id);

        $this->actingAs($user)->post(route('site-backups.store', $site))
            ->assertRedirect()
            ->assertSessionHas('status');

        $backup = SiteBackup::firstOrFail();
        $this->assertSame('full_app', $backup->kind);
        $this->assertSame('pending', $backup->status);
        $this->assertSame($site->id, $backup->site_id);
        Queue::assertPushedOn('operations', BackupApplicationSiteJob::class);
    }

    public function test_gated_user_is_redirected_when_creating_full_backup(): void
    {
        Queue::fake();
        [$user, $site] = $this->customLaravelSite(['site_backups' => false]);

        $this->actingAs($user)->post(route('site-backups.store', $site))
            ->assertRedirect(route('billing.index'));

        $this->assertDatabaseCount('site_backups', 0);
        Queue::assertNothingPushed();
    }

    public function test_laravel_site_show_includes_backups_tab(): void
    {
        [$user, $site] = $this->customLaravelSite(['site_backups' => true]);

        $this->actingAs($user)->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('Create full backup', false)
            ->assertSee('Full site backups', false);
    }

    public function test_restore_requires_domain_confirmation(): void
    {
        Queue::fake();
        [$user, $site] = $this->customLaravelSite(['site_backups' => true]);
        $backup = $site->backups()->create([
            'user_id' => $user->id,
            'label' => '20260809-120000',
            'kind' => 'full_app',
            'source' => 'manual',
            'disk' => 'local',
            'disk_path' => 'site-backups/ready.tar.gz',
            'status' => 'ready',
        ]);
        Storage::fake('local');
        Storage::disk('local')->put('site-backups/ready.tar.gz', 'archive');

        $this->actingAs($user)->post(route('site-backups.restore', $backup), [
            'confirmation' => 'wrong.example.com',
        ])->assertSessionHasErrors('confirmation');

        Queue::assertNotPushed(RestoreApplicationSiteJob::class);

        $this->actingAs($user)->post(route('site-backups.restore', $backup), [
            'confirmation' => $site->domain,
        ])->assertRedirect()->assertSessionHas('status');

        Queue::assertPushedOn('operations', RestoreApplicationSiteJob::class);
    }

    public function test_download_only_when_ready_with_disk_path(): void
    {
        Storage::fake('local');
        [$user, $site] = $this->customLaravelSite(['site_backups' => true]);

        $pending = $site->backups()->create([
            'user_id' => $user->id,
            'label' => 'pending',
            'kind' => 'full_app',
            'source' => 'manual',
            'status' => 'pending',
        ]);
        $this->actingAs($user)->get(route('site-backups.download', $pending))->assertNotFound();

        $ready = $site->backups()->create([
            'user_id' => $user->id,
            'label' => 'ready',
            'kind' => 'full_app',
            'source' => 'manual',
            'disk' => 'local',
            'disk_path' => 'site-backups/ready.tar.gz',
            'status' => 'ready',
        ]);
        Storage::disk('local')->put('site-backups/ready.tar.gz', 'archive-bytes');

        $this->actingAs($user)->get(route('site-backups.download', $ready))
            ->assertOk();
    }
}
