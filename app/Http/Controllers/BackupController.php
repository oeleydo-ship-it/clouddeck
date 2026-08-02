<?php

namespace App\Http\Controllers;

use App\Jobs\Backups\CreateServerSnapshotJob;
use App\Jobs\Backups\DeleteServerSnapshotJob;
use App\Jobs\Backups\RestoreDatabaseBackupJob;
use App\Jobs\Backups\RestoreServerSnapshotJob;
use App\Jobs\Operations\ExportDatabaseJob;
use App\Models\BackupPolicy;
use App\Models\DatabaseBackup;
use App\Models\Server;
use App\Models\ServerSnapshot;
use App\Services\AuditLogger;
use App\Services\BackupSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BackupController extends Controller
{
    public function store(Request $request, Server $server, BackupSchedule $schedule, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $server);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['database', 'snapshot'])],
            'managed_database_id' => ['nullable', 'required_if:type,database', 'uuid', Rule::exists('managed_databases', 'id')->where('server_id', $server->id)],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'run_at' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'weekday' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,28'],
            'retention_count' => ['required', 'integer', 'between:1,100'],
            'disk' => ['nullable', Rule::in($this->privateDisks())],
        ]);
        if ($data['type'] === 'snapshot') {
            abort_unless($server->provider_id, 422, 'Server is not active at its provider.');
        }
        $policy = $server->backupPolicies()->make([...$data, 'user_id' => $request->user()->id, 'enabled' => true]);
        $policy->next_run_at = $schedule->next($policy);
        $policy->save();
        $audit->record($request, 'backup-policy.created', $policy, [], $policy->only(['name', 'type', 'frequency', 'retention_count']));

        return back()->with('status', 'Backup policy created.');
    }

    public function toggle(Request $request, BackupPolicy $backupPolicy, BackupSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $backupPolicy->server);
        $enabled = ! $backupPolicy->enabled;
        $backupPolicy->update(['enabled' => $enabled, 'next_run_at' => $enabled ? $schedule->next($backupPolicy) : null]);

        return back()->with('status', $enabled ? 'Backup policy enabled.' : 'Backup policy disabled.');
    }

    public function destroy(Request $request, BackupPolicy $backupPolicy, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $backupPolicy->server);
        $old = $backupPolicy->only(['name', 'type', 'frequency', 'retention_count']);
        $backupPolicy->delete();
        $audit->record($request, 'backup-policy.deleted', $backupPolicy, $old, []);

        return back()->with('status', 'Backup policy removed. Existing recovery points were preserved.');
    }

    public function run(Request $request, BackupPolicy $backupPolicy): RedirectResponse
    {
        $this->authorize('update', $backupPolicy->server);
        if ($backupPolicy->type === 'database') {
            $backup = $backupPolicy->database->backups()->create(['user_id' => $request->user()->id, 'backup_policy_id' => $backupPolicy->id, 'type' => 'export', 'source' => 'manual', 'disk' => $backupPolicy->disk ?: config('remote_management.database_backup_disk')]);
            ExportDatabaseJob::dispatch($backup->id)->onQueue('operations');
        } else {
            $snapshot = $backupPolicy->server->snapshots()->create(['user_id' => $request->user()->id, 'backup_policy_id' => $backupPolicy->id, 'name' => $backupPolicy->server->hostname.'-'.now()->utc()->format('Ymd-His')]);
            CreateServerSnapshotJob::dispatch($snapshot->id)->onQueue('operations');
        }

        return back()->with('status', 'Backup queued.');
    }

    public function restoreDatabase(Request $request, DatabaseBackup $databaseBackup, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $databaseBackup->database->server);
        $request->validate(['confirmation' => ['required', Rule::in([$databaseBackup->database->name])]]);
        abort_unless($databaseBackup->status === 'ready' && $databaseBackup->disk_path && Storage::disk($databaseBackup->disk)->exists($databaseBackup->disk_path), 422, 'Backup is unavailable.');
        $restore = $databaseBackup->restores()->create(['user_id' => $request->user()->id, 'managed_database_id' => $databaseBackup->managed_database_id]);
        RestoreDatabaseBackupJob::dispatch($restore->id)->onQueue('operations');
        $audit->record($request, 'database-backup.restore-queued', $databaseBackup, [], ['restore_id' => $restore->id, 'database_id' => $databaseBackup->managed_database_id]);

        return back()->with('status', 'Database restore queued.');
    }

    public function snapshot(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        abort_unless($server->provider_id, 422, 'Server is not active at its provider.');
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/']]);
        $snapshot = $server->snapshots()->create(['user_id' => $request->user()->id, 'name' => $data['name']]);
        CreateServerSnapshotJob::dispatch($snapshot->id)->onQueue('operations');

        return back()->with('status', 'Provider snapshot queued.');
    }

    public function restoreSnapshot(Request $request, ServerSnapshot $serverSnapshot, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $serverSnapshot->server);
        $request->validate(['confirmation' => ['required', Rule::in([$serverSnapshot->server->hostname])]]);
        abort_unless($serverSnapshot->status === 'ready' && $serverSnapshot->provider_snapshot_id, 422, 'Snapshot is not ready.');
        RestoreServerSnapshotJob::dispatch($serverSnapshot->id)->onQueue('operations');
        $audit->record($request, 'server-snapshot.restore-queued', $serverSnapshot, [], ['server_id' => $serverSnapshot->server_id]);

        return back()->with('status', 'Destructive server restore queued.');
    }

    public function destroySnapshot(Request $request, ServerSnapshot $serverSnapshot): RedirectResponse
    {
        $this->authorize('update', $serverSnapshot->server);
        DeleteServerSnapshotJob::dispatch($serverSnapshot->id)->onQueue('operations');

        return back()->with('status', 'Snapshot deletion queued.');
    }

    private function privateDisks(): array
    {
        return collect(config('filesystems.disks'))
            ->reject(fn (array $disk) => ($disk['visibility'] ?? null) === 'public')
            ->keys()
            ->all();
    }
}
