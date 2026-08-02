@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-5 py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-cyan-600 dark:text-cyan-300">Infrastructure</p><h1 class="mt-1 text-3xl font-semibold heading">Servers</h1><p class="mt-2 muted">Provision new hosts, or open one to manage databases, SSL, workers, and monitoring.</p></div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('servers.custom') }}" class="button-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><path d="M6 6h.01M6 18h.01"/></svg>
                Add existing server
            </a>
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

    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 dark:border-white/10 dark:bg-white/[.03] dark:shadow-none">
        @if($servers->isNotEmpty())
            <div class="hidden border-b border-slate-100 px-6 py-3 text-xs font-medium uppercase tracking-wide muted lg:grid lg:grid-cols-[1.4fr_1.4fr_1fr_190px_140px] lg:gap-4 dark:border-white/5">
                <span>Server</span><span>Details</span><span>Resources</span><span>Status</span><span class="text-right">Actions</span>
            </div>
        @endif
        @livewire('server-status-list', ['servers' => $servers->getCollection()])
    </section>
    <div class="mt-6">{{ $servers->links() }}</div>
</div>
@endsection
