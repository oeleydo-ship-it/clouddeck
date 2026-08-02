@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-5xl px-5 py-10">
    <div class="flex items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-cyan-600 dark:text-cyan-300">Applications</p><h1 class="mt-1 text-3xl font-semibold heading">Sites</h1></div>
        <a href="{{ route('sites.create') }}" class="button-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M12 5v14M5 12h14"/></svg>
            Add site
        </a>
    </div>
    @php $statusTints = ['active' => 'emerald', 'deploying' => 'amber', 'configuring' => 'amber', 'failed' => 'rose', 'pending' => 'amber']; @endphp
    <div class="mt-8 space-y-3">
        @forelse($sites as $site)
            @php $tint = $statusTints[$site->status] ?? 'slate'; @endphp
            <a href="{{ route('sites.show',$site) }}" class="panel flex items-center gap-4 transition hover:border-cyan-300 dark:hover:border-cyan-400/40">
                <div class="min-w-0 grow">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="truncate font-semibold heading">{{ $site->domain }}</h2>
                        <span class="badge {{ $tint === 'slate' ? 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300' : ($tint === 'emerald' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' : ($tint === 'rose' ? 'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300')) }} capitalize"><span class="badge-dot bg-{{ $tint === 'slate' ? 'slate-400' : $tint.'-500' }}"></span>{{ $site->status }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm muted">
                        <span class="inline-flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M4 17V7a2 2 0 0 1 2-2h5l2 2h5a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/></svg>PHP {{ $site->php_version }}</span>
                        <span class="inline-flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><circle cx="6" cy="6" r="2"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="9" r="2"/><path d="M6 8v8M6 8a9 9 0 0 0 12 8"/></svg>{{ $site->branch }}</span>
                    </div>
                    <p class="mt-1 flex items-center gap-1.5 truncate text-xs muted"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg><span class="truncate">{{ $site->repository_url }}</span></p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0 muted"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        @empty
            <div class="panel text-center muted">No sites yet. Add a Laravel project to a ready server.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $sites->links() }}</div>
</div>
@endsection
