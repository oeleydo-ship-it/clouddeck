<?php

namespace App\Jobs\Backups;

use App\Models\BackupPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PruneBackupRetentionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $policyId) {}

    public function handle(): void
    {
        $policy = BackupPolicy::withTrashed()->find($this->policyId);
        if (! $policy) {
            return;
        }

        if ($policy->type === 'database') {
            $policy->databaseBackups()->where('status', 'ready')->latest('completed_at')->skip($policy->retention_count)->take(500)->get()->each(function ($backup): void {
                if ($backup->disk_path) {
                    Storage::disk($backup->disk)->delete($backup->disk_path);
                }
                $backup->update(['status' => 'expired', 'disk_path' => null, 'expires_at' => now()]);
            });

            return;
        }

        $policy->snapshots()->where('status', 'ready')->latest('completed_at')->skip($policy->retention_count)->take(500)->get()->each(fn ($snapshot) => DeleteServerSnapshotJob::dispatch($snapshot->id)->onQueue('operations'));
    }
}
