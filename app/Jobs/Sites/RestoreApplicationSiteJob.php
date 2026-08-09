<?php

namespace App\Jobs\Sites;

use App\Models\SiteBackup;
use App\Notifications\OperationalEventNotification;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RestoreApplicationSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly string $backupId) {}

    public function handle(SshClient $ssh): void
    {
        $backup = SiteBackup::with(['site.server.sshKey', 'site.database'])->findOrFail($this->backupId);
        if (! $backup->isFullApp() || $backup->status !== 'ready' || ! $backup->disk_path) {
            throw new RuntimeException('That backup is not ready to restore.');
        }
        if (! Storage::disk($backup->disk)->exists($backup->disk_path)) {
            throw new RuntimeException('Backup archive is missing from storage.');
        }

        $site = $backup->site;
        $database = $site->database;
        $remotePath = '/tmp/uplary-site-restore-'.$backup->id.'.tar.gz';

        $stream = Storage::disk($backup->disk)->readStream($backup->disk_path);
        if ($stream === false) {
            throw new RuntimeException('Unable to open the site archive for restore.');
        }

        try {
            $ssh->putContents($site->server, $remotePath, $stream, 3600);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $ssh->runScript($site->server, resource_path('scripts/app-restore.sh'), [
            'DOMAIN' => $site->domain,
            'ARCHIVE_PATH' => $remotePath,
            'PHP_VERSION' => $site->php_version,
            'DB_ENGINE' => $database?->engine ?: 'mysql',
            'DB_NAME' => $database?->name ?: '',
        ], 3600);
    }

    public function failed(Throwable $exception): void
    {
        $backup = SiteBackup::with('site.user')->find($this->backupId);

        $backup?->site?->user?->notify(new OperationalEventNotification(
            event: 'backup_failed',
            title: 'Site restore failed for '.($backup->site->domain ?? 'site'),
            body: $exception->getMessage(),
            url: $backup?->site ? route('sites.show', ['site' => $backup->site, 'tab' => 'backups']) : null,
            severity: 'critical',
            context: ['backup_id' => $backup?->id, 'site_id' => $backup?->site_id],
        ));
    }
}
