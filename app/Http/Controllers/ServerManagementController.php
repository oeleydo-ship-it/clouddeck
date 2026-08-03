<?php

namespace App\Http\Controllers;

use App\Cloud\CloudProviderManager;
use App\Models\NotificationChannel;
use App\Models\Server;
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
        return view('servers.index', [
            'servers' => $request->user()->accessibleServers()
                ->with(['sites', 'latestMetric', 'cloudAccount', 'team'])
                ->latest()
                ->paginate(15),
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
                'alertIncidents' => fn ($q) => $q->latest('started_at')->limit(20),
                'backupPolicies.database',
                'snapshots' => fn ($q) => $q->latest()->limit(30),
            ]),
            'notificationChannels' => $request->user()->notificationChannels()->latest()->get(),
            'notificationEvents' => NotificationChannel::EVENTS,
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
                $providers->for($server->cloudAccount)->deleteServer($server->provider_id);
            } catch (Throwable $e) {
                return back()->withErrors(['server' => 'Unable to remove the provider Droplet: '.$e->getMessage()]);
            }
        }

        $audit->record($request, 'server.deleted', $server, ['hostname' => $server->hostname, 'provider_id' => $server->provider_id], []);
        $server->delete();

        return redirect()->route('dashboard')->with('status', 'Server removed.');
    }
}
