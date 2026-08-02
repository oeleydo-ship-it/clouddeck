<?php

namespace App\Jobs\Operations;

use App\Models\CronJob;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncCronJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public readonly string $cronJobId) {}

    public function handle(SshClient $ssh): void
    {
        $cron = CronJob::with('server.sshKey')->withTrashed()->findOrFail($this->cronJobId);
        $ssh->runScript($cron->server, resource_path('scripts/sync-cron.sh'), ['ID' => $cron->id, 'ENABLED' => ! $cron->trashed() && $cron->enabled ? 'yes' : 'no', 'EXPRESSION' => $cron->expression, 'COMMAND' => $cron->command]);
        if (! $cron->trashed()) {
            $cron->update(['status' => $cron->enabled ? 'active' : 'disabled']);
        }
    }

    public function failed(Throwable $e): void
    {
        CronJob::withTrashed()->find($this->cronJobId)?->update(['status' => 'failed']);
    }
}
