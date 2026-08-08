@extends('layouts.app')
@section('content')
<div class="app-main">
    <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
        <div>
            <p class="page-eyebrow">Infrastructure</p>
            <h1 class="page-title">Servers</h1>
            <p class="page-subtitle">Provision new hosts, or open one to manage databases, SSL, workers, and monitoring.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @if(($managedServersReady ?? false) && ($planFeatures['managed_servers'] ?? false))
                <a href="{{ route('servers.managed') }}" class="button-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
                    Managed server
                </a>
            @endif
            <a href="{{ route('servers.custom') }}" class="button-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><path d="M6 6h.01M6 18h.01"/></svg>
                Add existing server
            </a>
            @if($planFeatures['providers'] ?? true)
                <a href="{{ route('cloud-accounts') }}" class="button-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg>
                    Import existing Droplet
                </a>
            @endif
            @if($planFeatures['providers'] ?? true)
                <a href="{{ route('servers.create') }}" class="button-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
                    Provision with your cloud
                </a>
            @endif
        </div>
    </header>

    @php
        $summaryCards = [
            ['label' => 'Total servers', 'value' => $summary['total'], 'note' => $summary['total'] === 1 ? 'Host' : 'Hosts', 'tint' => 'bg-blue-50 text-[#0058bc] dark:bg-blue-400/10 dark:text-blue-300', 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01'],
            ['label' => 'Agent uptime', 'value' => $summary['uptime'] === null ? '—' : $summary['uptime'].'%', 'note' => $summary['uptime'] === null ? 'Monitoring off' : 'Fleet average', 'tint' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300', 'icon' => 'M3 3v18h18M7 15l4-5 3 3 5-7'],
            ['label' => 'CPU load', 'value' => $summary['cpu'] === null ? '—' : $summary['cpu'].'%', 'note' => $summary['cpu'] === null ? 'No samples' : 'Last 24 hours', 'tint' => 'bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300', 'icon' => 'M6 6h12v12H6zM9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4'],
            ['label' => 'Open alerts', 'value' => $summary['alerts'], 'note' => $summary['alerts'] > 0 ? 'Needs review' : 'All stable', 'danger' => $summary['alerts'] > 0, 'tint' => 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300', 'icon' => 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0'],
        ];
    @endphp
    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($summaryCards as $card)
            <div class="stat-card">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <span class="stat-icon {{ $card['tint'] }}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="{{ $card['icon'] }}"/></svg></span>
                    <p class="stat-label text-right">{{ $card['label'] }}</p>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="stat-value {{ !empty($card['danger']) ? '!text-rose-600 dark:!text-rose-400' : '' }}">{{ $card['value'] }}</span>
                    <span class="text-xs font-medium {{ !empty($card['danger']) ? 'text-rose-600 dark:text-rose-400' : 'muted' }}">{{ $card['note'] }}</span>
                </div>
            </div>
        @endforeach
    </section>

    <section class="mt-8 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)] dark:border-white/10 dark:bg-white/[.03] dark:shadow-none">
        @if($servers->isNotEmpty())
            <div class="table-head hidden lg:grid lg:grid-cols-[1.4fr_1.4fr_1fr_190px_140px] lg:gap-4">
                <span>Server</span><span>Details</span><span>Resources</span><span>Status</span><span class="text-right">Actions</span>
            </div>
        @endif
        @livewire('server-status-list', ['servers' => $servers->getCollection()])
    </section>
    <div class="mt-6">{{ $servers->links() }}</div>
</div>
@endsection
