@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-5xl px-5 py-10">
    <p class="text-sm font-medium text-cyan-600 dark:text-cyan-300">Provider connections</p>
    <h1 class="mt-1 text-3xl font-semibold heading">Cloud accounts</h1>
    <p class="mt-2 muted">Tokens are validated against DigitalOcean before being encrypted and stored.</p>
    @if($errors->any())<div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>@endif
    <div class="mt-8 grid gap-6 lg:grid-cols-[380px_1fr]">
        <form method="POST" action="/cloud-accounts" class="panel h-fit">@csrf
            <h2 class="font-semibold heading">Connect a provider</h2>
            @php $providers = config('clouddeck.providers'); @endphp
            <label class="mt-5 block text-sm heading">Provider
                <select class="field" name="provider" x-data x-ref="provider">
                    @foreach($providers as $key => $provider)
                        <option value="{{ $key }}" @selected(old('provider', 'digitalocean') === $key)>{{ $provider['label'] }}{{ $provider['api'] ? '' : ' — connect by IP' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="mt-4 block text-sm heading">Connection name<input class="field" name="name" value="{{ old('name') }}" placeholder="Production"></label>
            <label class="mt-4 block text-sm heading">API token<input class="field" type="password" name="token" autocomplete="off"></label>
            <p class="mt-2 text-xs muted">DigitalOcean tokens are validated and used to create and destroy servers; they need read and write access to droplets and SSH keys. Other providers are stored for your reference — attach their servers with <a class="text-cyan-600 dark:text-cyan-300" href="{{ route('servers.custom') }}">Add existing server</a>.</p>
            <button class="button-primary mt-5 w-full">Connect</button>
        </form>
        <div class="space-y-3">
            @forelse($accounts as $account)
                <article class="panel">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01"/></svg></span>
                            <div>
                                <h2 class="font-medium heading">{{ $account->name }}</h2>
                                <p class="mt-1 text-sm capitalize muted">{{ $account->provider }} / {{ $account->servers_count }} connected servers</p>
                                <p class="mt-1 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M20 6 9 17l-5-5"/></svg>Validated {{ $account->validated_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <form method="POST" action="/cloud-accounts/{{ $account->id }}" onsubmit="return confirm('Disconnect {{ $account->name }}? Servers must be removed first.')">@csrf @method('DELETE')<button class="text-sm font-medium text-rose-600 hover:underline dark:text-rose-300">Disconnect</button></form>
                    </div>
                    <a class="button-primary mt-5 inline-flex w-full" href="{{ route('cloud-accounts.servers',$account) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg>
                        Discover and connect Droplets
                    </a>
                </article>
            @empty
                <div class="panel text-center muted">No cloud accounts connected.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
