<!doctype html><html lang="en" x-data="{dark: localStorage.theme === 'dark'}" x-init="$watch('dark', v => localStorage.theme = v ? 'dark' : 'light')" :class="dark && 'dark'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title ?? 'CloudDeck' }}</title>@vite(['resources/css/app.css','resources/js/app.js']) @livewireStyles</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,.08),transparent_35%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,.12),transparent_35%)]"></div>
<header class="relative border-b border-slate-200 bg-white/90 backdrop-blur dark:border-white/10 dark:bg-slate-950/80">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
        <a href="/" class="flex items-center gap-3 font-semibold text-slate-900 dark:text-white"><span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 font-bold text-white shadow-md shadow-cyan-500/25">C</span>CloudDeck</a>
        <nav class="flex items-center gap-2">
            @auth
                <div class="hidden items-center gap-1 md:flex">
                    <a href="/dashboard" class="nav-link {{ request()->is('dashboard') ? '!bg-slate-100 !text-slate-900 dark:!bg-white/10 dark:!text-white' : '' }}">Dashboard</a>
                    <a href="/servers" class="nav-link {{ request()->is('servers*') ? '!bg-slate-100 !text-slate-900 dark:!bg-white/10 dark:!text-white' : '' }}">Servers</a>
                    <a href="/sites" class="nav-link {{ request()->is('sites*') ? '!bg-slate-100 !text-slate-900 dark:!bg-white/10 dark:!text-white' : '' }}">Sites</a>
                    <a href="/cloud-accounts" class="nav-link {{ request()->is('cloud-accounts*') ? '!bg-slate-100 !text-slate-900 dark:!bg-white/10 dark:!text-white' : '' }}">Providers</a>
                    <a href="/ssh-keys" class="nav-link {{ request()->is('ssh-keys*') ? '!bg-slate-100 !text-slate-900 dark:!bg-white/10 dark:!text-white' : '' }}">SSH keys</a>
                    <a href="/teams" class="nav-link {{ request()->is('teams*') ? '!bg-slate-100 !text-slate-900 dark:!bg-white/10 dark:!text-white' : '' }}">Teams</a>
                    <a href="/billing" class="nav-link {{ request()->is('billing*') ? '!bg-slate-100 !text-slate-900 dark:!bg-white/10 dark:!text-white' : '' }}">Billing</a>
                    @if(auth()->user()->isSuperAdmin())<a href="/admin" class="nav-link !text-amber-600 dark:!text-amber-300 {{ request()->is('admin*') ? '!bg-amber-50 dark:!bg-amber-400/10' : '' }}">Admin</a>@endif
                    <a href="/account" class="nav-link {{ request()->is('account*') ? '!bg-slate-100 !text-slate-900 dark:!bg-white/10 dark:!text-white' : '' }}">Account</a>
                </div>
                <button type="button" @click="dark = !dark" class="icon-button" title="Toggle dark mode" aria-label="Toggle dark mode">
                    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                    <svg x-cloak x-show="dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                </button>
                <form method="POST" action="/logout">@csrf<button class="button-secondary">Sign out</button></form>
            @else
                <button type="button" @click="dark = !dark" class="icon-button" title="Toggle dark mode" aria-label="Toggle dark mode">
                    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                    <svg x-cloak x-show="dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                </button>
                <a href="/login" class="nav-link">Sign in</a><a href="/register" class="button-primary">Get started</a>
            @endauth
        </nav>
    </div>
</header>
<main class="relative">@if(session('status'))<div class="mx-auto mt-5 max-w-7xl px-5"><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('status') }}</div></div>@endif{{ $slot ?? '' }}@yield('content')</main>@livewireScripts</body></html>
