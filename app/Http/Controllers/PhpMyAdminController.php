<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Jobs\Servers\ManagePhpMyAdminJob;
use App\Models\Server;
use App\Services\ServerPortRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhpMyAdminController extends Controller
{
    public function store(Request $request, Server $server, ServerPortRegistry $ports): RedirectResponse
    {
        $this->authorize('update', $server);
        abort_unless($server->status === ServerStatus::Ready, 422, 'The server must be ready before installing phpMyAdmin.');
        $data = $request->validate(['port' => ['nullable', 'integer', 'between:1024,65535']]);
        $port = (int) ($data['port'] ?? $ports->allocate($server, ServerPortRegistry::PHPMYADMIN_DEFAULT));

        if ($port === ServerPortRegistry::REVERB_DEFAULT) {
            throw ValidationException::withMessages(['port' => 'Port '.ServerPortRegistry::REVERB_DEFAULT.' is reserved: it is the default Laravel Reverb binds to, so phpMyAdmin here would break "php artisan reverb:start".']);
        }
        if ($conflict = $ports->conflict($server, $port)) {
            throw ValidationException::withMessages(['port' => 'Port '.$port.' is already used by '.$conflict.' on this server.']);
        }

        $server->update(['phpmyadmin_port' => $port]);
        $operation = $server->operations()->create(['user_id' => $request->user()->id, 'type' => 'phpmyadmin:install', 'status' => 'pending']);
        ManagePhpMyAdminJob::dispatch($operation->id);

        return back()->with('status', 'phpMyAdmin installation queued on port '.$port.'.');
    }

    public function destroy(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        $operation = $server->operations()->create(['user_id' => $request->user()->id, 'type' => 'phpmyadmin:remove', 'status' => 'pending']);
        ManagePhpMyAdminJob::dispatch($operation->id);

        return back()->with('status', 'phpMyAdmin removal queued.');
    }
}
