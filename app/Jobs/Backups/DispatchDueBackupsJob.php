<?php

namespace App\Jobs\Backups;

use App\Jobs\Operations\ExportDatabaseJob;
use App\Models\BackupPolicy;
use App\Models\User;
use App\Services\BackupSchedule;
use App\Services\FeatureManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DispatchDueBackupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BackupSchedule $schedule, FeatureManager $features): void
    {
        BackupPolicy::query()->where('enabled', true)->where('next_run_at', '<=', now())->eachById(function (BackupPolicy $policy) use ($schedule, $features): void {
            DB::transaction(function () use ($policy, $schedule, $features): void {
                $locked = BackupPolicy::lockForUpdate()->find($policy->id);
                if (! $locked || ! $locked->enabled || ! $locked->next_run_at?->isPast()) {
                    return;
                }

                $owner = User::query()->find($locked->user_id);
                if (! $owner || ! $features->enabled(FeatureManager::forBackupType($locked->type), $owner)) {
                    // Downgraded plans keep the policy row but must not create new recovery points.
                    $locked->update(['next_run_at' => $schedule->next($locked, now())]);

                    return;
                }

                if ($locked->type === 'snapshot') {
                    try {
                        app(\App\Services\QuotaManager::class)->assertCanCreate($owner, 'os_backup_gb', 1);
                    } catch (\Illuminate\Validation\ValidationException) {
                        $locked->update(['next_run_at' => $schedule->next($locked, now())]);

                        return;
                    }
                }

                $locked->update(['last_run_at' => now(), 'next_run_at' => $schedule->next($locked, now())]);
                if ($locked->type === 'database') {
                    $backup = $locked->database->backups()->create([
                        'user_id' => $locked->user_id,
                        'backup_policy_id' => $locked->id,
                        'type' => 'export',
                        'source' => 'scheduled',
                        'disk' => $locked->disk ?: config('remote_management.database_backup_disk'),
                    ]);
                    ExportDatabaseJob::dispatch($backup->id)->onQueue('operations');
                } else {
                    $snapshot = $locked->server->snapshots()->create([
                        'user_id' => $locked->user_id,
                        'backup_policy_id' => $locked->id,
                        'name' => $locked->server->hostname.'-'.now()->utc()->format('Ymd-His'),
                    ]);
                    CreateServerSnapshotJob::dispatch($snapshot->id)->onQueue('operations');
                }
            });
        }, 100);
    }
}
