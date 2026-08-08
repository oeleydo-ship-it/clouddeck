{{-- Shared “Provision” menu: managed (platform) vs BYOS (customer cloud). --}}
@php
    $canManaged = ($managedServersReady ?? false) && ($planFeatures['managed_servers'] ?? false);
    $canByos = $planFeatures['providers'] ?? true;
    $buttonClass = $buttonClass ?? 'button-primary';
@endphp
@if($canManaged || $canByos)
    <div class="relative" x-data="{ open: false }">
        <button type="button" class="{{ $buttonClass }}" @click="open = ! open" @click.outside="open = false" aria-haspopup="menu" :aria-expanded="open">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
            Provision server
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 opacity-80" :class="open && 'rotate-180'"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div x-cloak x-show="open" x-transition.origin.top.right class="menu-panel !right-0 !left-auto !w-72" role="menu">
            @if($canManaged)
                <a href="{{ route('servers.managed') }}" class="flex items-start gap-3 px-3 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-white/5" role="menuitem" @click="open = false">
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-cyan-50 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><path d="M6 6h.01M6 18h.01"/></svg>
                    </span>
                    <span>
                        <span class="block font-medium heading">Managed server</span>
                        <span class="mt-0.5 block text-xs muted">We create and host the VPS for you</span>
                    </span>
                </a>
            @endif
            @if($canByos)
                <a href="{{ route('servers.create') }}" class="flex items-start gap-3 px-3 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-white/5" role="menuitem" @click="open = false">
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
                    </span>
                    <span>
                        <span class="block font-medium heading">Provision your server</span>
                        <span class="mt-0.5 block text-xs muted">Bring your own cloud account (BYOS)</span>
                    </span>
                </a>
            @endif
        </div>
    </div>
@endif
