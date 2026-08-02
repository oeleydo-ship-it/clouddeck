<?php

namespace App\Jobs\Backups;

use App\Jobs\Operations\ExportDatabaseJob;
use App\Models\BackupPolicy;
use App\Services\BackupSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DispatchDueBackupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BackupSchedule $schedule): void
    {
        BackupPolicy::query()->where('enabled', true)->where('next_run_at', '<=', now())->eachById(function (BackupPolicy $policy) use ($schedule): void {
            DB::transaction(function () use ($policy, $schedule): void {
                $locked = BackupPolicy::lockForUpdate()->find($policy->id);
                if (! $locked || ! $locked->enabled || ! $locked->next_run_at?->isPast()) {
                    return;
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
