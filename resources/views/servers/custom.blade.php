@extends('layouts.app')
@section('content')
@php
    $command = 'mkdir -p /root/.ssh && chmod 700 /root/.ssh && echo '.escapeshellarg(trim($key->public_key)).' >> /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys';
@endphp
<div class="mx-auto max-w-3xl px-5 py-10">
    <a class="text-sm font-medium text-cyan-600 dark:text-cyan-300" href="{{ route('servers.index') }}">← Servers</a>
    <h1 class="mt-2 text-3xl font-semibold heading">Attach an existing server</h1>
    <p class="mt-2 muted">For a server you already run — another provider, bare metal, or a VM. CloudDeck connects over SSH by IP; no provider token is involved.</p>

    @if($errors->any())
        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
            <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="panel mt-8">
        <h2 class="font-semibold heading">1. Authorise CloudDeck on the server</h2>
        <p class="mt-2 text-sm muted">The server must run <strong>Ubuntu 22.04 or 24.04</strong> and you must be able to SSH in as <code>root</code>. Use a freshly provisioned box where you can — CloudDeck installs and configures Nginx, PHP-FPM, Redis, and a database.</p>
        <p class="mt-4 text-sm heading">SSH into the server as root and run:</p>
        <div x-data="{ copied: false }" class="mt-2">
            <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 font-mono text-xs leading-6 text-slate-300" x-ref="cmd">{{ $command }}</pre>
            <button type="button" class="button-secondary mt-3 text-xs"
                    @click="navigator.clipboard.writeText($refs.cmd.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    x-text="copied ? 'Copied' : 'Copy to clipboard'">Copy to clipboard</button>
        </div>
        <p class="mt-3 text-xs muted">This appends CloudDeck's public key to root's authorised keys. The matching private key is encrypted at rest here and never leaves this instance.</p>
    </section>

    <form method="POST" action="{{ route('servers.custom.store') }}" class="panel mt-6">@csrf
        <h2 class="font-semibold heading">2. Tell CloudDeck where it is</h2>
        @if($account)
            <input type="hidden" name="cloud_account_id" value="{{ $account->id }}">
            <p class="mt-2 text-sm muted">Filed under your <strong>{{ $account->name }}</strong> ({{ config('clouddeck.providers.'.$account->provider.'.label', $account->provider) }}) connection.</p>
        @endif
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="text-sm heading">Server name<input class="field" name="name" value="{{ old('name', $account?->name) }}" required maxlength="100" placeholder="production"></label>
            <label class="text-sm heading">Ubuntu version<select class="field" name="image">
                <option value="ubuntu-24-04-x64" @selected(old('image') === 'ubuntu-24-04-x64')>Ubuntu 24.04</option>
                <option value="ubuntu-22-04-x64" @selected(old('image') === 'ubuntu-22-04-x64')>Ubuntu 22.04</option>
            </select></label>
            <label class="text-sm heading">IP address<input class="field font-mono text-sm" name="public_ip" value="{{ old('public_ip', request('public_ip')) }}" required placeholder="203.0.113.10"></label>
            <label class="text-sm heading">SSH port<input class="field" type="number" name="ssh_port" value="{{ old('ssh_port', request('ssh_port', 22)) }}" min="1" max="65535" required></label>
        </div>
        <button class="button-primary mt-6">Verify and provision</button>
        <p class="mt-3 text-xs muted">CloudDeck checks it can reach the server as root and that it is running Ubuntu before installing anything.</p>
    </form>
</div>
@endsection
