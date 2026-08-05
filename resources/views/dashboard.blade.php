@extends('layouts.app')
@section('content')
@php
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = Str::of(auth()->user()->name)->before(' ')->toString();
    $planName = $plan['plan']?->name ?? 'No plan';
    $price = $plan['plan'] ? $plan['plan']->monthly_price : null;
    $currency = $plan['plan']?->currency ?? 'USD';
    $renews = $plan['subscription']?->current_period_ends_at;
    $resourceLabels = ['servers' => 'Servers', 'sites' => 'Sites', 'databases' => 'Databases'];
    $statusTone = [
        'ready' => 'badge-success',
        'active' => 'badge-success',
        'provisioning' => 'badge-warning',
        'creating' => 'badge-warning',
        'pending' => 'badge-neutral',
        'failed' => 'badge-danger',
        'deleting' => 'badge-danger',
    ];
    $deployTone = [
        'successful' => 'badge-success',
        'running' => 'badge-warning',
        'pending' => 'badge-neutral',
        'failed' => 'badge-danger',
        'cancelled' => 'badge-neutral',
        'rolled_back' => 'badge-warning',
    ];
@endphp
<div class="app-main">
    <header class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
            <p class="page-eyebrow">Overview</p>
            <h1 class="page-title">{{ $greeting }}, {{ $firstName }}</h1>
            <p class="page-subtitle">Your fleet at a glance — provision servers, ship sites, and keep an eye on health from one place.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('sites.create') }}" class="button-secondary h-12">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
                Add site
            </a>
            <a href="{{ route('servers.create') }}" class="button-primary h-12">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
                Provision server
            </a>
        </div>
    </header>

    {{-- Compact fleet summary --}}
    <section class="mt-10 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'Servers', 'value' => $stats['servers'], 'meta' => $stats['active'].' ready', 'href' => route('servers.index'), 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01', 'tone' => 'bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-300'],
            ['label' => 'Sites', 'value' => $stats['sites'], 'meta' => $stats['deployments'].' deploys today', 'href' => route('sites.index'), 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z', 'tone' => 'bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-300'],
            ['label' => 'Failed deploys', 'value' => $stats['failed'], 'meta' => $stats['failed'] > 0 ? 'Needs attention' : 'All clear', 'href' => route('sites.index'), 'icon' => 'M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z', 'tone' => $stats['failed'] > 0 ? 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300', 'danger' => $stats['failed'] > 0],
            ['label' => 'Offline agents', 'value' => $stats['offline'], 'meta' => $stats['offline'] > 0 ? 'Unreachable' : 'Reporting', 'href' => route('servers.index'), 'icon' => 'm4.9 4.9 14.2 14.2M9 9a5 5 0 0 1 7 7M5 13a9 9 0 0 1 2.5-6.3M12 20h.01', 'tone' => $stats['offline'] > 0 ? 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300', 'danger' => $stats['offline'] > 0],
        ] as $stat)
            <a href="{{ $stat['href'] }}" class="stat-card group transition hover:-translate-y-0.5 hover:border-sky-300 dark:hover:border-sky-400/40">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <p class="stat-label">{{ $stat['label'] }}</p>
                    <span class="stat-icon {{ $stat['tone'] }}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="{{ $stat['icon'] }}"/></svg></span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="stat-value {{ !empty($stat['danger']) ? '!text-rose-600 dark:!text-rose-400' : '' }}">{{ $stat['value'] }}</span>
                    <span class="text-xs font-medium muted">{{ $stat['meta'] }}</span>
                </div>
            </a>
        @endforeach
    </section>

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        {{-- Servers list --}}
        <section class="panel !p-0 overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-white/5">
                <div>
                    <h2 class="section-title">Your servers</h2>
                    <p class="mt-1 text-sm muted">Recently provisioned and connected hosts</p>
                </div>
                <a href="{{ route('servers.index') }}" class="text-sm font-semibold text-sky-600 hover:underline dark:text-sky-300">View all</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse($recentServers as $server)
                    <a href="{{ route('servers.manage', $server) }}" class="flex items-center gap-4 px-6 py-4 transition hover:bg-slate-50 dark:hover:bg-white/[.03]">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-300">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01"/></svg>
                        </span>
                        <div class="min-w-0 grow">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-semibold heading">{{ $server->name }}</p>
                                <span class="badge {{ $statusTone[$server->status->value] ?? 'badge-neutral' }}">{{ Str::headline($server->status->value) }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm muted">
                                {{ $server->public_ip ?: 'IP pending' }}
                                @if($server->region) · {{ $server->region }} @endif
                                @if($server->size) · {{ $server->size }} @endif
                            </p>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4 shrink-0 muted"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="font-medium heading">No servers yet</p>
                        <p class="mt-1 text-sm muted">Provision a cloud server or attach one you already run.</p>
                        <div class="mt-5 flex flex-wrap justify-center gap-3">
                            <a href="{{ route('servers.create') }}" class="button-primary">Provision server</a>
                            <a href="{{ route('servers.custom') }}" class="button-secondary">Add existing</a>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Deployments --}}
        <section class="panel !p-0 overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-white/5">
                <div>
                    <h2 class="section-title">Recent deployments</h2>
                    <p class="mt-1 text-sm muted">Latest release activity across your sites</p>
                </div>
                <a href="{{ route('sites.index') }}" class="text-sm font-semibold text-sky-600 hover:underline dark:text-sky-300">Sites</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse($recentDeployments as $deployment)
                    <a href="{{ route('deployments.show', $deployment) }}" class="flex items-start gap-3 px-6 py-4 transition hover:bg-slate-50 dark:hover:bg-white/[.03]">
                        <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $deployment->status === \App\Enums\DeploymentStatus::Successful ? 'bg-emerald-500' : ($deployment->status === \App\Enums\DeploymentStatus::Failed ? 'bg-rose-500' : 'bg-amber-400') }}"></span>
                        <div class="min-w-0 grow">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-semibold heading">{{ $deployment->site?->domain ?? 'Site' }}</p>
                                <span class="badge {{ $deployTone[$deployment->status->value] ?? 'badge-neutral' }}">{{ Str::headline($deployment->status->value) }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm muted">
                                {{ $deployment->site?->server?->name }}
                                · {{ $deployment->created_at?->diffForHumans() }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="font-medium heading">No deployments yet</p>
                        <p class="mt-1 text-sm muted">Create a site and push your first release.</p>
                        <a href="{{ route('sites.create') }}" class="button-primary mt-5 inline-flex">Add site</a>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        {{-- Plan --}}
        <section class="panel !p-0 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-white/5">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="section-title">Current plan</h2>
                    <span class="badge badge-info">{{ $planName }}</span>
                    @if($price > 0)
                        <span class="text-sm muted">{{ $currency }} {{ number_format($price / 100, 2) }}/mo</span>
                    @elseif($plan['plan'] && ! Str::contains(Str::lower($planName), 'free'))
                        <span class="text-sm muted">Free</span>
                    @endif
                    @if($renews)
                        <span class="text-sm muted">· renews {{ $renews->toFormattedDateString() }}</span>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('billing.index') }}" class="button-secondary !px-4 !py-2 text-xs">Manage billing</a>
                    @if($plan['upgrade'])
                        <a href="{{ route('billing.index') }}" class="button-primary !px-4 !py-2 text-xs">Upgrade to {{ $plan['upgrade']->name }}</a>
                    @endif
                </div>
            </div>
            <div class="grid gap-6 px-6 py-6 sm:grid-cols-3">
                @foreach($plan['usage'] as $resource => $figures)
                    @php
                        $unlimited = $figures['limit'] < 0;
                        $percent = $unlimited || $figures['limit'] === 0 ? 0 : min(100, $figures['used'] * 100 / $figures['limit']);
                        $exhausted = ! $unlimited && $figures['limit'] > 0 && $figures['used'] >= $figures['limit'];
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-sm font-medium heading">{{ $resourceLabels[$resource] ?? Str::headline($resource) }}</span>
                            <span class="tnum text-sm font-semibold {{ $exhausted ? 'text-rose-600 dark:text-rose-400' : 'muted' }}">
                                {{ $figures['used'] }} / {{ $unlimited ? '∞' : $figures['limit'] }}
                            </span>
                        </div>
                        <div class="meter mt-3">
                            <span class="meter-fill {{ $exhausted ? '!bg-rose-500' : '!bg-sky-500' }}" style="width: {{ $unlimited ? 100 : $percent }}%"></span>
                        </div>
                        @if($exhausted)
                            <p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">Limit reached — upgrade to add more.</p>
                        @endif
                    </div>
                @endforeach
            </div>
            @if(! $plan['upgrade'] && $plan['plan'])
                <p class="border-t border-slate-100 px-6 py-4 text-xs muted dark:border-white/5">
                    {{ $planName }} is the highest plan available, so there is nothing to upgrade to.
                </p>
            @endif
        </section>

        {{-- Health --}}
        @php
            $meters = [
                ['label' => 'CPU load', 'value' => $health['cpu'], 'tone' => '!bg-sky-500'],
                ['label' => 'Memory usage', 'value' => $health['memory'], 'tone' => '!bg-sky-400'],
                ['label' => 'Agent uptime', 'value' => $health['uptime'], 'tone' => '!bg-emerald-500'],
            ];
        @endphp
        <section class="panel !p-0 overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-white/5">
                <div>
                    <h2 class="section-title">Operational health</h2>
                    <p class="mt-1 text-sm muted">Fleet averages over the last 24 hours</p>
                </div>
                <span class="badge badge-neutral">Last 24 hours</span>
            </div>
            <div class="grid gap-6 px-6 py-6 sm:grid-cols-3">
                @foreach($meters as $meter)
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-sm font-medium heading">{{ $meter['label'] }}</span>
                            <span class="tnum text-sm font-semibold muted">{{ $meter['value'] === null ? 'No data' : $meter['value'].'%' }}</span>
                        </div>
                        <div class="meter mt-3">
                            <span class="meter-fill {{ $meter['tone'] }}" style="width: {{ min(100, max(0, $meter['value'] ?? 0)) }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($health['samples'] === 0)
                <p class="border-t border-slate-100 px-6 py-4 text-xs muted dark:border-white/5">
                    No monitoring agent has reported yet. Enable monitoring on a server to populate this panel.
                </p>
            @endif
        </section>
    </div>
</div>
@endsection
