<?php

namespace App\Jobs\Sites;

use App\Models\SiteBackup;
use App\Notifications\OperationalEventNotification;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupApplicationSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly string $backupId) {}

    public function handle(SshClient $ssh): void
    {
        $backup = SiteBackup::with(['site.server.sshKey', 'site.database'])->findOrFail($this->backupId);
        if (! $backup->isFullApp()) {
            throw new RuntimeException('Only full application backups can be run by this job.');
        }
        $backup->update(['status' => 'running', 'failure_reason' => null]);

        $site = $backup->site;
        $database = $site->database;
        $output = $ssh->runScript($site->server, resource_path('scripts/app-backup.sh'), [
            'DOMAIN' => $site->domain,
            'BACKUP_ID' => $backup->id,
            'PLATFORM' => $site->isWordPress() ? 'wordpress' : 'laravel',
            'DB_ENGINE' => $database?->engine ?: 'mysql',
            'DB_NAME' => $database?->name ?: '',
        ], 3600);

        if (! preg_match('/CLOUDDECK_ARCHIVE_PATH=(\S+)/', $output, $pathMatch)) {
            throw new RuntimeException('Backup script did not report an archive path.');
        }
        $remotePath = $pathMatch[1];

        $stagingDir = storage_path('app/private/site-backup-staging');
        if (! is_dir($stagingDir) && ! mkdir($stagingDir, 0755, true) && ! is_dir($stagingDir)) {
            throw new RuntimeException('Unable to create local staging directory for the site archive.');
        }
        $localPath = $stagingDir.DIRECTORY_SEPARATOR.$backup->id.'.tar.gz';
        @unlink($localPath);

        $handle = fopen($localPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open a local staging file for the site archive.');
        }

        $hash = hash_init('sha256');
        try {
            $result = $ssh->runStreaming($site->server, 'cat '.escapeshellarg($remotePath), function (string $type, string $chunk) use ($handle, $hash): void {
                if ($type === 'out') {
                    fwrite($handle, $chunk);
                    hash_update($hash, $chunk);
                }
            }, 3600);
            if ($result->failed()) {
                throw new RuntimeException($result->errorOutput() ?: 'Failed to download the site archive.');
            }
        } finally {
            fclose($handle);
        }

        $size = filesize($localPath);
        if (! is_int($size) || $size < 1) {
            @unlink($localPath);
            throw new RuntimeException('Site archive was empty.');
        }

        $disk = $backup->disk ?: app(\App\Services\BackupStorage::class)->defaultDisk();
        $path = "site-backups/{$backup->id}.tar.gz";

        try {
            // File-based upload gives S3/Wasabi a known size (multipart-safe). Streaming a
            // php://temp resource can hang or fail on large archives.
            $stored = Storage::disk($disk)->putFileAs(
                'site-backups',
                new File($localPath),
                $backup->id.'.tar.gz',
            );
            if ($stored === false || $stored === null || $stored === '') {
                throw new RuntimeException('Unable to persist the site archive to '.$disk.'.');
            }
            $path = is_string($stored) ? $stored : $path;
        } finally {
            @unlink($localPath);
        }

        try {
            $ssh->run($site->server, 'rm -f '.escapeshellarg($remotePath));
        } catch (Throwable) {
            // Local/offsite copy is already stored; remote cleanup is best-effort.
        }

        $backup->update([
            'status' => 'ready',
            'disk' => $disk,
            'disk_path' => $path,
            'size' => $size,
            'checksum' => hash_final($hash),
            'completed_at' => now(),
            'failure_reason' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $backup = SiteBackup::with('site.user')->find($this->backupId);
        $backup?->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);

        $backup?->site?->user?->notify(new OperationalEventNotification(
            event: 'backup_failed',
            title: 'Site backup failed for '.($backup->site->domain ?? 'site'),
            body: $exception->getMessage(),
            url: $backup?->site ? route('sites.show', ['site' => $backup->site, 'tab' => 'backups']) : null,
            severity: 'critical',
            context: ['backup_id' => $backup?->id, 'site_id' => $backup?->site_id],
        ));
    }
}
