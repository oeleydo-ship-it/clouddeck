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

class BackupWordPressSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $backupId) {}

    public function handle(SshClient $ssh): void
    {
        $backup = SiteBackup::with('site.server.sshKey')->findOrFail($this->backupId);
        $backup->update(['status' => 'running']);

        $output = $ssh->runScript($backup->site->server, resource_path('scripts/wp-backup.sh'), [
            'DOMAIN' => $backup->site->domain,
            'LABEL' => $backup->label,
        ]);

        preg_match('/CLOUDDECK_BACKUP_BYTES=(\d+)/', $output, $bytes);
        $backup->update([
            'status' => 'completed',
            'size' => isset($bytes[1]) ? (int) $bytes[1] : null,
            'completed_at' => now(),
            'failure_reason' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        SiteBackup::find($this->backupId)?->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);
    }
}
