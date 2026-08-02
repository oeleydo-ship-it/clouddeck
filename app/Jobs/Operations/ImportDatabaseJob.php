<?php

namespace App\Jobs\Operations;

use App\Models\DatabaseBackup;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportDatabaseJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $timeout = 900;

    public function __construct(public readonly string $backupId) {}

    public function handle(SshClient $ssh): void
    {
        $backup = DatabaseBackup::with('database.server.sshKey')->findOrFail($this->backupId);
        $backup->update(['status' => 'running']);
        $database = $backup->database;
        $ssh->runScript($database->server, resource_path('scripts/import-database.sh'), ['ENGINE' => $database->engine, 'DATABASE' => $database->name, 'SQL_BASE64' => base64_encode(Storage::disk($backup->disk)->get($backup->disk_path))]);
        $backup->update(['status' => 'completed']);
        Storage::disk($backup->disk)->delete($backup->disk_path);
        $backup->update(['disk_path' => null]);
    }

    public function failed(Throwable $e): void
    {
        DatabaseBackup::find($this->backupId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
