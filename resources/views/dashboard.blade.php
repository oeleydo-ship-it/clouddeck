@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-5 py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-cyan-600 dark:text-cyan-300">Control plane</p><h1 class="mt-1 text-3xl font-semibold heading">Good {{ now()->hour < 12 ? 'morning' : 'afternoon' }}, {{ auth()->user()->name }}</h1></div>
        <a href="{{ route('servers.create') }}" class="button-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
            Provision server
        </a>
    </div>

    @php
        $cards = [
            ['label' => 'Total servers', 'value' => $stats['servers'], 'tint' => 'cyan', 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3Z M8 7h.01M8 18h.01'],
            ['label' => 'Active servers', 'value' => $stats['active'], 'tint' => 'emerald', 'dot' => 'emerald', 'icon' => 'm9 12 2 2 4-4 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['label' => 'Deployments today', 'value' => $stats['deployments'], 'tint' => 'blue', 'icon' => 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z'],
            ['label' => 'Failed deployments', 'value' => $stats['failed'], 'tint' => 'rose', 'dot' => $stats['failed'] > 0 ? 'rose' : null, 'icon' => 'M12 9v4M12 17h.01 M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z'],
            ['label' => 'Offline agents', 'value' => $stats['offline'], 'tint' => 'rose', 'dot' => $stats['offline'] > 0 ? 'rose' : null, 'icon' => 'm4.9 4.9 14.2 14.2 M9 9a5 5 0 0 1 7 7M5 13a9 9 0 0 1 2.5-6.3M12 20h.01'],
        ];
        $tints = [
            'cyan' => 'bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300',
            'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300',
            'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-400/10 dark:text-blue-300',
            'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300',
        ];
    @endphp
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($cards as $stat)
            <div class="stat-card">
                <div class="flex items-start justify-between">
                    <p class="text-sm muted">{{ $stat['label'] }}</p>
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $tints[$stat['tint']] }}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="{{ $stat['icon'] }}"/></svg></span>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    @if(!empty($stat['dot']))<span class="badge-dot bg-{{ $stat['dot'] }}-500 animate-pulse"></span>@endif
                    <p class="text-3xl font-semibold heading">{{ $stat['value'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-2">
        <a href="{{ route('servers.index') }}" class="panel group flex items-center gap-4 transition hover:border-cyan-300 dark:hover:border-cyan-400/40">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01"/></svg></span>
            <div class="min-w-0 grow"><p class="font-semibold heading">Servers</p><p class="mt-1 text-sm muted">{{ $stats['servers'] }} total · {{ $stats['active'] }} active · manage and provision</p></div>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0 muted transition group-hover:translate-x-0.5"><path d="m9 18 6-6-6-6"/></svg>
        </a>
        <a href="{{ route('sites.index') }}" class="panel group flex items-center gap-4 transition hover:border-cyan-300 dark:hover:border-cyan-400/40">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M4 17V7a2 2 0 0 1 2-2h5l2 2h5a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/></svg></span>
            <div class="min-w-0 grow"><p class="font-semibold heading">Sites</p><p class="mt-1 text-sm muted">{{ $stats['deployments'] }} deployments today · {{ $stats['failed'] }} failed</p></div>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0 muted transition group-hover:translate-x-0.5"><path d="m9 18 6-6-6-6"/></svg>
        </a>
    </div>
</div>
@endsection
