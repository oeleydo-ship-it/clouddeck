@extends('layouts.app')
@section('content')
<div class="app-main">
    <p class="page-eyebrow">Provider connections</p>
    <h1 class="page-title">Cloud accounts</h1>
    <p class="page-subtitle">Tokens are validated against the provider before being encrypted and stored in the vault.</p>
    @if($errors->any())<div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>@endif
    <div class="mt-8 grid gap-6 lg:grid-cols-[380px_1fr]">
        <form method="POST" action="/cloud-accounts" class="panel h-fit">@csrf
            @php
                $providers = config('clouddeck.providers');
                $apiFlags = collect($providers)->map(fn ($provider) => (bool) $provider['api']);
            @endphp
            <div x-data="{ provider: @js(old('provider', 'digitalocean')), api: @js($apiFlags) }">
                <h2 class="flex items-center gap-3 section-title">
                    <span class="stat-icon bg-blue-50 text-[#0058bc] dark:bg-blue-400/10 dark:text-blue-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M9 17H7A5 5 0 0 1 7 7h2M15 7h2a5 5 0 0 1 0 10h-2M8 12h8"/></svg></span>
                    Connect a provider
                </h2>
                <label class="mt-5 block text-sm heading">Provider
                    <select class="field" name="provider" x-model="provider">
                        @foreach($providers as $key => $provider)
                            <option value="{{ $key }}">{{ $provider['label'] }}{{ $provider['api'] ? '' : ' — connect by IP' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mt-4 block text-sm heading">Connection name<input class="field" name="name" value="{{ old('name') }}" placeholder="Production"></label>

                {{-- Only what the chosen provider actually needs: a token {{ $branding['name'] }} will
                     never call is not worth asking for, and an address is. --}}
                <template x-if="api[provider]">
                    <div>
                        <label class="mt-4 block text-sm heading">API token<input class="field" type="password" name="token" autocomplete="off"></label>
                        <p class="mt-2 text-xs muted">Validated now, then used to create and destroy servers. Needs read and write access to droplets and SSH keys.</p>
                    </div>
                </template>

                <template x-if="! api[provider]">
                    <div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_110px]">
                            <label class="text-sm heading">Server IP address<input class="field font-mono text-sm" name="public_ip" value="{{ old('public_ip') }}" placeholder="203.0.113.10"></label>
                            <label class="text-sm heading">SSH port<input class="field" type="number" name="ssh_port" value="{{ old('ssh_port', 22) }}" min="1" max="65535"></label>
                        </div>
                        <p class="mt-2 text-xs muted">{{ $branding['name'] }} cannot create servers at <span x-text="provider"></span>, so it connects to one you already run. Next you will authorise its SSH key on the server as root.</p>
                    </div>
                </template>

                <button class="button-primary mt-5 w-full" x-text="api[provider] ? 'Validate and connect' : 'Continue to SSH setup'">Connect</button>
            </div>
        </form>
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-4 pb-1">
                <h2 class="section-title">Active connections</h2>
                <span class="badge badge-info">{{ $accounts->count() }} {{ Str::plural('connection', $accounts->count()) }}</span>
            </div>
            @forelse($accounts as $account)
                <article class="panel panel-interactive">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01"/></svg></span>
                            <div>
                                @php $drivesApi = (bool) config('clouddeck.providers.'.$account->provider.'.api'); @endphp
                                <h2 class="font-medium heading">{{ $account->name }}</h2>
                                <p class="mt-1 text-sm muted">{{ config('clouddeck.providers.'.$account->provider.'.label', Str::title($account->provider)) }} / {{ $account->servers_count }} connected servers</p>
                                {{-- Only an API connection is ever validated: there is nothing to
                                     validate for a provider whose servers are attached by IP. --}}
                                @if($account->validated_at)
                                    <p class="mt-1 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M20 6 9 17l-5-5"/></svg>Validated {{ $account->validated_at->diffForHumans() }}</p>
                                @else
                                    <p class="mt-1 text-xs muted">Servers attached by IP over SSH</p>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="/cloud-accounts/{{ $account->id }}" onsubmit="return confirm('Disconnect {{ $account->name }}? Servers must be removed first.')">@csrf @method('DELETE')<button class="text-sm font-medium text-rose-600 hover:underline dark:text-rose-300">Disconnect</button></form>
                    </div>
                    {{-- Discovery calls the provider API, which only exists for the providers
                         {{ $branding['name'] }} drives. Offering it elsewhere would fail on the click. --}}
                    @if($drivesApi)
                        <a class="button-primary mt-5 inline-flex w-full" href="{{ route('cloud-accounts.servers',$account) }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg>
                            Discover and connect servers
                        </a>
                    @else
                        <a class="button-secondary mt-5 inline-flex w-full" href="{{ route('servers.custom', ['cloud_account' => $account->id]) }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
                            Add a server by IP
                        </a>
                    @endif
                </article>
            @empty
            @endforelse
            <div class="dashed-cta">
                <span class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5"><path d="M12 5v14M5 12h14"/></svg>
                </span>
                <span class="text-sm muted">{{ $accounts->isEmpty() ? 'Connect your first cloud provider to start provisioning.' : 'Add another cloud provider to scale your infrastructure.' }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
