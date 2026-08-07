@extends('layouts.app')
@section('content')
<div class="app-main">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="page-eyebrow">Security</p>
            <h1 class="page-title">Firewall</h1>
            <p class="page-subtitle">Manage UFW allow and deny rules per server. Rules are applied over SSH and never accept raw shell input.</p>
        </div>
    </header>

    @if($errors->any())
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>
    @endif

    @if($servers->isEmpty())
        <div class="mt-8 dashed-cta">
            <span class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
            </span>
            <span class="text-card font-semibold heading">No servers yet</span>
            <span class="text-body-sm muted">Provision or connect a server first — firewall rules are managed per host.</span>
            <a href="{{ route('servers.create') }}" class="button-primary mt-2">Provision server</a>
        </div>
    @else
        <form method="GET" action="{{ route('firewall.index') }}" class="mt-8 panel flex flex-wrap items-end gap-4">
            <label class="min-w-[16rem] grow text-sm heading">Server
                <select class="field" name="server" onchange="this.form.submit()">
                    @foreach($servers as $server)
                        <option value="{{ $server->id }}" @selected($selected && $selected->id === $server->id)>{{ $server->name }}@if($server->public_ip) — {{ $server->public_ip }}@endif</option>
                    @endforeach
                </select>
            </label>
            <p class="pb-2 text-sm muted">Firewall rules belong to one server at a time. Switching servers loads that host’s rule set.</p>
        </form>

        @if($selected)
            @php
                $statusTints = ['synced' => 'emerald', 'pending' => 'amber', 'failed' => 'rose'];
                $badgeClasses = ['emerald' => 'badge-success', 'amber' => 'badge-warning', 'rose' => 'badge-danger'];
            @endphp

            @if($selected->firewall_status === 'missing_ufw')
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100">
                    {{ $selected->firewall_message ?: 'UFW is not installed on this server. Install and enable UFW before applying firewall rules.' }}
                </div>
            @elseif($selected->firewall_status === 'error' && $selected->firewall_message)
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $selected->firewall_message }}</div>
            @endif

            <div class="mt-6 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('firewall.sync', $selected) }}">@csrf
                    <button class="button-primary" @disabled($rules->isEmpty())>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
                        Apply to server
                    </button>
                </form>
                <form method="POST" action="{{ route('firewall.refresh', $selected) }}">@csrf
                    <button class="button-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg>
                        Refresh remote status
                    </button>
                </form>
                @if($selected->firewall_synced_at)
                    <p class="self-center text-xs muted">Last sync {{ $selected->firewall_synced_at->diffForHumans() }}</p>
                @endif
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[380px_1fr]">
                <form method="POST" action="{{ route('firewall.rules.store') }}" class="panel h-fit">@csrf
                    <input type="hidden" name="server_id" value="{{ $selected->id }}">
                    <h2 class="flex items-center gap-3 section-title">
                        <span class="stat-icon bg-sky-50 text-[#0058bc] dark:bg-sky-400/10 dark:text-sky-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg></span>
                        Add rule
                    </h2>
                    <p class="mt-2 text-xs muted">Whitelisted protocols and ports only. Source may be an IP or CIDR; leave blank for anywhere.</p>

                    <label class="mt-5 block text-sm heading">Action
                        <select class="field" name="type">
                            <option value="allow" @selected(old('type', 'allow') === 'allow')>Allow</option>
                            <option value="deny" @selected(old('type') === 'deny')>Deny</option>
                        </select>
                    </label>
                    <label class="mt-4 block text-sm heading">Protocol
                        <select class="field" name="protocol">
                            <option value="tcp" @selected(old('protocol', 'tcp') === 'tcp')>TCP</option>
                            <option value="udp" @selected(old('protocol') === 'udp')>UDP</option>
                            <option value="any" @selected(old('protocol') === 'any')>Any</option>
                        </select>
                    </label>
                    <label class="mt-4 block text-sm heading">Port or profile
                        <input class="field" name="port" value="{{ old('port') }}" placeholder="443 or OpenSSH" list="firewall-named-ports">
                    </label>
                    <datalist id="firewall-named-ports">
                        @foreach($namedPorts as $named)
                            <option value="{{ $named }}"></option>
                        @endforeach
                    </datalist>
                    <label class="mt-4 block text-sm heading">From IP / CIDR
                        <input class="field font-mono text-xs" name="from_ip" value="{{ old('from_ip') }}" placeholder="Anywhere if empty">
                    </label>
                    <label class="mt-4 block text-sm heading">Description
                        <input class="field" name="description" value="{{ old('description') }}" placeholder="Optional note">
                    </label>
                    <button class="button-primary mt-5 w-full">Add and apply</button>
                </form>

                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-4 pb-1">
                        <h2 class="section-title">Rules on {{ $selected->name }}</h2>
                        <span class="badge badge-info">{{ $rules->count() }} {{ Str::plural('rule', $rules->count()) }}</span>
                    </div>

                    @forelse($rules as $rule)
                        @php $tint = $statusTints[$rule->status] ?? 'amber'; @endphp
                        <article class="panel">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h3 class="font-semibold heading capitalize">{{ $rule->type }} {{ $rule->port ?: 'any port' }}</h3>
                                        <span class="badge {{ $badgeClasses[$tint] }} capitalize"><span class="badge-dot bg-{{ $tint }}-500"></span>{{ $rule->status }}</span>
                                    </div>
                                    <p class="mt-1 text-sm muted">
                                        {{ strtoupper($rule->protocol) }}
                                        · from {{ $rule->from_ip ?: 'anywhere' }}
                                        @if($rule->description) · {{ $rule->description }}@endif
                                    </p>
                                    @if($rule->status_message)
                                        <p class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ $rule->status_message }}</p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('firewall.rules.destroy', $rule) }}" onsubmit="return confirm('Remove this firewall rule from {{ $selected->name }}?')">@csrf @method('DELETE')
                                    <button class="button-ghost !text-rose-600 hover:!bg-rose-50 dark:!text-rose-300 dark:hover:!bg-rose-400/10">Delete</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="dashed-cta">
                            <span class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
                            </span>
                            <span class="text-sm muted">No managed rules for this server yet. Bootstrap still opens OpenSSH and Nginx Full; add custom allow/deny rules here, then apply.</span>
                        </div>
                    @endforelse

                    @if($selected->firewall_remote_status)
                        <div class="panel">
                            <h3 class="section-title">Remote UFW status</h3>
                            <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950/95 p-4 text-xs leading-relaxed text-slate-100">{{ $selected->firewall_remote_status }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
