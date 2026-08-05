@extends('layouts.app')
@section('content')
<div class="app-main">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="page-eyebrow">Applications</p>
            <h1 class="page-title">Sites</h1>
            <p class="page-subtitle">Every application deployed across your fleet, with its branch, runtime, and health.</p>
        </div>
        <a href="{{ route('sites.create') }}" class="button-primary h-12 shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
            Add site
        </a>
    </header>

    @php
        $statusTints = ['active' => 'emerald', 'deploying' => 'amber', 'configuring' => 'amber', 'failed' => 'rose', 'pending' => 'amber'];
        $badgeClasses = ['emerald' => 'badge-success', 'amber' => 'badge-warning', 'rose' => 'badge-danger', 'slate' => 'badge-neutral'];
    @endphp

    <div class="mt-8 space-y-3">
        @forelse($sites as $site)
            @php $tint = $statusTints[$site->status] ?? 'slate'; @endphp
            <div class="panel flex items-center gap-4">
                <a href="{{ route('sites.show',$site) }}" class="min-w-0 grow transition hover:opacity-90">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="truncate font-display text-lg font-semibold heading">{{ $site->domain }}</h2>
                        <span class="badge {{ $badgeClasses[$tint] }} capitalize"><span class="badge-dot bg-{{ $tint === 'slate' ? 'slate-400' : $tint.'-500' }}"></span>{{ $site->status }}</span>
                        @if($site->isStaging())
                            <span class="badge bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Staging</span>
                        @else
                            <span class="badge bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Production</span>
                        @endif
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm muted">
                        <span class="inline-flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M4 17V7a2 2 0 0 1 2-2h5l2 2h5a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/></svg>PHP {{ $site->php_version }}</span>
                        <span class="inline-flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><circle cx="6" cy="6" r="2"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="9" r="2"/><path d="M6 8v8M6 8a9 9 0 0 0 12 8"/></svg>{{ $site->branch }}</span>
                        @if($site->server)
                            <span class="inline-flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/></svg>{{ $site->server->name }}</span>
                        @endif
                    </div>
                    @if($site->repository_url)
                        <p class="mt-1 flex items-center gap-1.5 truncate text-xs muted"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg><span class="truncate">{{ $site->repository_url }}</span></p>
                    @endif
                </a>
                <div class="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center">
                    @if(($stagingSitesEnabled ?? false) && $site->isProduction() && $site->status === 'active' && ! $site->stagingSite)
                        <a href="{{ route('sites.show', $site) }}#staging-setup" class="button-secondary !px-3 !py-1.5 text-xs whitespace-nowrap">Create staging</a>
                    @elseif(($stagingSitesEnabled ?? false) && $site->isProduction() && $site->stagingSite)
                        <a href="{{ route('sites.show', $site->stagingSite) }}" class="button-secondary !px-3 !py-1.5 text-xs whitespace-nowrap">Open staging</a>
                    @endif
                    <a href="{{ route('sites.show',$site) }}" class="grid size-9 place-items-center rounded-full border border-slate-200 muted dark:border-white/10"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m9 18 6-6-6-6"/></svg></a>
                </div>
            </div>
        @empty
        @endforelse

        {{-- The empty state and the "one more" affordance are the same control: both cases
             want the same next step, so there is no reason to draw two different boxes. --}}
        <a href="{{ route('sites.create') }}" class="dashed-cta">
            <span class="grid size-11 place-items-center rounded-full bg-white shadow-sm dark:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5 text-[#0058bc] dark:text-cyan-300"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            <span class="font-display text-lg font-semibold heading">{{ $sites->isEmpty() ? 'Deploy your first application' : 'Deploy another application' }}</span>
            <span class="text-sm muted">Connect a repository, or install WordPress on a ready server.</span>
        </a>
    </div>

    <div class="mt-6">{{ $sites->links() }}</div>

    @php
        $summaryCards = [
            ['label' => 'Total sites', 'value' => $summary['total'], 'tint' => 'bg-blue-50 text-[#0058bc] dark:bg-blue-400/10 dark:text-blue-300', 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z'],
            ['label' => 'Active', 'value' => $summary['active'], 'tint' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300', 'icon' => 'm9 12 2 2 4-4M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['label' => 'Deploys today', 'value' => $summary['deployments'], 'tint' => 'bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300', 'icon' => 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z'],
            ['label' => 'Failed deploys', 'value' => $summary['failed'], 'danger' => $summary['failed'] > 0, 'tint' => 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300', 'icon' => 'M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z'],
        ];
    @endphp
    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($summaryCards as $card)
            <div class="stat-card">
                <div class="flex items-center gap-3">
                    <span class="stat-icon {{ $card['tint'] }}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="{{ $card['icon'] }}"/></svg></span>
                    <div>
                        <p class="stat-label">{{ $card['label'] }}</p>
                        <p class="stat-value !text-2xl !leading-8 {{ !empty($card['danger']) ? '!text-rose-600 dark:!text-rose-400' : '' }}">{{ $card['value'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </section>
</div>
@endsection
