<form method="GET" action="{{ route('notifications.index') }}" class="panel flex flex-wrap items-end gap-4">
    <input type="hidden" name="tab" value="incidents">
    <label class="min-w-[10rem] text-sm heading">Status
        <select class="field" name="status" onchange="this.form.submit()">
            <option value="open" @selected($filters['status'] === 'open')>Open</option>
            <option value="acknowledged" @selected($filters['status'] === 'acknowledged')>Acknowledged</option>
            <option value="resolved" @selected($filters['status'] === 'resolved')>Resolved</option>
            <option value="all" @selected($filters['status'] === 'all')>All</option>
        </select>
    </label>
    <label class="min-w-[10rem] text-sm heading">Type
        <select class="field" name="type" onchange="this.form.submit()">
            <option value="all" @selected($filters['type'] === 'all')>All</option>
            <option value="security" @selected($filters['type'] === 'security')>Security</option>
            <option value="server" @selected($filters['type'] === 'server')>Server metric</option>
            <option value="site" @selected($filters['type'] === 'site')>Site monitor</option>
        </select>
    </label>
    <label class="min-w-[10rem] text-sm heading">Severity
        <select class="field" name="severity" onchange="this.form.submit()">
            <option value="all" @selected($filters['severity'] === 'all')>All</option>
            <option value="critical" @selected($filters['severity'] === 'critical')>Critical</option>
            <option value="warning" @selected($filters['severity'] === 'warning')>Warning</option>
            <option value="info" @selected($filters['severity'] === 'info')>Info</option>
        </select>
    </label>
    <label class="min-w-[16rem] grow text-sm heading">Server
        <select class="field" name="server" onchange="this.form.submit()">
            <option value="">All servers</option>
            @foreach($servers as $server)
                <option value="{{ $server->id }}" @selected($filters['server'] === $server->id)>{{ $server->name }}@if($server->public_ip) — {{ $server->public_ip }}@endif</option>
            @endforeach
        </select>
    </label>
</form>

<div class="mt-6 space-y-3">
    @forelse($incidents as $incident)
        @php
            $severityTint = match ($incident['severity']) {
                'critical' => 'rose',
                'warning' => 'amber',
                default => 'slate',
            };
            $statusOpen = $incident['status'] !== 'resolved';
        @endphp
        <article class="panel flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 grow">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-card font-semibold heading">{{ $incident['message'] }}</h2>
                    <span class="badge {{ $statusOpen ? 'badge-danger' : 'badge-success' }} capitalize">
                        <span class="badge-dot {{ $statusOpen ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                        {{ $incident['status'] }}
                    </span>
                    <span class="badge badge-neutral capitalize">
                        <span class="badge-dot bg-{{ $severityTint }}-500"></span>
                        {{ $incident['severity'] }}
                    </span>
                    <span class="badge badge-neutral capitalize">{{ $incident['source'] }}</span>
                </div>
                <p class="mt-2 text-sm muted">
                    {{ $incident['detail'] }}
                    · started {{ $incident['started_at']?->diffForHumans() ?? '—' }}
                    @if($incident['resolved_at'])
                        · resolved {{ $incident['resolved_at']->diffForHumans() }}
                    @endif
                </p>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs muted">
                    @if($incident['server'])
                        <span>{{ $incident['server']->name }}</span>
                    @endif
                    @if($incident['site'])
                        <span>{{ $incident['site']->domain }}</span>
                    @endif
                </div>
                @if($incident['security'])
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs font-medium text-sky-700 dark:text-sky-300">Sanitized evidence</summary>
                        <p class="mt-2 text-sm muted">{{ $incident['security']->summary }}</p>
                        <pre class="mt-2 max-h-48 overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-200">{{ json_encode($incident['security']->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach(['acknowledged' => 'Acknowledge', 'resolved' => 'Resolve', 'open' => 'Reopen'] as $status => $label)
                            @if($incident['status'] !== $status)
                                <form method="POST" action="{{ route('security.incidents.status', $incident['security']) }}">@csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <button class="button-secondary !px-3 !py-1.5 text-xs">{{ $label }}</button>
                                </form>
                            @endif
                        @endforeach
                        @if($incident['security']->source_ip && ! $incident['security']->firewall_rule_id)
                            <form method="POST" action="{{ route('security.incidents.block', $incident['security']) }}" onsubmit="return confirm('Block this public IP on the affected server?')">@csrf
                                <input type="hidden" name="confirm" value="1">
                                <button class="button-secondary !px-3 !py-1.5 text-xs text-rose-600">Block IP</button>
                            </form>
                        @elseif($incident['security']->firewall_rule_id)
                            <form method="POST" action="{{ route('security.incidents.unblock', $incident['security']) }}" onsubmit="return confirm('Remove the incident-managed firewall block?')">@csrf @method('DELETE')
                                <input type="hidden" name="confirm" value="1">
                                <button class="button-secondary !px-3 !py-1.5 text-xs">Unblock IP</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
            @if($incident['href'])
                <a href="{{ $incident['href'] }}" class="button-secondary shrink-0 !px-3 !py-1.5 text-xs whitespace-nowrap">
                    {{ $incident['source'] === 'site' ? 'Open site' : 'Open server' }}
                </a>
            @endif
        </article>
    @empty
        <div class="dashed-cta">
            <span class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0ZM12 9v4M12 17h.01"/></svg>
            </span>
            <span class="text-card font-semibold heading">
                @if(($filters['status'] ?? 'open') === 'open')
                    No open incidents
                @else
                    No incidents match these filters
                @endif
            </span>
            <span class="text-body-sm muted">When a server alert rule or site check fails, it will show up here.</span>
        </div>
    @endforelse
</div>
