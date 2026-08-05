@extends('layouts.app')
@section('content')
@php
    $nav = [
        'home' => ['label' => 'Home', 'route' => 'home'],
        'about' => ['label' => 'About', 'route' => 'about'],
        'features' => ['label' => 'Features', 'route' => 'features'],
        'pricing' => ['label' => 'Pricing', 'href' => route('home').'#pricing'],
        'use-cases' => ['label' => 'Use cases', 'route' => 'use-cases'],
        'blog' => ['label' => 'Blog', 'route' => 'blog'],
        'contact' => ['label' => 'Contact', 'route' => 'contact'],
    ];
    $platform = $branding['name'] ?? app(\App\Services\SystemSettings::class)->branding()['name'];
    $isHome = request()->routeIs('home');
@endphp

{{-- Signed-in users still need marketing links (guest header is hidden behind the console shell). --}}
@auth
    <div class="relative z-10 border-b border-slate-200/80 bg-white/80 backdrop-blur dark:border-white/10 dark:bg-slate-950/70">
        <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-5 py-3 text-sm" aria-label="Marketing">
            @foreach($nav as $item)
                @php
                    $itemRoute = $item['route'] ?? null;
                    $current = $itemRoute && (request()->routeIs($itemRoute) || ($itemRoute === 'blog' && request()->routeIs('blog.show')));
                @endphp
                <a href="{{ $item['href'] ?? route($itemRoute) }}"
                   @class([
                       'shrink-0 rounded-lg px-3 py-1.5 transition',
                       'bg-sky-50 font-semibold text-sky-700 dark:bg-sky-400/10 dark:text-sky-200' => $current,
                       'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/5' => ! $current,
                   ])
                   @if($current) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </div>
@endauth

@if(session('status'))
    <div class="mx-auto mt-6 max-w-3xl px-5"><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('status') }}</div></div>
@endif

@yield('marketing')

<footer class="border-t border-slate-200 dark:border-white/10 {{ $isHome ? 'bg-slate-950 text-slate-300' : 'bg-white dark:bg-slate-950' }}">
    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-12 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="font-display text-lg font-extrabold {{ $isHome ? 'text-white' : 'heading' }}">{{ $platform }}</p>
            <p class="mt-2 max-w-sm text-sm {{ $isHome ? 'text-slate-400' : 'muted' }}">Set up servers, deploy Laravel and WordPress, and manage day-to-day tasks — on infrastructure you own.</p>
            <p class="mt-6 text-sm {{ $isHome ? 'text-slate-500' : 'muted' }}">&copy; {{ now()->year }} {{ $platform }}.</p>
        </div>
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            @foreach($nav as $item)
                <a class="{{ $isHome ? 'text-slate-400 hover:text-white' : 'muted hover:text-slate-900 dark:hover:text-white' }}" href="{{ $item['href'] ?? route($item['route']) }}">{{ $item['label'] }}</a>
            @endforeach
            <a class="{{ $isHome ? 'text-slate-400 hover:text-white' : 'muted hover:text-slate-900 dark:hover:text-white' }}" href="{{ route('register') }}">Get started</a>
        </div>
    </div>
</footer>
@endsection
