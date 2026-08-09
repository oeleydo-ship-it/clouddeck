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

class CreateDatabaseJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public readonly string $databaseId) {}

    public function handle(SshClient $ssh): void
    {
        $database = ManagedDatabase::with(['server.sshKey', 'site'])->findOrFail($this->databaseId);
        $database->update(['status' => 'creating']);
        $ssh->runScript($database->server, resource_path('scripts/manage-database.sh'), ['ACTION' => 'create', 'ENGINE' => $database->engine, 'DATABASE' => $database->name, 'USERNAME' => $database->username, 'PASSWORD' => $database->password]);
        $database->update(['status' => 'ready', 'failure_reason' => null]);
        $database->load('site');
        $database->syncAttachedSiteEnvironment();
    }

    public function failed(Throwable $e): void
    {
        ManagedDatabase::find($this->databaseId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
