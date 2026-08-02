<?php

namespace App\Jobs\Operations;

use App\Jobs\Backups\PruneBackupRetentionJob;
use App\Models\DatabaseBackup;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ExportDatabaseJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $timeout = 900;

    public function __construct(public readonly string $backupId) {}

    public function handle(SshClient $ssh): void
    {
        $backup = DatabaseBackup::with('database.server.sshKey')->findOrFail($this->backupId);
        $backup->update(['status' => 'running']);
        $database = $backup->database;
        $command = $database->engine === 'postgresql' ? "sudo -u postgres pg_dump --no-owner --no-acl {$database->name}" : "mysqldump --protocol=socket --single-transaction --routines --triggers {$database->name}";
        $temporary = tmpfile();
        if ($temporary === false) {
            throw new RuntimeException('Unable to allocate a temporary export stream.');
        }
        $hash = hash_init('sha256');
        $result = $ssh->runStreaming($database->server, $command, function (string $type, string $chunk) use ($temporary, $hash): void {
            if ($type === 'out') {
                fwrite($temporary, $chunk);
                hash_update($hash, $chunk);
            }
        });
        if ($result->failed()) {
            fclose($temporary);
            throw new RuntimeException($result->errorOutput());
        }
        if (fstat($temporary)['size'] === 0 && $result->output() !== '') {
            fwrite($temporary, $result->output());
            hash_update($hash, $result->output());
        }
        $path = "database-exports/{$backup->id}.sql";
        $size = fstat($temporary)['size'];
        rewind($temporary);
        if (! Storage::disk($backup->disk)->put($path, $temporary)) {
            fclose($temporary);
            throw new RuntimeException('Unable to persist the database export.');
        }
        fclose($temporary);
        $backup->update(['status' => 'ready', 'disk_path' => $path, 'size' => $size, 'checksum' => hash_final($hash), 'completed_at' => now()]);
        if ($backup->backup_policy_id) {
            PruneBackupRetentionJob::dispatch($backup->backup_policy_id)->onQueue('operations');
        }
    }

    public function failed(Throwable $e): void
    {
        DatabaseBackup::find($this->backupId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
