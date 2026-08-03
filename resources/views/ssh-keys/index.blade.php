@extends('layouts.app')
@section('content')
<div class="app-main">
    <p class="page-eyebrow">Secure access</p>
    <h1 class="page-title">SSH keys</h1>
    <p class="page-subtitle">The keys provisioning workers use to reach your fleet. Managed keys are generated and encrypted here; uploaded keys stay public-only.</p>

    @if($errors->any())
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>
    @endif

    @if(session('download_key'))
        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-400/20 dark:bg-amber-400/10">
            <a class="button-primary" href="{{ route('ssh-keys.download',session('download_key')) }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16"/></svg>
                Download private key now
            </a>
            <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">This download is available once. Store it before leaving the page.</p>
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <form method="POST" action="/ssh-keys/generate" class="panel flex flex-col">@csrf
            <h2 class="flex items-center gap-3 section-title">
                <span class="stat-icon bg-blue-50 text-[#0058bc] dark:bg-blue-400/10 dark:text-blue-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="m12 3 1.9 4.6L19 9.5l-4.6 1.9L12.5 16l-1.9-4.6L6 9.5l4.6-1.9ZM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9Z"/></svg></span>
                Generate managed key
            </h2>
            <p class="mt-3 text-sm muted">CloudDeck generates and encrypts a 4096-bit RSA key used by provisioning workers to reach your servers without manual setup.</p>
            <label class="mt-auto block pt-6 text-sm heading">Key name<input class="field" name="name" value="{{ old('name') }}" placeholder="CloudDeck primary"></label>
            <button class="button-primary mt-5 w-fit">
                Generate key
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M15 7a5 5 0 1 1-4.9 6H7v3H4v-3H2v-3h8.1A5 5 0 0 1 15 7Zm2 4h.01"/></svg>
            </button>
        </form>

        <form method="POST" action="/ssh-keys" class="panel flex flex-col">@csrf
            <h2 class="flex items-center gap-3 section-title">
                <span class="stat-icon bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M12 18v-6m0 0-2 2m2-2 2 2"/></svg></span>
                Upload public key
            </h2>
            <p class="mt-3 text-sm muted">Already have a key pair? Upload the OpenSSH public key to grant your team the same access.</p>
            <label class="mt-5 block text-sm heading">Key name<input class="field" name="name" value="{{ old('name') }}" placeholder="e.g. My MacBook Pro"></label>
            <label class="mt-4 block text-sm heading">OpenSSH public key<textarea class="field min-h-28 font-mono text-xs" name="public_key" placeholder="ssh-rsa AAAA…">{{ old('public_key') }}</textarea></label>
            <button class="button-primary mt-5 w-fit">
                Upload key
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 21V9m0 0-4 4m4-4 4 4M4 5h16"/></svg>
            </button>
        </form>
    </div>

    <div class="mt-10 flex items-center justify-between gap-4">
        <h2 class="section-title">Configured keys</h2>
        <span class="badge badge-neutral">{{ $keys->count() }} {{ Str::plural('key', $keys->count()) }} active</span>
    </div>

    <div class="mt-4 space-y-3">
        @forelse($keys as $key)
            <article class="panel panel-interactive flex flex-wrap items-center gap-4" x-data="{ copied: false }">
                <span class="stat-icon shrink-0 {{ $key->private_key ? 'bg-blue-50 text-[#0058bc] dark:bg-blue-400/10 dark:text-blue-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M15 7a5 5 0 1 1-4.9 6H7v3H4v-3H2v-3h8.1A5 5 0 0 1 15 7Zm2 4h.01"/></svg>
                </span>
                <div class="min-w-0 grow">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="font-semibold heading">{{ $key->name }}</h3>
                        <span class="badge {{ $key->private_key ? 'badge-info' : 'badge-neutral' }}">{{ $key->private_key ? 'Managed key' : 'Public key only' }}</span>
                    </div>
                    <p class="mt-1.5 break-all rounded-md bg-slate-100 px-2 py-1 font-mono text-xs muted dark:bg-white/5">{{ $key->fingerprint }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" class="button-ghost"
                            @click="navigator.clipboard.writeText(@js($key->fingerprint)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    </button>
                    <form method="POST" action="/ssh-keys/{{ $key->id }}" onsubmit="return confirm('Delete {{ $key->name }}? Servers already authorised keep working until the key is removed from them.')">@csrf @method('DELETE')
                        <button class="button-ghost !text-rose-600 hover:!bg-rose-50 dark:!text-rose-300 dark:hover:!bg-rose-400/10">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                            Delete
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="dashed-cta">
                <span class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M15 7a5 5 0 1 1-4.9 6H7v3H4v-3H2v-3h8.1A5 5 0 0 1 15 7Zm2 4h.01"/></svg>
                </span>
                <span class="text-sm muted">No SSH keys yet. Generate a managed key to let CloudDeck provision servers for you.</span>
            </div>
        @endforelse
    </div>
</div>
@endsection
