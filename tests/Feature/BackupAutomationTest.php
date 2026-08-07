<?php

namespace Tests\Feature;

use App\Cloud\CloudProviderManager;
use App\Enums\ServerStatus;
use App\Jobs\Backups\CreateServerSnapshotJob;
use App\Jobs\Backups\DispatchDueBackupsJob;
use App\Jobs\Backups\PruneBackupRetentionJob;
use App\Jobs\Backups\RefreshServerRestoreJob;
use App\Jobs\Backups\RefreshServerSnapshotJob;
use App\Jobs\Backups\RestoreDatabaseBackupJob;
use App\Jobs\Backups\RestoreServerSnapshotJob;
use App\Jobs\Operations\ExportDatabaseJob;
use App\Models\BackupPolicy;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use App\Services\BackupSchedule;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackupAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_timezone_aware_database_policy(): void
    {
        Carbon::setTestNow('2026-08-01 00:00:00 UTC');
        [$user, $server, $database] = $this->infrastructure();

        $this->actingAs($user)->post("/servers/{$server->id}/backup-policies", [
            'name' => 'Nightly', 'type' => 'database', 'managed_database_id' => $database->id,
            'frequency' => 'daily', 'run_at' => '02:00', 'timezone' => 'Asia/Dubai', 'retention_count' => 7,
        ])->assertSessionHas('status');

        $policy = BackupPolicy::firstOrFail();
        $this->assertSame($database->id, $policy->managed_database_id);
        $this->assertSame('2026-08-01 22:00:00', $policy->next_run_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_database_backup_policy_cannot_target_the_public_disk(): void
    {
        [$user, $server, $database] = $this->infrastructure();

        $this->actingAs($user)->post("/servers/{$server->id}/backup-policies", [
            'name' => 'Nightly', 'type' => 'database', 'managed_database_id' => $database->id,
            'frequency' => 'daily', 'run_at' => '02:00', 'timezone' => 'UTC', 'retention_count' => 7,
            'disk' => 'public',
        ])->assertSessionHasErrors('disk');

        $this->assertDatabaseMissing('backup_policies', ['server_id' => $server->id]);
    }

    public function test_due_policy_creates_recovery_point_once_and_advances_schedule(): void
    {
        Queue::fake();
        [$user, $server, $database] = $this->infrastructure();
        $policy = $server->backupPolicies()->create(['user_id' => $user->id, 'managed_database_id' => $database->id, 'name' => 'Daily', 'type' => 'database', 'frequency' => 'daily', 'run_at' => '02:00', 'timezone' => 'UTC', 'retention_count' => 3, 'enabled' => true, 'next_run_at' => now()->subMinute()]);

        (new DispatchDueBackupsJob)->handle(app(BackupSchedule::class));
        (new DispatchDueBackupsJob)->handle(app(BackupSchedule::class));

        $this->assertSame(1, $policy->databaseBackups()->count());
        $this->assertTrue($policy->fresh()->next_run_at->isFuture());
        Queue::assertPushedOn('operations', ExportDatabaseJob::class);
    }

    public function test_snapshot_jobs_track_provider_action_and_completed_snapshot(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        Http::fake([
            'https://api.digitalocean.com/v2/droplets/123/actions' => Http::response(['action' => ['id' => 91, 'status' => 'in-progress']]),
            'https://api.digitalocean.com/v2/droplets/123/actions/91' => Http::response(['action' => ['id' => 91, 'status' => 'completed']]),
            'https://api.digitalocean.com/v2/droplets/123/snapshots*' => Http::response(['snapshots' => [['id' => 55, 'name' => 'app-snapshot', 'size_gigabytes' => 4.5, 'created_at' => now()->toIso8601String()]]]),
        ]);
        $snapshot = $server->snapshots()->create(['user_id' => $user->id, 'name' => 'app-snapshot']);

        (new CreateServerSnapshotJob($snapshot->id))->handle(app(CloudProviderManager::class));
        $this->assertSame('91', $snapshot->fresh()->provider_action_id);
        (new RefreshServerSnapshotJob($snapshot->id))->handle(app(CloudProviderManager::class));

        $snapshot->refresh();
        $this->assertSame('ready', $snapshot->status);
        $this->assertSame('55', $snapshot->provider_snapshot_id);
        $this->assertSame('4.50', $snapshot->size_gigabytes);
    }

    public function test_database_restore_requires_name_and_preserves_source_backup(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$user, $server, $database] = $this->infrastructure();
        Storage::disk('local')->put('database-exports/backup.sql', 'CREATE TABLE restored (id INT);');
        $backup = $database->backups()->create(['user_id' => $user->id, 'type' => 'export', 'status' => 'ready', 'disk' => 'local', 'disk_path' => 'database-exports/backup.sql']);

        $this->actingAs($user)->post("/database-backups/{$backup->id}/restore", ['confirmation' => 'wrong'])->assertSessionHasErrors('confirmation');
        $this->actingAs($user)->post("/database-backups/{$backup->id}/restore", ['confirmation' => $database->name])->assertSessionHas('status');
        Queue::assertPushedOn('operations', RestoreDatabaseBackupJob::class);

        Process::fake(['*' => Process::result(output: 'restored', exitCode: 0)]);
        $restore = $backup->restores()->firstOrFail();
        (new RestoreDatabaseBackupJob($restore->id))->handle(app(SshClient::class));
        $this->assertSame('completed', $restore->fresh()->status);
        Storage::disk('local')->assertExists('database-exports/backup.sql');
        $this->assertDatabaseHas('audit_logs', ['action' => 'database-backup.restore-queued', 'auditable_id' => $backup->id]);
    }

    public function test_destructive_snapshot_restore_requires_hostname_and_tracks_completion(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $snapshot = $server->snapshots()->create(['user_id' => $user->id, 'name' => 'known-good', 'status' => 'ready', 'provider_snapshot_id' => '55']);

        $this->actingAs($user)->post("/server-snapshots/{$snapshot->id}/restore", ['confirmation' => 'wrong'])->assertSessionHasErrors('confirmation');
        $this->actingAs($user)->post("/server-snapshots/{$snapshot->id}/restore", ['confirmation' => $server->hostname])->assertSessionHas('status');
        Queue::assertPushedOn('operations', RestoreServerSnapshotJob::class);

        Http::fake([
            'https://api.digitalocean.com/v2/droplets/123/actions' => Http::response(['action' => ['id' => 100, 'status' => 'in-progress']]),
            'https://api.digitalocean.com/v2/droplets/123/actions/100' => Http::response(['action' => ['id' => 100, 'status' => 'completed']]),
        ]);
        (new RestoreServerSnapshotJob($snapshot->id))->handle(app(CloudProviderManager::class));
        $this->assertSame('provisioning', $server->fresh()->status->value);
        (new RefreshServerRestoreJob($server->id, '100'))->handle(app(CloudProviderManager::class));

        $server->refresh();
        $this->assertSame('ready', $server->status->value);
        $this->assertSame(100, $server->progress);
        $this->assertArrayNotHasKey('restore_action_id', $server->metadata);
        $this->assertDatabaseHas('audit_logs', ['action' => 'server-snapshot.restore-queued', 'auditable_id' => $snapshot->id]);
    }

    public function test_server_management_renders_backup_workspace(): void
    {
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->get("/servers/{$server->id}/manage?tab=backups")
            ->assertOk()
            ->assertSee('Automated backup policy')
            ->assertSee('Provider snapshots')
            ->assertSee('Storage disk')
            ->assertSee('Create snapshot');
    }

    public function test_custom_server_hides_provider_snapshot_controls(): void
    {
        [$user, $server] = $this->infrastructure();
        $server->update(['provider_id' => null, 'cloud_account_id' => null]);

        $this->actingAs($user)->get("/servers/{$server->id}/manage?tab=backups")
            ->assertOk()
            ->assertSee('database export policies')
            ->assertDontSee('>Create snapshot<', false);

        $this->actingAs($user)->post("/servers/{$server->id}/backup-policies", [
            'name' => 'Snap', 'type' => 'snapshot',
            'frequency' => 'daily', 'run_at' => '02:00', 'timezone' => 'UTC', 'retention_count' => 2,
        ])->assertStatus(422);
    }

    public function test_database_policy_can_select_a_private_storage_disk(): void
    {
        [$user, $server, $database] = $this->infrastructure();

        $this->actingAs($user)->post("/servers/{$server->id}/backup-policies", [
            'name' => 'Nightly', 'type' => 'database', 'managed_database_id' => $database->id,
            'frequency' => 'daily', 'run_at' => '02:00', 'timezone' => 'UTC', 'retention_count' => 7,
            'disk' => 'local',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('backup_policies', ['server_id' => $server->id, 'disk' => 'local']);
    }

    public function test_failed_database_export_notifies_the_owner(): void
    {
        Notification::fake();
        [$user, $server, $database] = $this->infrastructure();
        $backup = $database->backups()->create(['user_id' => $user->id, 'type' => 'export', 'status' => 'running', 'disk' => 'local']);

        (new ExportDatabaseJob($backup->id))->failed(new \RuntimeException('mysqldump failed'));

        $this->assertSame('failed', $backup->fresh()->status);
        $this->assertSame('mysqldump failed', $backup->fresh()->failure_reason);
        Notification::assertSentTo($user, OperationalEventNotification::class, fn ($n) => $n->event === 'backup_failed');
    }

    public function test_failed_snapshot_create_notifies_the_owner(): void
    {
        Notification::fake();
        [$user, $server] = $this->infrastructure();
        $snapshot = $server->snapshots()->create(['user_id' => $user->id, 'name' => 'app-snapshot', 'status' => 'creating']);

        (new CreateServerSnapshotJob($snapshot->id))->failed(new \RuntimeException('provider timeout'));

        $this->assertSame('failed', $snapshot->fresh()->status);
        Notification::assertSentTo($user, OperationalEventNotification::class, fn ($n) => $n->event === 'backup_failed' && str_contains($n->title, 'snapshot'));
    }

    public function test_failed_database_restore_notifies_the_owner(): void
    {
        Notification::fake();
        Storage::fake('local');
        [$user, $server, $database] = $this->infrastructure();
        Storage::disk('local')->put('database-exports/backup.sql', 'CREATE TABLE restored (id INT);');
        $backup = $database->backups()->create(['user_id' => $user->id, 'type' => 'export', 'status' => 'ready', 'disk' => 'local', 'disk_path' => 'database-exports/backup.sql']);
        $restore = $backup->restores()->create(['user_id' => $user->id, 'managed_database_id' => $database->id, 'status' => 'running']);

        (new RestoreDatabaseBackupJob($restore->id))->failed(new \RuntimeException('import failed'));

        $this->assertSame('failed', $restore->fresh()->status);
        Notification::assertSentTo($user, OperationalEventNotification::class, fn ($n) => $n->event === 'backup_failed' && str_contains($n->title, 'restore'));
    }

    public function test_retention_prunes_old_database_recovery_points(): void
    {
        Storage::fake('local');
        [$user, $server, $database] = $this->infrastructure();
        $policy = $server->backupPolicies()->create(['user_id' => $user->id, 'managed_database_id' => $database->id, 'name' => 'Daily', 'type' => 'database', 'frequency' => 'daily', 'run_at' => '02:00', 'timezone' => 'UTC', 'retention_count' => 2, 'enabled' => true]);
        foreach (range(1, 4) as $index) {
            $path = "database-exports/{$index}.sql";
            Storage::disk('local')->put($path, "backup {$index}");
            $policy->databaseBackups()->create(['user_id' => $user->id, 'managed_database_id' => $database->id, 'type' => 'export', 'status' => 'ready', 'disk' => 'local', 'disk_path' => $path, 'completed_at' => now()->subDays(5 - $index)]);
        }

        (new PruneBackupRetentionJob($policy->id))->handle();

        $this->assertSame(2, $policy->databaseBackups()->where('status', 'ready')->count());
        $this->assertSame(2, $policy->databaseBackups()->where('status', 'expired')->count());
    }

    public function test_backup_controls_are_tenant_scoped(): void
    {
        Queue::fake();
        [$owner, $server, $database] = $this->infrastructure();
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        $policy = $server->backupPolicies()->create(['user_id' => $owner->id, 'managed_database_id' => $database->id, 'name' => 'Daily', 'type' => 'database', 'frequency' => 'daily', 'run_at' => '02:00', 'timezone' => 'UTC', 'retention_count' => 2, 'enabled' => true]);

        $this->actingAs($intruder)->post("/backup-policies/{$policy->id}/run")->assertForbidden();
        $this->actingAs($intruder)->delete("/backup-policies/{$policy->id}")->assertForbidden();
    }

    public function test_backup_api_lists_only_authenticated_customers_resources(): void
    {
        [$user, $server, $database] = $this->infrastructure();
        $policy = $server->backupPolicies()->create(['user_id' => $user->id, 'managed_database_id' => $database->id, 'name' => 'Daily', 'type' => 'database', 'frequency' => 'daily', 'run_at' => '02:00', 'timezone' => 'UTC', 'retention_count' => 2, 'enabled' => true]);
        [$other, $otherServer, $otherDatabase] = $this->infrastructure();
        $otherServer->backupPolicies()->create(['user_id' => $other->id, 'managed_database_id' => $otherDatabase->id, 'name' => 'Hidden', 'type' => 'database', 'frequency' => 'daily', 'run_at' => '03:00', 'timezone' => 'UTC', 'retention_count' => 2, 'enabled' => true]);
        Sanctum::actingAs($user, ['servers:read']);

        $this->getJson('/api/backups')->assertOk()->assertJsonPath('policies.0.id', $policy->id)->assertJsonCount(1, 'policies');
    }

    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'provider_id' => '123', 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        $database = $server->databases()->create(['user_id' => $user->id, 'engine' => 'mysql', 'name' => 'application', 'username' => 'application_user', 'password' => 'secret', 'status' => 'ready']);

        return [$user, $server, $database];
    }
}
