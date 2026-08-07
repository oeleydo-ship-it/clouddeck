<?php

namespace App\Http\Controllers;

use App\Jobs\Operations\RunServerOperationJob;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerOperationController extends Controller
{
    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        $data = $request->validate([
            'type' => ['required', Rule::in([
                'nginx:test', 'nginx:reload', 'nginx:restart',
                'php:reload', 'php:restart',
                'supervisor:restart', 'redis:restart', 'mysql:restart',
                'system:harden', 'system:update', 'system:release-upgrade',
            ])],
            'confirmation' => ['nullable', 'string'],
        ]);

        if ($data['type'] === 'system:release-upgrade') {
            $request->validate(['confirmation' => ['required', Rule::in([$server->hostname])]]);
        }

        $operation = $server->operations()->create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'target' => strtok($data['type'], ':'),
        ]);
        RunServerOperationJob::dispatch($operation->id)->onQueue('operations');

        return back()->with('status', 'Server operation queued.');
    }
}
