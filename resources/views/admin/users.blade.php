@extends('layouts.admin')
@section('admin-title', 'Users')
@section('admin-description', 'Customer accounts, roles, subscriptions, and suspensions.')
@section('admin')
    <div class="space-y-5">
        <form method="GET" class="flex flex-wrap gap-3">
            <div class="relative grow">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input class="field mt-0 !pl-10" name="search" value="{{ request('search') }}" placeholder="Search by name or email">
            </div>
            <button class="button-secondary">Search</button>
            @if(request('search'))<a href="{{ route('admin.users') }}" class="button-secondary">Clear</a>@endif
        </form>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 dark:border-white/10 dark:bg-white/[.03] dark:shadow-none">
            @forelse($users as $user)
                @php $suspended = (bool) $user->suspended_at; @endphp
                <div class="border-b border-slate-100 last:border-0 dark:border-white/5">
                    <div class="flex flex-wrap items-center gap-4 px-6 py-4">
                        <div class="grid size-10 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold uppercase text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ Str::substr($user->name, 0, 2) }}</div>
                        <div class="min-w-0 grow">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-medium heading">{{ $user->name }}</p>
                                @if($user->isSuperAdmin())<span class="badge bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Super admin</span>@endif
                                @if($suspended)<span class="badge bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300">Suspended</span>@endif
                            </div>
                            <p class="truncate text-sm muted">{{ $user->email }}</p>
                        </div>
                        <div class="hidden text-sm sm:block">
                            <p class="heading">{{ $user->currentSubscription?->plan?->name ?? 'No subscription' }}</p>
                            <p class="text-xs muted">Joined {{ $user->created_at->toFormattedDateString() }}</p>
                        </div>
                        <a href="{{ route('admin.users.show', $user) }}" class="button-secondary !px-3 !py-1.5 text-xs">Manage</a>
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <p class="font-medium heading">{{ request('search') ? 'No accounts match that search' : 'No accounts yet' }}</p>
                    @if(request('search'))<a href="{{ route('admin.users') }}" class="mt-3 inline-block text-sm text-cyan-600 dark:text-cyan-300">Show every account</a>@endif
                </div>
            @endforelse
        </section>

        <div>{{ $users->links() }}</div>
    </div>
@endsection
