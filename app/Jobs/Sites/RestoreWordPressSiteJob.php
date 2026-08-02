<?php

namespace App\Jobs\Sites;

use App\Models\SiteBackup;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RestoreWordPressSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $backupId) {}

    public function handle(SshClient $ssh): void
    {
        $backup = SiteBackup::with('site.server.sshKey')->findOrFail($this->backupId);

        $ssh->runScript($backup->site->server, resource_path('scripts/wp-restore.sh'), [
            'DOMAIN' => $backup->site->domain,
            'LABEL' => $backup->label,
        ]);

        // The restored database decides what counts as installed now.
        CheckWordPressInstallJob::dispatch($backup->site_id)->onQueue('operations');
    }

    public function failed(Throwable $exception): void
    {
        SiteBackup::find($this->backupId)?->update(['failure_reason' => 'Restore failed: '.$exception->getMessage()]);
    }
}
