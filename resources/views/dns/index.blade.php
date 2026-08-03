@extends('layouts.app')
@section('content')
<div class="app-main">
    <p class="page-eyebrow">Domain names</p>
    <h1 class="page-title">DNS</h1>
    <p class="page-subtitle">Connect Cloudflare, import the zones you want to manage, and edit records without leaving the console.</p>

    @if($errors->any())
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[380px_1fr]">
        <form method="POST" action="{{ route('dns.accounts.store') }}" class="panel h-fit">@csrf
            <h2 class="flex items-center gap-3 section-title">
                <span class="stat-icon bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M17.5 19a4.5 4.5 0 0 0 .5-8.97A6 6 0 0 0 6.2 9.4 4.5 4.5 0 0 0 6.5 19h11Z"/></svg></span>
                Connect Cloudflare
            </h2>
            <label class="mt-5 block text-sm heading">Connection name<input class="field" name="name" value="{{ old('name') }}" placeholder="Cloudflare"></label>
            <label class="mt-4 block text-sm heading">API token<input class="field" type="password" name="token" autocomplete="off"></label>
            <p class="mt-2 text-xs muted">Create a token in Cloudflare with <strong>Zone:Read</strong> and <strong>DNS:Edit</strong>. It is verified now, then encrypted before storage. A global API key is not accepted — a scoped token limits what a leak is worth.</p>
            <button class="button-primary mt-5 w-full">Validate and connect</button>
        </form>

        <div class="space-y-3">
            <div class="flex items-center justify-between gap-4 pb-1">
                <h2 class="section-title">Connections</h2>
                <span class="badge badge-info">{{ $accounts->count() }} {{ Str::plural('connection', $accounts->count()) }}</span>
            </div>

            @forelse($accounts as $account)
                <article class="panel">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold heading">{{ $account->name }}</h3>
                            <p class="mt-1 text-sm muted">Cloudflare · {{ $account->zones_count }} {{ Str::plural('zone', $account->zones_count) }} imported</p>
                            @if($account->validated_at)
                                <p class="mt-1 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M20 6 9 17l-5-5"/></svg>Validated {{ $account->validated_at->diffForHumans() }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('dns.accounts.sync', $account) }}">@csrf
                                <button class="button-secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg>
                                    Import zones
                                </button>
                            </form>
                            <form method="POST" action="{{ route('dns.accounts.destroy', $account) }}" onsubmit="return confirm('Disconnect {{ $account->name }}? Zones stay untouched at Cloudflare; only this console forgets them.')">@csrf @method('DELETE')
                                <button class="button-ghost !text-rose-600 hover:!bg-rose-50 dark:!text-rose-300 dark:hover:!bg-rose-400/10">Disconnect</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="dashed-cta">
                    <span class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M17.5 19a4.5 4.5 0 0 0 .5-8.97A6 6 0 0 0 6.2 9.4 4.5 4.5 0 0 0 6.5 19h11Z"/></svg>
                    </span>
                    <span class="text-sm muted">No DNS provider connected yet. Paste a Cloudflare token to manage records from here.</span>
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-10 flex items-center justify-between gap-4">
        <h2 class="section-title">Zones</h2>
        <span class="badge badge-neutral">{{ $zones->count() }} {{ Str::plural('zone', $zones->count()) }}</span>
    </div>

    <div class="mt-4 space-y-3">
        @forelse($zones as $zone)
            <a href="{{ route('dns.zones.show', $zone) }}" class="panel panel-interactive flex items-center gap-4">
                <span class="stat-icon shrink-0 bg-blue-50 text-[#0058bc] dark:bg-blue-400/10 dark:text-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/></svg>
                </span>
                <div class="min-w-0 grow">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="truncate font-display text-lg font-semibold heading">{{ $zone->name }}</h3>
                        <span class="badge {{ $zone->status === 'active' ? 'badge-success' : 'badge-warning' }} capitalize"><span class="badge-dot {{ $zone->status === 'active' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>{{ $zone->status }}</span>
                    </div>
                    <p class="mt-1 text-sm muted">{{ $zone->account?->name ?? 'Cloudflare' }}@if($zone->synced_at) · imported {{ $zone->synced_at->diffForHumans() }}@endif</p>
                </div>
                <span class="grid size-9 shrink-0 place-items-center rounded-full border border-slate-200 muted dark:border-white/10"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m9 18 6-6-6-6"/></svg></span>
            </a>
        @empty
            <div class="panel text-center muted">No zones yet. Connect Cloudflare, then use <strong>Import zones</strong> to pull the domains on that account.</div>
        @endforelse
    </div>
</div>
@endsection
