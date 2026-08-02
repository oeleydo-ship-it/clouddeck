<?php

namespace App\Jobs\Backups;

use App\Models\BackupRestore;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RestoreDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(public readonly string $restoreId) {}

    public function handle(SshClient $ssh): void
    {
        $restore = BackupRestore::with(['backup', 'database.server.sshKey'])->findOrFail($this->restoreId);
        $restore->update(['status' => 'running', 'started_at' => now()]);
        $backup = $restore->backup;
        if ($backup->status !== 'ready' || ! $backup->disk_path || ! Storage::disk($backup->disk)->exists($backup->disk_path)) {
            throw new RuntimeException('The selected backup is no longer available.');
        }
        $database = $restore->database;
        $ssh->runScript($database->server, resource_path('scripts/import-database.sh'), ['ENGINE' => $database->engine, 'DATABASE' => $database->name, 'SQL_BASE64' => base64_encode(Storage::disk($backup->disk)->get($backup->disk_path))]);
        $restore->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        BackupRestore::find($this->restoreId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
