<?php

namespace App\Http\Controllers;

use App\Cloud\CloudProviderManager;
use App\Models\AlertIncident;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Services\AuditLogger;
use App\Services\TeamAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ServerManagementController extends Controller
{
    public function index(Request $request): View
    {
        $servers = $request->user()->accessibleServers()
            ->with(['sites', 'latestMetric', 'cloudAccount', 'team'])
            ->latest()
            ->paginate(15);

        $monitored = $request->user()->accessibleServers()->where('monitoring_enabled', true)->get(['id', 'last_seen_at']);
        $reachable = $monitored->filter(fn ($server) => $server->last_seen_at && $server->last_seen_at->gte(now()->subMinutes(5)))->count();

        // Summary strip above the table. Every figure comes from the same rows the table
        // renders or from the agent's own samples, so the two can never disagree.
        $cpuAverage = $monitored->isEmpty() ? null : ServerMetric::query()
            ->whereIn('server_id', $monitored->pluck('id'))
            ->where('recorded_at', '>=', now()->subDay())
            ->avg('cpu_percent');

        return view('servers.index', [
            'servers' => $servers,
            'summary' => [
                'total' => $servers->total(),
                'uptime' => $monitored->isEmpty() ? null : round($reachable / $monitored->count() * 100, 2),
                'cpu' => $cpuAverage === null ? null : round((float) $cpuAverage, 1),
                'alerts' => AlertIncident::query()
                    ->where('status', 'open')
                    ->whereHas('server', fn ($query) => $query->accessibleTo($request->user()))
                    ->count(),
            ],
        ]);
    }

    public function show(Request $request, Server $server, TeamAccess $teams): View
    {
        $this->authorize('view', $server);

        return view('servers.manage', [
            'server' => $server->load([
                'databases.backups',
                'cronJobs',
                'operations' => fn ($q) => $q->latest()->limit(20),
                'sites.queueWorkers',
                'metrics' => fn ($q) => $q->latest('recorded_at')->limit(72),
                'alertRules',
                'alertIncidents' => fn ($q) => $q->latest('started_at')->limit(5),
                'backupPolicies.database',
                'backupPolicies.databaseBackups' => fn ($q) => $q->latest()->limit(1),
                'backupPolicies.snapshots' => fn ($q) => $q->latest()->limit(1),
                'snapshots' => fn ($q) => $q->latest()->limit(30),
            ]),
            'backupDisks' => collect(config('filesystems.disks'))
                ->reject(fn (array $disk) => ($disk['visibility'] ?? null) === 'public')
                ->keys()
                ->values(),
            'transferTeams' => $request->user()->teamMemberships()->with('team')->whereNotNull('accepted_at')->get()->filter(fn ($membership) => $teams->canManage($request->user(), $membership->team))->pluck('team'),
        ]);
    }

    public function destroy(Request $request, Server $server, CloudProviderManager $providers, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $server);
        $request->validate(['confirmation' => ['required', Rule::in([$server->hostname])]]);
        if ($server->sites()->exists()) {
            return back()->withErrors(['server' => 'Delete the attached sites before removing this server.']);
        }

        if ($server->provider_id) {
            try {
                $providers->forServer($server)->deleteServer($server->provider_id);
            } catch (Throwable $e) {
                return back()->withErrors(['server' => 'Unable to remove the provider Droplet: '.$e->getMessage()]);
            }
        }

        $audit->record($request, 'server.deleted', $server, ['hostname' => $server->hostname, 'provider_id' => $server->provider_id], []);
        $server->delete();

        return redirect()->route('dashboard')->with('status', 'Server removed.');
    }
}
