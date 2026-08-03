@extends('layouts.app')
@section('content')
<div class="app-main !max-w-6xl">
    <a class="page-eyebrow" href="{{ route('cloud-accounts') }}">&larr; Provider connections</a>
    <div class="mt-3 flex flex-wrap items-end justify-between gap-4"><div><p class="page-eyebrow">DigitalOcean discovery</p><h1 class="page-title">Connect a Droplet</h1><p class="mt-2 text-slate-500 dark:text-slate-400">Active Droplets from {{ $account->name }}. Importing queues the CloudDeck bootstrap before sites can be created.</p></div><a class="button-secondary" href="{{ route('ssh-keys') }}">Manage SSH keys</a></div>
    @if($errors->any())<div class="mt-5 rounded-xl border border-rose-200 dark:border-rose-400/20 bg-rose-50 dark:bg-rose-400/10 p-4 text-sm text-rose-700 dark:text-rose-200">{{ $errors->first() }}</div>@endif
    @if($keys->isEmpty())<div class="mt-6 rounded-xl border border-amber-200 dark:border-amber-400/20 bg-amber-50 dark:bg-amber-400/10 p-4 text-sm text-amber-700 dark:text-amber-200">Generate a managed SSH key, then install its public key for the Droplet's root user before importing.</div>@endif
    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        @forelse($droplets as $droplet)
            @php
                $providerId = (string) data_get($droplet, 'id');
                $connected = $imported->get($providerId);
                $publicIp = collect(data_get($droplet, 'networks.v4', []))->firstWhere('type', 'public')['ip_address'] ?? null;
            @endphp
            <article class="panel">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-semibold">{{ data_get($droplet, 'name') }}</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $publicIp ?: 'No public IPv4' }} / {{ data_get($droplet, 'region.slug') }} / {{ data_get($droplet, 'size_slug') }}</p></div><span class="rounded-full px-3 py-1 text-xs {{ data_get($droplet, 'status') === 'active' ? 'bg-emerald-50 dark:bg-emerald-400/10 text-emerald-600 dark:text-emerald-300' : 'bg-amber-50 dark:bg-amber-400/10 text-amber-600 dark:text-amber-300' }}">{{ data_get($droplet, 'status') }}</span></div>
                @if($connected)
                    <div class="mt-5 flex items-center justify-between rounded-xl bg-slate-50 dark:bg-white/5 p-4 text-sm"><span>Connected / <span class="capitalize">{{ $connected->status->value }}</span></span><a class="text-cyan-600 dark:text-cyan-300" href="{{ route('servers.manage',$connected) }}">Manage</a></div>
                @else
                    <form method="POST" action="{{ route('cloud-accounts.servers.store',$account) }}" class="mt-5">@csrf<input type="hidden" name="provider_id" value="{{ $providerId }}"><label class="text-sm">SSH key installed on this Droplet<select class="field" name="ssh_key_id"><option value="">Select a managed private key</option>@foreach($keys as $key)<option value="{{ $key->id }}">{{ $key->name }}</option>@endforeach</select></label><button class="button-primary mt-4" @disabled($keys->isEmpty() || data_get($droplet, 'status') !== 'active' || ! $publicIp)>Import and bootstrap</button></form>
                @endif
            </article>
        @empty
            <div class="panel text-center text-slate-500 dark:text-slate-400 lg:col-span-2">No Droplets were returned by this DigitalOcean account.</div>
        @endforelse
    </div>
</div>
@endsection
