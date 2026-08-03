@extends('layouts.app')
@section('content')
<div class="app-main">
    <header class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <p class="page-eyebrow">Control plane</p>
            <h1 class="page-title">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}</h1>
        </div>
        <a href="{{ route('servers.create') }}" class="button-primary h-12 shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
            Provision server
        </a>
    </header>

    @php
        $cards = [
            ['label' => 'Total servers', 'value' => $stats['servers'], 'note' => $stats['servers'] === 1 ? 'Host' : 'Hosts', 'tint' => 'cyan', 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3Z M8 7h.01M8 18h.01'],
            ['label' => 'Active servers', 'value' => $stats['active'], 'note' => 'Ready', 'tint' => 'emerald', 'dot' => $stats['active'] > 0 ? 'emerald' : null, 'icon' => 'm9 12 2 2 4-4 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['label' => 'Deployments today', 'value' => $stats['deployments'], 'note' => 'Shipped', 'tint' => 'blue', 'icon' => 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z'],
            ['label' => 'Failed deployments', 'value' => $stats['failed'], 'note' => $stats['failed'] > 0 ? 'Needs review' : 'All clear', 'tint' => 'rose', 'dot' => $stats['failed'] > 0 ? 'rose' : null, 'danger' => $stats['failed'] > 0, 'icon' => 'M12 9v4M12 17h.01 M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z'],
            ['label' => 'Offline agents', 'value' => $stats['offline'], 'note' => $stats['offline'] > 0 ? 'Unreachable' : 'Reporting', 'tint' => 'rose', 'dot' => $stats['offline'] > 0 ? 'rose' : null, 'danger' => $stats['offline'] > 0, 'icon' => 'm4.9 4.9 14.2 14.2 M9 9a5 5 0 0 1 7 7M5 13a9 9 0 0 1 2.5-6.3M12 20h.01'],
        ];
        $tints = [
            'cyan' => 'bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300',
            'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300',
            'blue' => 'bg-blue-50 text-[#0058bc] dark:bg-blue-400/10 dark:text-blue-300',
            'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300',
        ];
    @endphp
    <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        @foreach($cards as $stat)
            <div class="stat-card">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <p class="stat-label">{{ $stat['label'] }}</p>
                    <span class="stat-icon {{ $tints[$stat['tint']] }}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="{{ $stat['icon'] }}"/></svg></span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="stat-value {{ !empty($stat['danger']) ? '!text-rose-600 dark:!text-rose-400' : '' }}">{{ $stat['value'] }}</span>
                    @if(!empty($stat['dot']))<span class="badge-dot bg-{{ $stat['dot'] }}-500 animate-pulse"></span>@endif
                    <span class="text-xs font-medium muted">{{ $stat['note'] }}</span>
                </div>
            </div>
        @endforeach
    </section>

    <section class="mt-8 grid gap-4 md:grid-cols-2">
        <a href="{{ route('servers.index') }}" class="panel panel-interactive group flex items-center gap-4">
            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01"/></svg></span>
            <div class="min-w-0 grow">
                <p class="section-title">Servers</p>
                <p class="mt-1 text-sm muted">{{ $stats['servers'] }} total · {{ $stats['active'] }} active · manage and provision</p>
            </div>
            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-slate-200 muted transition group-hover:border-[#0070eb] group-hover:text-[#0058bc] dark:border-white/10"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m9 18 6-6-6-6"/></svg></span>
        </a>
        <a href="{{ route('sites.index') }}" class="panel panel-interactive group flex items-center gap-4">
            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-6"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/></svg></span>
            <div class="min-w-0 grow">
                <p class="section-title">Sites</p>
                <p class="mt-1 text-sm muted">{{ $stats['deployments'] }} deployments today · {{ $stats['failed'] }} failed</p>
            </div>
            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-slate-200 muted transition group-hover:border-[#0070eb] group-hover:text-[#0058bc] dark:border-white/10"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m9 18 6-6-6-6"/></svg></span>
        </a>
    </section>

    @php
        $planName = $plan['plan']?->name ?? 'No plan';
        $price = $plan['plan'] ? $plan['plan']->monthly_price : null;
        $currency = $plan['plan']?->currency ?? 'USD';
        $renews = $plan['subscription']?->current_period_ends_at;
        $resourceLabels = ['servers' => 'Servers', 'sites' => 'Sites', 'databases' => 'Databases'];
    @endphp
    <section class="panel mt-8 !p-0">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-white/5">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="section-title">Current plan</h2>
                <span class="badge badge-info">{{ $planName }}</span>
                {{-- A plan already called "Free" does not need "Free" printed beside it. --}}
                @if($price > 0)
                    <span class="text-sm muted">{{ $currency }} {{ number_format($price / 100, 2) }}/mo</span>
                @elseif($plan['plan'] && ! Str::contains(Str::lower($planName), 'free'))
                    <span class="text-sm muted">Free</span>
                @endif
                @if($renews)
                    <span class="text-sm muted">· renews {{ $renews->toFormattedDateString() }}</span>
                @endif
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('billing.index') }}" class="button-secondary">Manage billing</a>
                @if($plan['upgrade'])
                    <a href="{{ route('billing.index') }}" class="button-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 19V5m0 0-7 7m7-7 7 7"/></svg>
                        Upgrade to {{ $plan['upgrade']->name }}
                    </a>
                @endif
            </div>
        </div>
        <div class="grid gap-8 px-6 py-6 md:grid-cols-3">
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
                        <span class="meter-fill {{ $exhausted ? '!bg-rose-500' : '' }}" style="width: {{ $unlimited ? 100 : $percent }}%"></span>
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

    @php
        $meters = [
            ['label' => 'CPU load', 'value' => $health['cpu'], 'tone' => 'bg-[#0058bc]', 'text' => 'text-[#0058bc] dark:text-blue-300'],
            ['label' => 'Memory usage', 'value' => $health['memory'], 'tone' => 'bg-[#00677e]', 'text' => 'text-[#00677e] dark:text-cyan-300'],
            ['label' => 'Agent uptime', 'value' => $health['uptime'], 'tone' => 'bg-emerald-500', 'text' => 'text-emerald-600 dark:text-emerald-400'],
        ];
    @endphp
    <section class="panel mt-8 !p-0">
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-white/5">
            <h2 class="section-title">Operational health</h2>
            <span class="badge badge-neutral">Last 24 hours</span>
        </div>
        <div class="grid gap-8 px-6 py-6 md:grid-cols-3">
            @foreach($meters as $meter)
                <div>
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm font-medium heading">{{ $meter['label'] }}</span>
                        <span class="tnum text-sm font-semibold {{ $meter['value'] === null ? 'muted' : $meter['text'] }}">{{ $meter['value'] === null ? 'No data' : $meter['value'].'%' }}</span>
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
@endsection
