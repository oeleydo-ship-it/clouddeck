@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-5 py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-cyan-600 dark:text-cyan-300">Infrastructure</p><h1 class="mt-1 text-3xl font-semibold heading">Servers</h1><p class="mt-2 muted">Provision new hosts, or open one to manage databases, SSL, workers, and monitoring.</p></div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('cloud-accounts') }}" class="button-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg>
                Import existing Droplet
            </a>
            <a href="{{ route('servers.create') }}" class="button-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
                Provision server
            </a>
        </div>
    </div>

    @php
        $tints = [
            'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
            'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300',
            'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',
        ];
    @endphp

    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 dark:border-white/10 dark:bg-white/[.03] dark:shadow-none">
        @if($servers->isNotEmpty())
            <div class="hidden border-b border-slate-100 px-6 py-3 text-xs font-medium uppercase tracking-wide muted lg:grid lg:grid-cols-[1.4fr_1.4fr_1fr_190px_140px] lg:gap-4 dark:border-white/5">
                <span>Server</span><span>Details</span><span>Resources</span><span>Status</span><span class="text-right">Actions</span>
            </div>
        @endif
        @forelse($servers as $server)
            @php $tint = match($server->status->value) { 'ready','active' => 'emerald', 'failed' => 'rose', default => 'amber' }; @endphp
            <div class="data-row grid items-center gap-4 lg:grid-cols-[1.4fr_1.4fr_1fr_190px_140px]">
                <a href="{{ route('servers.manage',$server) }}" class="min-w-0">
                    <p class="truncate font-medium heading">{{ $server->name }}</p>
                    <p class="mt-1 truncate text-xs muted">{{ $server->public_ip ?? $server->hostname }}</p>
                    @if($server->team)<span class="badge mt-1 bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $server->team->name }}</span>@endif
                </a>
                <div class="text-sm muted">{{ $server->region }} / {{ $server->size }} / {{ $server->sites->count() }} sites</div>
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <span class="rounded-lg bg-slate-50 p-2 dark:bg-white/5"><span class="muted">CPU</span><br><b class="heading">{{ $server->latestMetric?->cpu_percent ?? '-' }}{{ $server->latestMetric ? '%' : '' }}</b></span>
                    <span class="rounded-lg bg-slate-50 p-2 dark:bg-white/5"><span class="muted">RAM</span><br><b class="heading">{{ $server->latestMetric?->memory_percent ?? '-' }}{{ $server->latestMetric ? '%' : '' }}</b></span>
                    <span class="rounded-lg bg-slate-50 p-2 dark:bg-white/5"><span class="muted">Disk</span><br><b class="heading">{{ $server->latestMetric?->disk_percent ?? '-' }}{{ $server->latestMetric ? '%' : '' }}</b></span>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs"><span class="badge {{ $tints[$tint] }} capitalize"><span class="badge-dot bg-{{ $tint }}-500"></span>{{ $server->status->value }}</span><span class="muted">{{ $server->progress }}%</span></div>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full {{ $tint === 'rose' ? 'bg-rose-500' : 'bg-gradient-to-r from-cyan-400 to-blue-500' }}" style="width:{{ $server->progress }}%"></div></div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('servers.manage',$server) }}" title="Manage server" class="icon-button !border-cyan-200 !bg-cyan-50 !text-cyan-600 dark:!border-cyan-400/20 dark:!bg-cyan-400/10 dark:!text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></a>
                    <a href="{{ route('servers.index') }}" title="Refresh" class="icon-button"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg></a>
                    <a href="{{ route('servers.manage',$server) }}#danger-zone" title="Delete server" class="icon-button hover:!border-rose-200 hover:!text-rose-600 dark:hover:!border-rose-400/30 dark:hover:!text-rose-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z"/></svg></a>
                </div>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <p class="font-medium heading">No servers yet</p>
                <p class="mt-1 text-sm muted">Provision a new host, or import a Droplet you already run.</p>
                <a href="{{ route('servers.create') }}" class="button-primary mt-5">Provision your first server</a>
            </div>
        @endforelse
    </section>
    <div class="mt-6">{{ $servers->links() }}</div>
</div>
@endsection
