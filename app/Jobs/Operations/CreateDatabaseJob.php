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
        if ($database->site) {
            foreach (['DB_CONNECTION' => $database->engine === 'postgresql' ? 'pgsql' : 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => $database->engine === 'postgresql' ? '5432' : '3306', 'DB_DATABASE' => $database->name, 'DB_USERNAME' => $database->username, 'DB_PASSWORD' => $database->password] as $key => $value) {
                $database->site->environmentVariables()->updateOrCreate(['key' => $key], ['value' => $value, 'is_secret' => $key === 'DB_PASSWORD']);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        ManagedDatabase::find($this->databaseId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
