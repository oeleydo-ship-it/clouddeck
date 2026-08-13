<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $seo = $page['props']['seo'] ?? ['description' => null, 'keywords' => null, 'og_image' => null, 'robots' => 'index,follow'];
        $analytics = $page['props']['analytics'] ?? ['ga_measurement_id' => null, 'gsc_verification' => null];
        $branding = $page['props']['branding'] ?? ['name' => config('app.name', 'Uplary')];
        $pageTitle = $page['props']['title'] ?? ($branding['name'] ?? config('app.name', 'Uplary'));
        $pageDescription = $page['props']['metaDescription'] ?? ($seo['description'] ?? null);
        $pageOgImage = $page['props']['ogImage'] ?? ($seo['og_image'] ?? null);
        $insertCode = $page['props']['insertCode'] ?? ['head' => null, 'body' => null, 'on_marketing' => true, 'on_console' => false];
        $onMarketing = $page['props']['onMarketing'] ?? false;
        $inConsole = auth()->check() && ! $onMarketing;
        $injectInsertCode = $inConsole ? ($insertCode['on_console'] ?? false) : ($insertCode['on_marketing'] ?? true);
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
    @if(! empty($pageOgImage))
        <meta property="og:image" content="{{ $pageOgImage }}">
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
            if (localStorage.theme !== 'light') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        } catch (e) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <link rel="icon" href="{{ $branding['favicon_url'] ?? $branding['logo_url'] ?? asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
    @if($injectInsertCode && ! empty($insertCode['head']))
        {!! $insertCode['head'] !!}
    @endif
</head>
<body class="min-h-screen antialiased{{ $inConsole ? ' console-body' : '' }}"@unless(($branding['logo_image_only'] ?? false) && ! empty($branding['logo_url'])) data-brand-name="{{ $branding['name'] ?? config('app.name', 'Uplary') }}"@endunless>
    @if(! empty($branding['logo_url']))
        <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] ?? config('app.name', 'Uplary') }}" class="sr-only">
    @endif
    @if($onMarketing)
        <nav aria-label="Primary" class="sr-only">
            @auth
                <a href="{{ route('dashboard') }}">Open console</a>
            @else
                <a href="{{ route('login') }}">Sign in</a>
                <a href="{{ route('register') }}">Get started</a>
            @endauth
        </nav>
    @endif
    @if($inConsole)
        <aside class="app-sidebar app-shell sr-only" aria-hidden="true">
            <a href="{{ $page['props']['chrome']['home_href'] ?? url('/') }}" target="_blank" rel="noopener noreferrer">View website</a>
            @foreach(($page['props']['consoleNav'] ?? []) as $item)
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ $page['props']['chrome']['billing_href'] ?? '/billing' }}">{{ $page['props']['chrome']['billing'] ?? 'Billing' }}</a>
        </aside>
        @if(($page['props']['impersonation']['active'] ?? false) && ! empty($page['props']['impersonation']['banner']))
            <div class="sr-only">{{ $page['props']['impersonation']['banner'] }} {{ $page['props']['impersonation']['exit_label'] ?? 'Exit impersonation' }}</div>
        @endif
    @endif
    @php
        $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();
    @endphp
    @if($__inertiaSsrResponse)
        {!! $__inertiaSsrResponse->body !!}
    @else
        <script data-page="app" type="application/json">{!! json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) !!}</script>
        <div id="app"></div>
    @endif
    @if($injectInsertCode && ! empty($insertCode['body']))
        {!! $insertCode['body'] !!}
    @endif
</body>
</html>
