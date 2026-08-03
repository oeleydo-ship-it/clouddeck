@extends('layouts.app')
@section('content')
<div class="app-main">
    <a class="link-action" href="{{ route('dns.index') }}">← DNS</a>
    <h1 class="page-title">{{ $zone->name }}</h1>
    <p class="page-subtitle">Records are read from Cloudflare each time this page loads, so what you see is what the zone actually holds.</p>

    @if($errors->any())
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>
    @endif
    @if($error)
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">{{ $error }}</div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[380px_1fr]">
        <div class="space-y-6">
            <form method="POST" action="{{ route('dns.records.store', $zone) }}" class="panel h-fit" x-data="{ type: '{{ old('type', 'A') }}' }">@csrf
                <h2 class="section-title">Add record</h2>
                <label class="mt-5 block text-sm heading">Type
                    <select class="field" name="type" x-model="type">
                        @foreach($types as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>@endforeach
                    </select>
                </label>
                <label class="mt-4 block text-sm heading">Name<input class="field" name="name" value="{{ old('name') }}" placeholder="{{ $zone->name }}"></label>
                <p class="mt-1 text-xs muted">Use <code>@</code> for the root, or a subdomain such as <code>www</code>.</p>
                <label class="mt-4 block text-sm heading">Content<input class="field font-mono text-sm" name="content" value="{{ old('content') }}" placeholder="203.0.113.10"></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="mt-4 block text-sm heading">TTL
                        <select class="field" name="ttl">
                            <option value="1" @selected(old('ttl', '1') === '1')>Automatic</option>
                            <option value="60">1 minute</option>
                            <option value="300">5 minutes</option>
                            <option value="1800">30 minutes</option>
                            <option value="3600">1 hour</option>
                            <option value="86400">1 day</option>
                        </select>
                    </label>
                    <label class="mt-4 block text-sm heading" x-show="type === 'MX'" x-cloak>Priority<input class="field" type="number" name="priority" value="{{ old('priority', 10) }}" min="0" max="65535"></label>
                </div>
                {{-- Only these three can sit behind Cloudflare's proxy; offering it on a TXT
                     record would be a checkbox the API rejects. --}}
                <label class="mt-4 flex items-center gap-2 text-sm heading" x-show="['A','AAAA','CNAME'].includes(type)" x-cloak>
                    <input type="checkbox" name="proxied" value="1" class="size-4 rounded border-slate-300" @checked(old('proxied'))>
                    Proxy through Cloudflare
                </label>
                <button class="button-primary mt-5 w-full">Add record</button>
            </form>

            @if($sites->isNotEmpty())
                <div class="panel">
                    <h2 class="section-title">Point a site here</h2>
                    <p class="mt-2 text-sm muted">Sites in this zone that are already on a server. Adding the record fills the form above with that server's address.</p>
                    <div class="mt-4 space-y-2">
                        @foreach($sites as $site)
                            <form method="POST" action="{{ route('dns.records.store', $zone) }}" class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 dark:border-white/10">@csrf
                                <input type="hidden" name="type" value="A">
                                <input type="hidden" name="name" value="{{ $site->domain }}">
                                <input type="hidden" name="content" value="{{ $site->server->public_ip }}">
                                <input type="hidden" name="ttl" value="1">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium heading">{{ $site->domain }}</span>
                                    <span class="block truncate font-mono text-xs muted">A → {{ $site->server->public_ip }}</span>
                                </span>
                                <button class="button-secondary shrink-0">Add</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_4px_6px_-1px_rgba(0,0,0,0.05)] dark:border-white/10 dark:bg-white/[.03] dark:shadow-none">
            <div class="table-head hidden lg:grid lg:grid-cols-[90px_1.3fr_1.6fr_110px_120px] lg:gap-4">
                <span>Type</span><span>Name</span><span>Content</span><span>TTL</span><span class="text-right">Actions</span>
            </div>
            @forelse($records as $record)
                <div class="data-row grid items-center gap-4 lg:grid-cols-[90px_1.3fr_1.6fr_110px_120px]">
                    <span class="badge badge-neutral w-fit font-mono">{{ $record['type'] }}</span>
                    <span class="min-w-0 truncate text-sm font-medium heading">{{ $record['name'] }}</span>
                    <span class="min-w-0 truncate font-mono text-xs muted" title="{{ $record['content'] }}">{{ $record['content'] }}</span>
                    <span class="text-xs muted">{{ $record['ttl'] === 1 ? 'Auto' : $record['ttl'].'s' }}</span>
                    <div class="flex items-center justify-end gap-2">
                        @if($record['proxied'])
                            <span class="badge badge-warning" title="Proxied through Cloudflare">Proxied</span>
                        @endif
                        <form method="POST" action="{{ route('dns.records.destroy', [$zone, $record['id']]) }}" onsubmit="return confirm('Delete the {{ $record['type'] }} record for {{ $record['name'] }}? DNS changes take effect for everyone.')">@csrf @method('DELETE')
                            <button class="button-ghost !text-rose-600 hover:!bg-rose-50 dark:!text-rose-300 dark:hover:!bg-rose-400/10" title="Delete record">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <p class="font-medium heading">{{ $error ? 'Records could not be read' : 'No records in this zone' }}</p>
                    <p class="mt-1 text-sm muted">{{ $error ? 'Fix the connection above, then reload.' : 'Add one with the form beside this list.' }}</p>
                </div>
            @endforelse
        </section>
    </div>
</div>
@endsection
