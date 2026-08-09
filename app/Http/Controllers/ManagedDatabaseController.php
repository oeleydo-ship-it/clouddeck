<?php

namespace App\Http\Controllers;

use App\Jobs\Operations\CreateDatabaseJob;
use App\Jobs\Operations\DeleteDatabaseJob;
use App\Jobs\Operations\ExportDatabaseJob;
use App\Jobs\Operations\ImportDatabaseJob;
use App\Models\DatabaseBackup;
use App\Models\ManagedDatabase;
use App\Models\Server;
use App\Services\QuotaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagedDatabaseController extends Controller
{
    public function store(Request $request, Server $server, QuotaManager $quotas): RedirectResponse
    {
        $this->authorize('update', $server);
        $quotas->assertCanCreate($request->user(), 'databases');
        $data = $request->validate([
            'engine' => ['required', Rule::in(['mysql', 'postgresql'])],
            // Guarded here as well as by the index, so a name already on this server comes
            // back on the field instead of as a 500 from the database. Trashed rows are
            // ignored: deleting a database frees its name for the next one.
            'name' => [
                'required', 'regex:/^[a-z][a-z0-9_]{0,62}$/',
                Rule::unique('managed_databases', 'name')->where(fn ($query) => $query->where('server_id', $server->id)->where('engine', $request->input('engine'))->whereNull('deleted_at')),
            ],
            'username' => ['required', 'regex:/^[a-z][a-z0-9_]{0,30}$/'],
            'site_id' => ['nullable', 'uuid', Rule::exists('sites', 'id')->where('user_id', $request->user()->id)->where('server_id', $server->id)],
        ]);
        $password = Str::random(32);
        $database = $server->databases()->create([...$data, 'user_id' => $request->user()->id, 'password' => $password, 'status' => 'pending']);
        CreateDatabaseJob::dispatch($database->id)->onQueue('operations');

        return back()->with('status', 'Database creation queued.')->with('database_password', $password);
    }

    public function destroy(Request $request, ManagedDatabase $managedDatabase): RedirectResponse
    {
        $this->authorize('update', $managedDatabase->server);
        // Typing the name is required because dropping the schema on the server is permanent —
        // exports are separate and are not restored by undoing this action.
        $request->validate(['confirmation' => ['required', Rule::in([$managedDatabase->name])]]);
        $managedDatabase->update(['status' => 'deleting']);
        DeleteDatabaseJob::dispatch($managedDatabase->id)->onQueue('operations');

        return back()->with('status', 'Database deletion queued.');
    }

    public function export(Request $request, ManagedDatabase $managedDatabase): RedirectResponse
    {
        $this->authorize('view', $managedDatabase->server);
        abort_unless($managedDatabase->status === 'ready', 422);
        $backup = $managedDatabase->backups()->create(['user_id' => $request->user()->id, 'type' => 'export', 'disk' => config('remote_management.database_backup_disk')]);
        ExportDatabaseJob::dispatch($backup->id)->onQueue('operations');

        return back()->with('status', 'Database export queued.');
    }

    public function import(Request $request, ManagedDatabase $managedDatabase): RedirectResponse
    {
        $this->authorize('update', $managedDatabase->server);
        $data = $request->validate(['sql' => ['required', 'file', 'mimes:sql,txt', 'max:10240']]);
        $disk = config('remote_management.database_backup_disk');
        $path = $data['sql']->store('database-imports', $disk);
        $backup = $managedDatabase->backups()->create(['user_id' => $request->user()->id, 'type' => 'import', 'disk' => $disk, 'disk_path' => $path, 'size' => $data['sql']->getSize()]);
        ImportDatabaseJob::dispatch($backup->id)->onQueue('operations');

        return back()->with('status', 'Database import queued.');
    }

    public function download(Request $request, DatabaseBackup $databaseBackup): StreamedResponse
    {
        $this->authorize('view', $databaseBackup->database->server);
        abort_unless($databaseBackup->type === 'export' && $databaseBackup->status === 'ready' && $databaseBackup->disk_path, 404);

        return Storage::disk($databaseBackup->disk)->download($databaseBackup->disk_path, $databaseBackup->database->name.'-'.$databaseBackup->created_at->format('YmdHis').'.sql');
    }
}
