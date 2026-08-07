<?php

namespace App\Http\Controllers;

use App\Jobs\Operations\CheckQueueWorkerStatusJob;
use App\Jobs\Operations\SyncQueueWorkerJob;
use App\Models\QueueWorker;
use App\Models\Site;
use App\Services\FeatureManager;
use App\Services\ReverbEnvironment;
use App\Services\ServerPortRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QueueWorkerController extends Controller
{
    public function __construct(private readonly ServerPortRegistry $ports, private readonly ReverbEnvironment $reverbEnvironment) {}

    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $this->assertWorkerEntitlement($request);
        $server = $site->server;
        $data = $request->validate([
            'name' => ['required', 'regex:/^[a-z][a-z0-9-]{0,40}$/', Rule::unique('queue_workers', 'name')->where(fn ($query) => $query->where('site_id', $site->id)->whereNull('deleted_at'))],
            'type' => ['required', Rule::in(['queue', 'horizon', 'reverb'])],
            'connection' => ['required_if:type,queue', 'nullable', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'queue' => ['required_if:type,queue', 'nullable', 'regex:/^[a-zA-Z0-9_,.-]+$/'],
            'processes' => ['required_if:type,queue', 'nullable', 'integer', 'between:1,20'],
            'tries' => ['nullable', 'integer', 'between:1,20'],
            'timeout' => ['nullable', 'integer', 'between:10,3600'],
            'memory' => ['nullable', 'integer', 'between:64,2048'],
            'port' => [
                'required_if:type,reverb', 'nullable', 'integer', 'between:1024,65535',
                // Soft-deleted workers must not reserve their port forever: removing a Reverb
                // worker has to free the port for the next one.
                Rule::unique('queue_workers', 'port')->where(fn ($query) => $query->whereIn('site_id', $server->sites()->pluck('id'))->whereNull('deleted_at')),
            ],
        ]);
        if (($data['type'] ?? null) === 'reverb' && $conflict = $this->ports->conflict($server, (int) $data['port'])) {
            throw ValidationException::withMessages(['port' => 'Port '.$data['port'].' is already used by '.$conflict.' on this server.']);
        }

        $worker = $site->queueWorkers()->create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'connection' => $data['connection'] ?? 'redis',
            'queue' => $data['queue'] ?? 'default',
            'processes' => $data['type'] === 'queue' ? $data['processes'] : 1,
            'tries' => $data['tries'] ?? 3,
            'timeout' => $data['timeout'] ?? 90,
            'memory' => $data['memory'] ?? 256,
            'port' => $data['port'] ?? null,
        ]);

        // Keep the app's own Reverb config in step with the port Supervisor binds, so a manual
        // `php artisan reverb:start` (which accepts no port) does not fall back to Reverb's 8080.
        if ($worker->type === 'reverb') {
            $this->reverbEnvironment->apply($site, (int) $worker->port);
        }

        SyncQueueWorkerJob::dispatch($worker->id)->onQueue('operations');

        return back()->with('status', ucfirst($data['type']).' worker configuration queued.'.($worker->type === 'reverb' ? ' Reverb binds to 127.0.0.1:'.$worker->port.' and Nginx proxies it on https://'.$site->domain.'/app — redeploy to write the connection details into the release .env and the compiled assets.' : ''));
    }

    public function destroy(Request $request, QueueWorker $queueWorker): RedirectResponse
    {
        $this->authorize('update', $queueWorker->site);
        $this->assertWorkerEntitlement($request);
        $queueWorker->delete();
        SyncQueueWorkerJob::dispatch($queueWorker->id)->onQueue('operations');

        return back()->with('status', 'Queue worker removal queued.');
    }

    public function status(Request $request, QueueWorker $queueWorker): RedirectResponse
    {
        $this->authorize('update', $queueWorker->site);
        $this->assertWorkerEntitlement($request);
        CheckQueueWorkerStatusJob::dispatch($queueWorker->id)->onQueue('operations');

        return back()->with('status', 'Checking Supervisor status.');
    }

    private function assertWorkerEntitlement(Request $request): void
    {
        abort_unless(
            app(FeatureManager::class)->enabled('redis', $request->user()),
            403,
            'This feature is not enabled for your account.',
        );
    }
}
