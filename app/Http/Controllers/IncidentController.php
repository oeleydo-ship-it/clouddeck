<?php

namespace App\Http\Controllers;

use App\Models\AlertIncident;
use App\Models\SecurityIncident;
use App\Models\Server;
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
            'type' => $request->query('type'),
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Shared incident inbox for the Notifications page (and any future consumers).
     *
     * @return array{incidents: Collection<int, array<string, mixed>>, servers: Collection<int, Server>, filters: array<string, mixed>}
     */
    public function listData(Request $request): array
    {
        $user = $request->user();
        $servers = $user->accessibleServers()->orderBy('name')->get(['id', 'name', 'public_ip']);

        if ($request->input('server') === '') {
            $request->merge(['server' => null]);
        }

        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['open', 'acknowledged', 'resolved', 'all'])],
            'severity' => ['sometimes', 'nullable', Rule::in(['info', 'warning', 'critical', 'all'])],
            'server' => ['sometimes', 'nullable', 'uuid'],
            'type' => ['sometimes', 'nullable', Rule::in(['all', 'security', 'server', 'site'])],
        ]);

        $status = $filters['status'] ?? 'open';
        $severity = $filters['severity'] ?? 'all';
        $serverId = $filters['server'] ?? null;
        $type = $filters['type'] ?? 'all';

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
                'started_at' => $incident->started_at?->toIso8601String(),
                'started_at_human' => $incident->started_at?->diffForHumans(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'resolved_at_human' => $incident->resolved_at?->diffForHumans(),
                'server' => $incident->server ? $incident->server->only(['id', 'name', 'public_ip']) : null,
                'site' => null,
                'href' => route('servers.manage', ['server' => $incident->server_id, 'tab' => 'monitoring']),
                'security' => null,
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
                'started_at' => $incident->started_at?->toIso8601String(),
                'started_at_human' => $incident->started_at?->diffForHumans(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'resolved_at_human' => $incident->resolved_at?->diffForHumans(),
                'server' => $incident->site?->server ? $incident->site->server->only(['id', 'name']) : null,
                'site' => $incident->site ? $incident->site->only(['id', 'domain']) : null,
                'href' => $incident->site_id
                    ? route('sites.show', ['site' => $incident->site_id]).'?tab=monitoring'
                    : null,
                'security' => null,
            ]);

        $securityIncidents = SecurityIncident::query()
            ->accessibleTo($user)
            ->with(['server:id,name', 'site:id,domain,server_id', 'firewallRule'])
            ->when($status !== 'all', function ($query) use ($status) {
                return $status === 'open'
                    ? $query->whereIn('status', ['open', 'acknowledged'])
                    : $query->where('status', $status);
            })
            ->when($serverId, fn ($query) => $query->where('server_id', $serverId))
            ->when($severity !== 'all', fn ($query) => $query->where('severity', $severity))
            ->latest('last_seen_at')
            ->limit(100)
            ->get()
            ->map(fn (SecurityIncident $incident) => [
                'id' => $incident->id,
                'source' => 'security',
                'message' => $incident->title,
                'status' => $incident->status,
                'severity' => $incident->severity,
                'detail' => $incident->rule_name
                    .($incident->source_ip ? ' · '.$incident->source_ip : '')
                    .' · '.$incident->occurrence_count.' occurrences',
                'started_at' => $incident->first_seen_at?->toIso8601String(),
                'started_at_human' => $incident->first_seen_at?->diffForHumans(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'resolved_at_human' => $incident->resolved_at?->diffForHumans(),
                'server' => $incident->server ? $incident->server->only(['id', 'name', 'public_ip']) : null,
                'site' => $incident->site ? $incident->site->only(['id', 'domain']) : null,
                'href' => $incident->site
                    ? route('sites.show', $incident->site)
                    : ($incident->server ? route('servers.manage', $incident->server) : null),
                'security' => [
                    'id' => $incident->id,
                    'summary' => $incident->summary,
                    'evidence' => $incident->evidence,
                    'source_ip' => $incident->source_ip,
                    'firewall_rule_id' => $incident->firewall_rule_id,
                ],
            ]);

        /** @var Collection<int, array<string, mixed>> $incidents */
        $incidents = $alertIncidents
            ->concat($siteIncidents)
            ->concat($securityIncidents)
            ->when($type !== 'all', fn (Collection $rows) => $rows->where('source', $type))
            ->sortByDesc(fn (array $row) => $row['started_at'] ?? '')
            ->values()
            ->take(100);

        return [
            'incidents' => $incidents->all(),
            'servers' => $servers->map(fn (Server $server) => $server->only(['id', 'name', 'public_ip']))->values()->all(),
            'filters' => [
                'status' => $status,
                'severity' => $severity,
                'server' => $serverId,
                'type' => $type,
            ],
        ];
    }
}
