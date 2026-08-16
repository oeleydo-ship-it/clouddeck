<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Jobs\Sites\FetchLogJob;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LogController extends Controller
{
    /**
     * Every log Uplary can show. A name rather than a path: the browser must never be able
     * to choose which file is read.
     */
    public const SOURCES = [
        'laravel' => 'Laravel',
        'nginx' => 'Nginx errors',
        'nginx-access' => 'Nginx access',
        'php' => 'PHP-FPM',
        'supervisor' => 'Supervisor',
        'reverb' => 'Reverb and workers',
        'redis' => 'Redis',
    ];

    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->server->status === ServerStatus::Ready, 422, 'The server is not ready.');

        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(self::SOURCES))],
            'lines' => ['sometimes', 'integer', 'min:20', 'max:2000'],
        ]);

        $snapshot = $site->logSnapshots()->create([
            'server_id' => $site->server_id,
            'user_id' => $request->user()->id,
            'source' => $data['source'],
            'lines' => $data['lines'] ?? 200,
            'status' => 'pending',
        ]);

        FetchLogJob::dispatch($snapshot->id)->onQueue('operations');

        return redirect()
            ->route('sites.show', ['site' => $site, 'tab' => 'logs'])
            ->with('status', 'Reading the '.self::SOURCES[$data['source']].' log.');
    }
}
