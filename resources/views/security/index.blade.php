@extends('layouts.app')
@section('content')
@php
    $serverRows = $servers->map(function ($server) use ($detectionEnabled) {
        $canScan = $detectionEnabled
            && $server->status === \App\Enums\ServerStatus::Ready
            && $server->ssh_key_id;
        $stale = $server->securityScanIsStale();

        return [
            'id' => $server->id,
            'name' => $server->name,
            'sites_count' => (int) $server->sites_count,
            'can_scan' => (bool) $canScan,
            'status' => $server->security_scan_status ?: 'idle',
            'message' => $server->security_scan_message,
            'busy' => $server->securityScanIsBusy(),
            'scanned_at' => $server->security_scanned_at?->toIso8601String(),
            'scanned_at_human' => $server->security_scanned_at?->diffForHumans() ?? 'never',
            'label' => $stale
                ? ($server->security_scan_status === 'running'
                    ? 'Scan stalled — check the operations queue worker'
                    : 'Queued too long — is the operations worker running?')
                : match ($server->security_scan_status) {
                    'queued' => 'Queued',
                    'running' => 'Scanning…',
                    'failed' => 'Failed',
                    default => $server->security_scanned_at
                        ? 'Last scan '.$server->security_scanned_at->diffForHumans()
                        : 'Last scan never',
                },
            'badge' => $stale
                ? 'danger'
                : match ($server->security_scan_status) {
                    'queued' => 'warning',
                    'running' => 'info',
                    'failed' => 'danger',
                    'succeeded' => 'success',
                    default => 'neutral',
                },
        ];
    })->values();
@endphp
<div
    class="app-main"
    x-data="securityScanStatus(@js([
        'statusUrl' => route('security.status'),
        'detectionEnabled' => (bool) $detectionEnabled,
        'servers' => $serverRows,
        'summary' => [
            'protected_servers' => $protectedServers,
            'protected_sites' => $protectedSites,
            'open_critical' => $openCritical,
            'last_scan' => $lastScan?->toIso8601String(),
            'last_scan_human' => $lastScan?->diffForHumans() ?? 'Never',
        ],
    ]))"
    x-init="boot()"
>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="page-eyebrow">Threat monitoring</p>
            <h1 class="page-title">Security</h1>
            <p class="page-subtitle">Configurable server and site signal detection with explicit, audited response actions.</p>
        </div>
        <form method="POST" action="{{ route('security.scan') }}">@csrf
            <button
                class="button-primary"
                :disabled="!detectionEnabled || anyBusy"
                title="{{ $detectionEnabled ? 'Queue scans for this workspace' : 'Enable security detection before scanning' }}"
            >Scan all now</button>
        </form>
    </header>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <section class="panel">
            <p class="text-xs font-medium uppercase tracking-wide muted">Protected servers</p>
            <p class="mt-2 text-2xl font-semibold heading" x-text="summary.protected_servers">{{ $protectedServers }}</p>
        </section>
        <section class="panel">
            <p class="text-xs font-medium uppercase tracking-wide muted">Protected sites</p>
            <p class="mt-2 text-2xl font-semibold heading" x-text="summary.protected_sites">{{ $protectedSites }}</p>
        </section>
        <section class="panel">
            <p class="text-xs font-medium uppercase tracking-wide muted">Open critical</p>
            <p class="mt-2 text-2xl font-semibold heading" x-text="summary.open_critical">{{ $openCritical }}</p>
        </section>
        <section class="panel">
            <p class="text-xs font-medium uppercase tracking-wide muted">Last completed scan</p>
            <p class="mt-2 text-2xl font-semibold heading" x-text="summary.last_scan_human">{{ $lastScan?->diffForHumans() ?? 'Never' }}</p>
        </section>
    </div>

    <section class="panel mt-6">
        <form method="POST" action="{{ route('security.settings.update') }}">
            @csrf @method('PUT')
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="section-title">Detection settings</h2>
                    <p class="mt-1 text-sm muted">Applies to <strong class="heading">{{ $settingsScope }}</strong>. Recommended: keep detection enabled, observe a baseline, then tune noisy rules.</p>
                </div>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 dark:border-white/10">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" @checked($detectionEnabled) @disabled(!$canManageSettings) class="h-4 w-4 rounded border-slate-300">
                    <span>
                        <span class="block text-sm font-medium heading">Security detection enabled</span>
                        <span class="block text-xs muted">Default: on</span>
                    </span>
                </label>
            </div>

            @unless($canManageSettings)
                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">Only the team owner or an administrator can change these settings.</p>
            @endunless

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @foreach($rules as $index => $rule)
                    <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-white/10" @disabled(!$canManageSettings)>
                        <input type="hidden" name="rules[{{ $index }}][key]" value="{{ $rule['key'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium heading">{{ $rule['name'] }}</p>
                                <p class="mt-1 text-xs muted"><code>{{ $rule['key'] }}</code></p>
                            </div>
                            <label class="flex items-center gap-2 text-xs font-medium heading">
                                <input type="hidden" name="rules[{{ $index }}][enabled]" value="0">
                                <input type="checkbox" name="rules[{{ $index }}][enabled]" value="1" @checked($rule['enabled']) class="h-4 w-4 rounded border-slate-300">
                                Enabled
                            </label>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            <label class="text-xs muted">
                                Threshold
                                @if($rule['single_event'])
                                    <input type="hidden" name="rules[{{ $index }}][threshold]" value="1">
                                    <span class="mt-1 block rounded-lg border border-slate-200 px-3 py-2 text-sm heading dark:border-white/10">1 event</span>
                                @else
                                    <input class="input mt-1 !py-2" type="number" min="1" max="10000" name="rules[{{ $index }}][threshold]" value="{{ $rule['threshold'] }}" required>
                                @endif
                            </label>
                            <label class="text-xs muted">
                                Window (minutes)
                                <input class="input mt-1 !py-2" type="number" min="1" max="1440" name="rules[{{ $index }}][lookback_minutes]" value="{{ $rule['lookback_minutes'] }}" required>
                            </label>
                            <label class="text-xs muted">
                                Severity
                                <select class="input mt-1 !py-2" name="rules[{{ $index }}][severity]">
                                    @foreach(['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical'] as $value => $label)
                                        <option value="{{ $value }}" @selected($rule['severity'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </fieldset>
                @endforeach
            </div>

            @if($canManageSettings)
                <div class="mt-5 flex justify-end">
                    <button class="button-primary">Save settings</button>
                </div>
            @endif
        </form>

        @if($canManageSettings)
            <form class="mt-3 flex justify-end" method="POST" action="{{ route('security.settings.reset') }}">
                @csrf @method('DELETE')
                <button class="button-secondary" onclick="return confirm('Reset every detector to the recommended deployment defaults?')">Reset to recommended defaults</button>
            </form>
        @endif
    </section>

    <section class="panel mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="section-title">Managed servers</h2>
                <p class="mt-1 text-sm muted">Scans use the operations queue and existing managed SSH credentials.</p>
            </div>
            <span class="badge badge-neutral">IP blocking: manual only</span>
        </div>
        <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200"><strong>Automatic IP blocking is off and unavailable.</strong> Review each incident before blocking. Manual blocks still reject private, reserved, loopback, and server-owned addresses.</p>
        <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
            @forelse($servers as $server)
                @php
                    $canScan = $detectionEnabled
                        && $server->status === \App\Enums\ServerStatus::Ready
                        && $server->ssh_key_id;
                    $row = $serverRows->firstWhere('id', $server->id);
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium heading">{{ $server->name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <p class="text-xs muted">{{ $server->sites_count }} sites</p>
                            <span class="badge" :class="badgeClass(statusFor(@js($server->id)).badge)">
                                <span class="badge-dot" :class="badgeDot(statusFor(@js($server->id)).badge)"></span>
                                <span x-text="statusFor(@js($server->id)).label">{{ $row['label'] }}</span>
                            </span>
                        </div>
                        <p
                            class="mt-1 text-xs muted"
                            x-show="statusFor(@js($server->id)).status === 'failed' && statusFor(@js($server->id)).message"
                            x-text="statusFor(@js($server->id)).message"
                        >{{ ($row['status'] ?? '') === 'failed' ? ($row['message'] ?? '') : '' }}</p>
                    </div>
                    <form method="POST" action="{{ route('security.scan') }}">@csrf
                        <input type="hidden" name="server_id" value="{{ $server->id }}">
                        <button
                            class="button-secondary !px-3 !py-1.5 text-xs"
                            :disabled="!statusFor(@js($server->id)).can_scan || statusFor(@js($server->id)).busy"
                            @disabled(! $canScan || ($row['busy'] ?? false))
                            :title="scanButtonTitle(statusFor(@js($server->id)))"
                            title="{{ $canScan ? (($row['busy'] ?? false) ? 'Scan already in progress' : 'Queue a security scan') : ($detectionEnabled ? 'Server must be Ready with managed SSH' : 'Enable security detection before scanning') }}"
                        >Scan now</button>
                    </form>
                </div>
            @empty
                <p class="py-6 text-sm muted">Add a server to start protecting it</p>
            @endforelse
        </div>
    </section>

</div>

<script>
function securityScanStatus(initial) {
    return {
        statusUrl: initial.statusUrl,
        detectionEnabled: !!initial.detectionEnabled,
        servers: initial.servers || [],
        summary: initial.summary || {},
        timer: null,

        get anyBusy() {
            return this.servers.some((server) => server.busy);
        },

        statusFor(id) {
            return this.servers.find((server) => server.id === id) || {
                id,
                can_scan: false,
                status: 'idle',
                message: null,
                busy: false,
                label: 'Last scan never',
                badge: 'neutral',
            };
        },

        boot() {
            if (this.anyBusy) {
                this.poll();
            }
            this.timer = setInterval(() => {
                if (this.anyBusy) {
                    this.poll();
                }
            }, 4000);
        },

        badgeClass(badge) {
            return {
                'badge-warning': badge === 'warning',
                'badge-info': badge === 'info',
                'badge-danger': badge === 'danger',
                'badge-success': badge === 'success',
                'badge-neutral': !badge || badge === 'neutral',
            };
        },

        badgeDot(badge) {
            return {
                'bg-amber-500': badge === 'warning',
                'bg-[#0070eb]': badge === 'info',
                'bg-rose-500': badge === 'danger',
                'bg-emerald-500': badge === 'success',
                'bg-slate-400': !badge || badge === 'neutral',
            };
        },

        scanButtonTitle(server) {
            if (! this.detectionEnabled) {
                return 'Enable security detection before scanning';
            }
            if (! server.can_scan) {
                return 'Server must be Ready with managed SSH';
            }
            if (server.busy) {
                return server.status === 'running' ? 'Scan already in progress' : 'Scan already queued';
            }
            return 'Queue a security scan';
        },

        async poll() {
            try {
                const res = await fetch(this.statusUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (! res.ok) return;
                const data = await res.json();
                const byId = Object.fromEntries((data.servers || []).map((row) => [row.id, row]));
                this.servers = this.servers.map((server) => {
                    const next = byId[server.id];
                    if (! next) return server;
                    return {
                        ...server,
                        status: next.status,
                        message: next.message,
                        busy: next.busy,
                        scanned_at: next.scanned_at,
                        scanned_at_human: next.scanned_at_human,
                        label: next.label,
                        badge: next.badge,
                    };
                });
                if (data.summary) {
                    this.summary = data.summary;
                }
            } catch (e) { /* keep last snapshot */ }
        },
    };
}
</script>
@endsection
