<!doctype html>
<html lang="en" x-data="{dark: localStorage.theme === 'dark', marketingMenu: false}" x-init="$watch('dark', v => localStorage.theme = v ? 'dark' : 'light')" :class="dark && 'dark'">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $seo = $seo ?? ['description' => null, 'keywords' => null, 'og_image' => null, 'robots' => 'index,follow'];
        $analytics = $analytics ?? ['ga_measurement_id' => null, 'gsc_verification' => null];
        $pageTitle = $title ?? ($branding['name'] ?? config('app.name', 'Uplary'));
        $pageDescription = $metaDescription ?? ($seo['description'] ?? null);
    @endphp
    <title>{{ $pageTitle }}</title>
    @if($pageDescription)
        <meta name="description" content="{{ $pageDescription }}">
        <meta property="og:description" content="{{ $pageDescription }}">
    @endif
    @if(! empty($seo['keywords']))
        <meta name="keywords" content="{{ $seo['keywords'] }}">
    @endif
    @if(! empty($seo['robots']))
        <meta name="robots" content="{{ $seo['robots'] }}">
    @endif
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(! empty($seo['og_image']))
        <meta property="og:image" content="{{ $seo['og_image'] }}">
    @endif
    @if(! empty($analytics['gsc_verification']))
        <meta name="google-site-verification" content="{{ $analytics['gsc_verification'] }}">
    @endif
    @if(! empty($analytics['ga_measurement_id']))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analytics['ga_measurement_id'] }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @js($analytics['ga_measurement_id']));
        </script>
    @endif
    <script>
        try {
            if (localStorage.theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
    @php
        $insertCode = $insertCode ?? ['head' => null, 'body' => null, 'on_marketing' => true, 'on_console' => false];
        // Same marketing route list as the chrome below — duplicated here so head snippets
        // can decide before the body opens.
        $onMarketing = request()->routeIs('home', 'about', 'features', 'use-cases', 'blog', 'blog.show', 'contact');
        $inConsole = auth()->check() && ! $onMarketing;
        $injectInsertCode = $inConsole
            ? ($insertCode['on_console'] ?? false)
            : ($insertCode['on_marketing'] ?? true);
    @endphp
    @if($injectInsertCode && ! empty($insertCode['head']))
        {!! $insertCode['head'] !!}
    @endif
</head>
<body class="min-h-screen antialiased">
@php
    $branding = $branding ?? ['name' => config('app.name', 'Uplary'), 'logo_url' => null];
    // Public marketing pages keep the landing chrome for everyone — including signed-in
    // admins — so the console sidebar never appears on /, /about, /blog, etc.
@endphp

@if(auth()->check() && ! $onMarketing)
    @php
        $user = auth()->user();
        $sections = [
            ['href' => route('dashboard'), 'label' => 'Dashboard', 'match' => 'dashboard', 'icon' => 'M3 3h7v7H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 14h7v7H3z'],
            ['href' => route('servers.index'), 'label' => 'Servers', 'match' => 'servers*', 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01'],
            ['href' => route('sites.index'), 'label' => 'Sites', 'match' => 'sites*', 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z'],
            ['href' => route('cloud-accounts'), 'label' => 'Providers', 'match' => 'cloud-accounts*', 'icon' => 'M17.5 19a4.5 4.5 0 0 0 .5-8.97A6 6 0 0 0 6.2 9.4 4.5 4.5 0 0 0 6.5 19h11Z'],
        ];
        // Mirrors the routes rather than deciding anything: DNS is switched off in admin
        // settings, and the entry follows so the nav never offers a 404.
        if ($dnsEnabled ?? true) {
            $sections[] = ['href' => route('dns.index'), 'label' => 'DNS', 'match' => 'dns*', 'icon' => 'M4 6h16M4 12h16M4 18h10M18 15l3 3-3 3'];
        }
        $sections[] = ['href' => route('ssh-keys'), 'label' => 'SSH keys', 'match' => 'ssh-keys*', 'icon' => 'M15 7a5 5 0 1 1-4.9 6H7v3H4v-3H2v-3h8.1A5 5 0 0 1 15 7Zm2 4h.01'];
        if ($user->isSuperAdmin()) {
            $sections[] = ['href' => route('admin.dashboard'), 'label' => 'Admin', 'match' => 'admin*', 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 0v4'];
        }
        $accountLinks = ['/account' => 'Account settings', '/teams' => 'Teams', '/billing' => 'Billing'];
        $inAccountArea = request()->is('account*') || request()->is('teams*') || request()->is('billing*');
        $current = collect($sections)->first(fn ($section) => request()->is($section['match']));
        $alerts = $shellAlerts ?? [];

        // Account, Teams and Billing share a sidebar entry but are separate pages, so the
        // crumb names the page rather than lumping all three under "Account".
        $crumb = $current['label'] ?? null;
        foreach ($accountLinks as $href => $label) {
            if (! $crumb && request()->is(ltrim($href, '/').'*')) {
                $crumb = $label;
            }
        }
        $crumb ??= $title ?? 'Overview';
    @endphp

    <div class="app-shell" x-data="{ nav: false }" @keydown.escape.window="nav = false">
        <!-- Mobile scrim -->
        <div x-cloak x-show="nav" x-transition.opacity @click="nav = false" class="fixed inset-0 z-50 bg-slate-900/50 lg:hidden"></div>

        <aside class="app-sidebar" :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="mb-8 flex items-center justify-between px-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    @if($branding['logo_url'])
                        <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] }}" class="size-10 rounded-xl object-contain">
                    @else
                        <span class="grid size-10 place-items-center rounded-xl bg-sky-500 font-display text-lg font-extrabold text-white">{{ Str::upper(Str::substr($branding['name'], 0, 1)) }}</span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate font-display text-lg font-extrabold text-white">{{ $branding['name'] }}</span>
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Cloud management</span>
                    </span>
                </a>
                <button type="button" @click="nav = false" class="icon-button !text-slate-400 hover:!bg-white/10 hover:!text-white lg:hidden" aria-label="Close navigation">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto">
                @foreach($sections as $section)
                    @php $active = request()->is($section['match']); @endphp
                    <a href="{{ $section['href'] }}" @class(['side-link', 'side-link-active' => $active]) @if($active) aria-current="page" @endif>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0"><path d="{{ $section['icon'] }}"/></svg>
                        {{ $section['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto space-y-4 px-4 pt-4">
                {{-- No Provision server button here: the Servers and Dashboard pages both
                     carry that action where the servers themselves are, and a second copy
                     pinned to the nav competed with them from every unrelated page. --}}
                <div class="space-y-1 border-t border-white/10 pt-4">
                    <a href="{{ route('docs') }}" @class(['side-mini-link !px-2', 'bg-white/10 text-white' => request()->is('docs*')])>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M9 15h6M9 11h3"/></svg>
                        Documentation
                    </a>
                    @if($publicSiteEnabled ?? true)
                        <a href="{{ route('contact') }}" class="side-mini-link !px-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg>
                            Contact
                        </a>
                    @endif
                </div>

                <div class="relative border-t border-white/10 pt-4" x-data="{ open: false }">
                    <button type="button" @click="open = ! open" @click.outside="open = false"
                            @class(['flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-white/10', 'bg-white/10' => $inAccountArea])
                            :aria-expanded="open" aria-haspopup="true">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-cyan-200 text-xs font-bold uppercase text-[#00303d]">{{ Str::upper(Str::substr($user->name, 0, 2)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-semibold text-white">{{ $user->name }}</span>
                            <span class="block truncate text-[10px] text-slate-400">{{ $user->isSuperAdmin() ? 'Administrator' : 'Account' }}</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.bottom.left class="menu-panel !bottom-full !left-0 !right-auto !mb-2 !mt-0 w-[13.5rem]">
                        <div class="border-b border-slate-100 px-4 py-3 dark:border-white/5">
                            <p class="truncate text-sm font-medium heading">{{ $user->name }}</p>
                            <p class="truncate text-xs muted">{{ $user->email }}</p>
                        </div>
                        @foreach($accountLinks as $href => $label)
                            <a href="{{ $href }}" @class(['menu-item', 'menu-item-active' => request()->is(ltrim($href, '/').'*')])>{{ $label }}</a>
                        @endforeach
                        <form method="POST" action="/logout" class="border-t border-slate-100 dark:border-white/5">@csrf
                            <button class="menu-item !text-rose-600 hover:!bg-rose-50 dark:!text-rose-300 dark:hover:!bg-rose-400/10">Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <div class="app-content">
            <main class="relative flex-1">
                <!-- Ambient brand wash: keeps the expansive feel without competing with data. -->
                <div class="pointer-events-none absolute right-0 top-0 -z-10 size-[500px] rounded-full bg-[linear-gradient(135deg,#00d2fd_0%,#0058bc_100%)] opacity-10 blur-[120px]"></div>

                <!-- Console context row. Part of the page body rather than a fixed bar, so the -->
                <!-- breadcrumb reads as the top of the content and nothing is pinned over it. -->
                <div class="page-context">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" @click="nav = true" class="icon-button lg:hidden" aria-label="Open navigation">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                        </button>
                        <nav class="hidden min-w-0 items-center gap-2 sm:flex" aria-label="Breadcrumb">
                            <a href="{{ route('dashboard') }}" class="breadcrumb hover:text-slate-900 dark:hover:text-white">Home</a>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-3.5 muted"><path d="m9 18 6-6-6-6"/></svg>
                            <span class="breadcrumb breadcrumb-current truncate">{{ $crumb }}</span>
                        </nav>
                    </div>
    
                    <div class="flex items-center gap-1.5 sm:gap-3">
                        <!-- Quick jump: filters every destination in the console without a round trip. -->
                        <div class="relative hidden md:block" x-data="{
                                q: '',
                                items: @js(collect($sections)->map(fn ($s) => ['label' => $s['label'], 'href' => $s['href']])
                                    ->merge(array_filter([
                                        ['label' => 'Provision server', 'href' => route('servers.create')],
                                        ['label' => 'Add existing server', 'href' => route('servers.custom')],
                                        ['label' => 'Add site', 'href' => route('sites.create')],
                                        ['label' => 'Account settings', 'href' => '/account'],
                                        ['label' => 'Teams', 'href' => '/teams'],
                                        ['label' => 'Billing', 'href' => '/billing'],
                                        ['label' => 'Documentation', 'href' => route('docs')],
                                        ($publicSiteEnabled ?? true) ? ['label' => 'Contact support', 'href' => route('contact')] : null,
                                    ]))->values()),
                                get results() { return this.q === '' ? [] : this.items.filter(i => i.label.toLowerCase().includes(this.q.toLowerCase())) },
                            }" @click.outside="q = ''">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 muted"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input type="search" x-model="q" class="search-input w-56 lg:w-72" placeholder="Search sites, servers…" aria-label="Search the console"
                                   @keydown.enter="results.length && (window.location = results[0].href)">
                            <div x-cloak x-show="results.length" class="menu-panel !w-full">
                                <template x-for="item in results" :key="item.href">
                                    <a :href="item.href" class="menu-item" x-text="item.label"></a>
                                </template>
                            </div>
                        </div>
    
                        <!-- Notifications: live alert incidents and failed deployments, not decoration. -->
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = ! open" @click.outside="open = false" class="icon-button relative" aria-label="Notifications">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                                @if(count($alerts))<span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-rose-500 ring-2 ring-[#f7f9fb] dark:ring-slate-950"></span>@endif
                            </button>
                            <div x-cloak x-show="open" x-transition.origin.top.right class="menu-panel !w-80">
                                <p class="border-b border-slate-100 px-4 py-3 text-sm font-semibold heading dark:border-white/5">Notifications</p>
                                @forelse($alerts as $alert)
                                    <a href="{{ $alert['href'] }}" class="menu-item flex gap-3">
                                        <span class="badge-dot mt-1.5 shrink-0 {{ $alert['tone'] === 'danger' ? 'bg-rose-500' : 'bg-amber-500' }}"></span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium heading">{{ $alert['title'] }}</span>
                                            <span class="block truncate text-xs muted">{{ $alert['description'] }}</span>
                                        </span>
                                    </a>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm muted">Nothing needs your attention.</p>
                                @endforelse
                            </div>
                        </div>
    
                        <button type="button" @click="dark = !dark" class="icon-button" title="Toggle dark mode" aria-label="Toggle dark mode">
                            <svg x-cloak x-show="!dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                            <svg x-cloak x-show="dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                        </button>
                    </div>
                </div>

                @if(session('status'))
                    <div class="mx-auto w-full max-w-[1440px] px-5 pt-6 lg:px-10">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('status') }}</div>
                    </div>
                @endif
                {{ $slot ?? '' }}@yield('content')
            </main>
        </div>
    </div>
@else
    @php
        $marketingNav = [
            ['label' => 'Home', 'route' => 'home'],
            ['label' => 'About', 'route' => 'about'],
            ['label' => 'Features', 'route' => 'features'],
            ['label' => 'Pricing', 'href' => route('home').'#pricing'],
            ['label' => 'Use cases', 'route' => 'use-cases'],
            ['label' => 'Blog', 'route' => 'blog'],
            ['label' => 'Contact', 'route' => 'contact'],
        ];
    @endphp
    <div class="min-h-screen">
        <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-white/10 dark:bg-slate-950/90">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5">
                <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3 font-display font-bold heading">
                    @if($branding['logo_url'])
                        <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] }}" class="size-9 rounded-xl object-contain">
                    @else
                        <span class="grid size-9 place-items-center rounded-xl bg-sky-500 font-extrabold text-white">{{ Str::upper(Str::substr($branding['name'], 0, 1)) }}</span>
                    @endif
                    {{ $branding['name'] }}
                </a>

                <nav class="hidden items-center gap-0.5 md:flex" aria-label="Primary">
                    @foreach($marketingNav as $item)
                        @php
                            $itemRoute = $item['route'] ?? null;
                            $current = $itemRoute && (request()->routeIs($itemRoute) || ($itemRoute === 'blog' && request()->routeIs('blog.show')));
                        @endphp
                        <a href="{{ $item['href'] ?? route($itemRoute) }}"
                           @class([
                               'rounded-lg px-2.5 py-1.5 text-sm transition lg:px-3',
                               'bg-sky-50 font-semibold text-sky-700 dark:bg-sky-400/10 dark:text-sky-200' => $current,
                               'font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! $current,
                           ])
                           @if($current) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-1.5 sm:gap-2">
                    <button type="button" @click="dark = !dark" class="icon-button" title="Toggle dark mode" aria-label="Toggle dark mode">
                        <svg x-cloak x-show="!dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                        <svg x-cloak x-show="dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                    </button>
                    <button type="button" @click="marketingMenu = ! marketingMenu" class="icon-button md:hidden" aria-label="Open menu" :aria-expanded="marketingMenu">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                    </button>
                    @auth
                        <a href="{{ route('dashboard') }}" class="button-primary !px-4 !py-2 text-xs sm:text-sm">Open console</a>
                    @else
                        <a href="{{ route('login') }}" class="nav-link hidden sm:inline-flex">Sign in</a>
                        <a href="{{ route('register') }}" class="button-primary !px-4 !py-2 text-xs sm:text-sm">Get started</a>
                    @endauth
                </div>
            </div>
            <div x-cloak x-show="marketingMenu" x-transition class="border-t border-slate-200 px-5 py-3 md:hidden dark:border-white/10">
                <div class="flex flex-col gap-1">
                    @foreach($marketingNav as $item)
                        @php
                            $itemRoute = $item['route'] ?? null;
                            $current = $itemRoute && (request()->routeIs($itemRoute) || ($itemRoute === 'blog' && request()->routeIs('blog.show')));
                        @endphp
                        <a href="{{ $item['href'] ?? route($itemRoute) }}" @click="marketingMenu = false"
                           @class(['rounded-lg px-3 py-2.5 text-sm', 'bg-sky-50 font-semibold text-sky-700 dark:bg-sky-400/10 dark:text-sky-200' => $current, 'text-slate-600 dark:text-slate-300' => ! $current])>{{ $item['label'] }}</a>
                    @endforeach
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-sky-700 dark:text-sky-200">Open console</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-2.5 text-sm text-slate-600 sm:hidden dark:text-slate-300">Sign in</a>
                    @endauth
                </div>
            </div>
        </header>
        <main class="relative">
            @if(session('status') && ! $onMarketing)
                <div class="mx-auto mt-5 max-w-2xl px-5"><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('status') }}</div></div>
            @endif
            {{ $slot ?? '' }}@yield('content')
        </main>
    </div>
@endif
@include('partials.ai-guide')
@livewireScripts
@if($injectInsertCode && ! empty($insertCode['body']))
    {!! $insertCode['body'] !!}
@endif
</body>
</html>
