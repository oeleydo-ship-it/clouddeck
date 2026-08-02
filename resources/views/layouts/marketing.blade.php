@extends('layouts.app')
@section('content')
@php
    $nav = [
        'home' => ['label' => 'Home', 'route' => 'home'],
        'about' => ['label' => 'About us', 'route' => 'about'],
        'features' => ['label' => 'Features', 'route' => 'features'],
        'use-cases' => ['label' => 'Use cases', 'route' => 'use-cases'],
        'blog' => ['label' => 'Blog', 'route' => 'blog'],
        'contact' => ['label' => 'Contact us', 'route' => 'contact'],
    ];
    $platform = app(\App\Services\SystemSettings::class)->branding()['name'];
@endphp

<div class="border-b border-slate-200 bg-white/70 backdrop-blur dark:border-white/10 dark:bg-slate-950/60">
    <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-5 py-3 text-sm">
        @foreach($nav as $item)
            @php $current = request()->routeIs($item['route']) || ($item['route'] === 'blog' && request()->routeIs('blog.show')); @endphp
            <a href="{{ route($item['route']) }}"
               @class([
                   'shrink-0 rounded-lg px-3 py-1.5 transition',
                   'bg-slate-100 font-medium text-slate-900 dark:bg-white/10 dark:text-white' => $current,
                   'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/5' => ! $current,
               ])
               @if($current) aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
    </nav>
</div>

@if(session('status'))
    <div class="mx-auto mt-6 max-w-3xl px-5"><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('status') }}</div></div>
@endif

@yield('marketing')

<footer class="mt-24 border-t border-slate-200 dark:border-white/10">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-sm muted">
        <p>&copy; {{ now()->year }} {{ $platform }}.</p>
        <div class="flex flex-wrap gap-4">
            @foreach($nav as $item)<a class="hover:text-slate-900 dark:hover:text-white" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>@endforeach
        </div>
    </div>
</footer>
@endsection
