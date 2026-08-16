<?php

namespace App\Jobs\Operations;

use App\Models\ManagedDatabase;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeleteDatabaseJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public readonly string $databaseId) {}

    public function handle(SshClient $ssh): void
    {
        $database = ManagedDatabase::with(['server.sshKey', 'site'])->findOrFail($this->databaseId);
        $site = $database->site;
        $database->update(['status' => 'deleting']);
        $ssh->runScript($database->server, resource_path('scripts/manage-database.sh'), ['ACTION' => 'delete', 'ENGINE' => $database->engine, 'DATABASE' => $database->name, 'USERNAME' => $database->username, 'PASSWORD' => '']);
        if ($site) {
            $site->environmentVariables()->whereIn('key', ManagedDatabase::ENVIRONMENT_KEYS)->delete();
        }
        $database->update(['status' => 'deleted']);
        $database->delete();
    }

    public function failed(Throwable $e): void
    {
        ManagedDatabase::find($this->databaseId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
