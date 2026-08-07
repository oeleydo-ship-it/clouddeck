<?php

namespace App\Http\Controllers;

use App\Models\AlertIncident;
use App\Models\SiteMonitorIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class IncidentController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('notifications.index', array_filter([
            'tab' => 'incidents',
            'status' => $request->query('status'),
            'severity' => $request->query('severity'),
            'server' => $request->query('server'),
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Shared incident inbox for the Notifications page (and any future consumers).
     *
     * @return array{incidents: Collection<int, array<string, mixed>>, servers: Collection<int, \App\Models\Server>, filters: array{status: string, severity: string, server: ?string}}
     */
    public function listData(Request $request): array
    {
        $user = $request->user();
        $servers = $user->accessibleServers()->orderBy('name')->get(['id', 'name', 'public_ip']);

        if ($request->input('server') === '') {
            $request->merge(['server' => null]);
        }

        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['open', 'resolved', 'all'])],
            'severity' => ['sometimes', 'nullable', Rule::in(['info', 'warning', 'critical', 'all'])],
            'server' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $status = $filters['status'] ?? 'open';
        $severity = $filters['severity'] ?? 'all';
        $serverId = $filters['server'] ?? null;

        if ($serverId && ! $servers->contains('id', $serverId)) {
            abort(404);
        }

        $alertIncidents = AlertIncident::query()
            ->with(['server:id,name', 'rule:id,name'])
            ->whereHas('server', fn ($query) => $query->accessibleTo($user))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($serverId, fn ($query) => $query->where('server_id', $serverId))
            ->when($severity !== 'all', fn ($query) => $query->where('severity', $severity))
            ->latest('started_at')
            ->limit(100)
            ->get()
            ->map(fn (AlertIncident $incident) => [
                'id' => $incident->id,
                'source' => 'server',
                'message' => $incident->message,
                'status' => $incident->status,
                'severity' => $incident->severity,
                'detail' => trim(($incident->metric ?? '').' '.($incident->value ?? '').' / threshold '.($incident->threshold ?? '')),
                'started_at' => $incident->started_at,
                'resolved_at' => $incident->resolved_at,
                'server' => $incident->server,
                'site' => null,
                'href' => route('servers.manage', ['server' => $incident->server_id, 'tab' => 'monitoring']),
            ]);

        $siteIncidents = SiteMonitorIncident::query()
            ->with(['site:id,domain,server_id', 'site.server:id,name'])
            ->whereHas('site.server', fn ($query) => $query->accessibleTo($user))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($serverId, fn ($query) => $query->whereHas('site', fn ($site) => $site->where('server_id', $serverId)))
            ->when($severity !== 'all', function ($query) use ($severity) {
                $types = match ($severity) {
                    'critical' => ['site_down'],
                    'warning' => ['dns_mismatch'],
                    default => [],
                };

                return $types === [] ? $query->whereRaw('0 = 1') : $query->whereIn('type', $types);
            })
            ->latest('started_at')
            ->limit(100)
            ->get()
            ->map(fn (SiteMonitorIncident $incident) => [
                'id' => $incident->id,
                'source' => 'site',
                'message' => $incident->message,
                'status' => $incident->status,
                'severity' => match ($incident->type) {
                    'site_down' => 'critical',
                    default => 'warning',
                },
                'detail' => str_replace('_', ' ', $incident->type),
                'started_at' => $incident->started_at,
                'resolved_at' => $incident->resolved_at,
                'server' => $incident->site?->server,
                'site' => $incident->site,
                'href' => $incident->site_id
                    ? route('sites.show', ['site' => $incident->site_id]).'?tab=monitoring'
                    : null,
            ]);

        /** @var Collection<int, array<string, mixed>> $incidents */
        $incidents = $alertIncidents
            ->concat($siteIncidents)
            ->sortByDesc(fn (array $row) => $row['started_at']?->timestamp ?? 0)
            ->values()
            ->take(100);

        return [
            'incidents' => $incidents,
            'servers' => $servers,
            'filters' => [
                'status' => $status,
                'severity' => $severity,
                'server' => $serverId,
            ],
        ];
    }
}
