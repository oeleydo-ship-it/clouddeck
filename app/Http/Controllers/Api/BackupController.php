<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BackupPolicyResource;
use App\Http\Resources\DatabaseBackupResource;
use App\Http\Resources\ServerSnapshotResource;
use App\Jobs\Backups\CreateServerSnapshotJob;
use App\Jobs\Operations\ExportDatabaseJob;
use App\Models\BackupPolicy;
use App\Models\DatabaseBackup;
use App\Models\Server;
use App\Models\ServerSnapshot;
use App\Services\BackupSchedule;
use App\Services\FeatureManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BackupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $features = app(FeatureManager::class);
        abort_unless(
            $features->enabled('database_backups', $request->user())
            || $features->enabled('os_backups', $request->user()),
            403,
            'This feature is not enabled for your account.'
        );

        $serverIds = $request->user()->servers()->pluck('id');

        return response()->json([
            'policies' => BackupPolicyResource::collection(BackupPolicy::where('user_id', $request->user()->id)->whereIn('server_id', $serverIds)->latest()->get()),
            'database_backups' => DatabaseBackupResource::collection(DatabaseBackup::where('user_id', $request->user()->id)->where('type', 'export')->latest()->limit(100)->get()),
            'snapshots' => ServerSnapshotResource::collection(ServerSnapshot::where('user_id', $request->user()->id)->whereIn('server_id', $serverIds)->latest()->limit(100)->get()),
        ]);
    }

    public function store(Request $request, Server $server, BackupSchedule $schedule): BackupPolicyResource
    {
        $this->authorize('update', $server);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(['database', 'snapshot'])], 'managed_database_id' => ['nullable', 'required_if:type,database', 'uuid', Rule::exists('managed_databases', 'id')->where('server_id', $server->id)], 'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])], 'run_at' => ['required', 'date_format:H:i'], 'timezone' => ['required', 'timezone'], 'weekday' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'], 'day_of_month' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,28'], 'retention_count' => ['required', 'integer', 'between:1,100']]);

        abort_unless(
            app(FeatureManager::class)->enabled(FeatureManager::forBackupType($data['type']), $request->user()),
            403,
            'This feature is not enabled for your account.'
        );

        if ($data['type'] === 'snapshot') {
            abort_unless($server->provider_id, 422, 'Server is not active at its provider.');
            app(\App\Services\QuotaManager::class)->assertCanCreate($request->user(), 'os_backup_gb', 1);
        }
        $policy = $server->backupPolicies()->make([...$data, 'user_id' => $request->user()->id, 'enabled' => true, 'disk' => config('remote_management.database_backup_disk')]);
        $policy->next_run_at = $schedule->next($policy);
        $policy->save();

        return new BackupPolicyResource($policy);
    }

    public function run(Request $request, BackupPolicy $backupPolicy): JsonResponse
    {
        $this->authorize('update', $backupPolicy->server);
        abort_unless(
            app(FeatureManager::class)->enabled(FeatureManager::forBackupType($backupPolicy->type), $request->user()),
            403,
            'This feature is not enabled for your account.'
        );

        if ($backupPolicy->type === 'database') {
            $backup = $backupPolicy->database->backups()->create(['user_id' => $request->user()->id, 'backup_policy_id' => $backupPolicy->id, 'type' => 'export', 'source' => 'api', 'disk' => $backupPolicy->disk ?: config('remote_management.database_backup_disk')]);
            ExportDatabaseJob::dispatch($backup->id)->onQueue('operations');
        } else {
            app(\App\Services\QuotaManager::class)->assertCanCreate($request->user(), 'os_backup_gb', 1);
            $snapshot = $backupPolicy->server->snapshots()->create(['user_id' => $request->user()->id, 'backup_policy_id' => $backupPolicy->id, 'name' => $backupPolicy->server->hostname.'-'.now()->utc()->format('Ymd-His')]);
            CreateServerSnapshotJob::dispatch($snapshot->id)->onQueue('operations');
        }

        return response()->json(['message' => 'Backup queued.'], 202);
    }

    public function destroy(Request $request, BackupPolicy $backupPolicy): JsonResponse
    {
        $this->authorize('update', $backupPolicy->server);
        abort_unless(
            app(FeatureManager::class)->enabled(FeatureManager::forBackupType($backupPolicy->type), $request->user()),
            403,
            'This feature is not enabled for your account.'
        );

        $backupPolicy->delete();

        return response()->json([], 204);
    }
}
