@extends('layouts.app')
@section('content')
@php
    $sections = [
        ['route' => 'admin.dashboard', 'label' => 'Overview', 'icon' => 'M3 12h4l3 8 4-16 3 8h4'],
        ['route' => 'admin.users', 'label' => 'Users', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm13 14v-2a4 4 0 0 0-3-3.87'],
        ['route' => 'admin.plans', 'label' => 'Plans', 'icon' => 'M20 12V8H6a2 2 0 0 1 0-4h12v4M4 6v12a2 2 0 0 0 2 2h14v-4M18 12a2 2 0 0 0 0 4h4v-4Z'],
        ['route' => 'admin.features', 'label' => 'Feature flags', 'icon' => 'M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7'],
        ['route' => 'admin.posts', 'label' => 'Blog', 'icon' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z'],
        ['route' => 'admin.billing', 'label' => 'Billing review', 'icon' => 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        ['route' => 'admin.payments', 'label' => 'Payments', 'icon' => 'M2 9h20M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2Z'],
        ['route' => 'admin.settings', 'label' => 'Settings', 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.1l2-1.6-2-3.4-2.4 1a7.5 7.5 0 0 0-1.9-1.1l-.4-2.5h-4l-.4 2.5a7.5 7.5 0 0 0-1.9 1.1l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.2l-2 1.6 2 3.4 2.4-1c.6.5 1.2.8 1.9 1.1l.4 2.5h4l.4-2.5c.7-.3 1.3-.6 1.9-1.1l2.4 1 2-3.4-2-1.6c.1-.4.1-.7.1-1.1Z'],
        ['route' => 'admin.audit', 'label' => 'Audit', 'icon' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M9 15h6M9 11h3'],
    ];
@endphp
<div class="mx-auto max-w-7xl gap-8 px-5 py-10 lg:grid lg:grid-cols-[220px_1fr]">
    <aside class="mb-6 lg:mb-0">
        <div class="px-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300">Super administrator</p>
            <p class="mt-1 text-base font-semibold heading">SaaS control center</p>
        </div>
        <nav class="mt-4 flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
            @foreach($sections as $section)
                @php $current = request()->routeIs($section['route']); @endphp
                <a href="{{ route($section['route']) }}"
                   @class([
                       'flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2 text-sm transition',
                       'bg-amber-50 font-medium text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' => $current,
                       'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/5' => ! $current,
                   ])
                   @if($current) aria-current="page" @endif>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><path d="{{ $section['icon'] }}"/></svg>
                    {{ $section['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="min-w-0">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold heading">@yield('admin-title')</h1>
                <p class="mt-1 text-sm muted">@yield('admin-description')</p>
            </div>
            @yield('admin-actions')
        </div>
        @if($errors->any())
            <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
                <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="mt-6">@yield('admin')</div>
    </div>
</div>
@endsection
