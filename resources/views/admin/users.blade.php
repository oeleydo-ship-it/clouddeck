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
                <div x-data="{ open: false }" class="border-b border-slate-100 last:border-0 dark:border-white/5">
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
                        <button type="button" @click="open = ! open" class="button-secondary !px-3 !py-1.5 text-xs" x-text="open ? 'Close' : 'Manage'">Manage</button>
                    </div>

                    <div x-cloak x-show="open" class="grid gap-4 border-t border-slate-100 bg-slate-50/60 px-6 py-5 lg:grid-cols-3 dark:border-white/5 dark:bg-white/[.02]">
                        <form method="POST" action="{{ route('admin.users.subscription', $user) }}" class="space-y-2">@csrf
                            <p class="text-xs font-medium uppercase tracking-wide muted">Subscription</p>
                            <select class="field mt-0" name="plan_id" aria-label="Plan for {{ $user->email }}">
                                @forelse($plans->where('active', true) as $plan)
                                    <option value="{{ $plan->id }}" @selected($user->currentSubscription?->plan_id === $plan->id)>{{ $plan->name }}</option>
                                @empty
                                    <option value="" disabled>No active plans</option>
                                @endforelse
                            </select>
                            <div class="flex gap-2">
                                <input class="field mt-0" type="number" name="period_days" value="30" min="1" aria-label="Period in days">
                                <button class="button-secondary shrink-0" @disabled($plans->where('active', true)->isEmpty())>Assign</button>
                            </div>
                            <p class="text-xs muted">Days of access granted from today.</p>
                        </form>

                        <form method="POST" action="{{ route('admin.users.role', $user) }}" class="space-y-2">@csrf @method('PATCH')
                            <p class="text-xs font-medium uppercase tracking-wide muted">Role</p>
                            <select class="field mt-0" name="role" aria-label="Role for {{ $user->email }}">
                                <option value="customer" @selected($user->role === 'customer')>Customer</option>
                                <option value="super_admin" @selected($user->role === 'super_admin')>Super admin</option>
                            </select>
                            <button class="button-secondary">Update role</button>
                            <p class="text-xs muted">A super admin can see and operate every customer's infrastructure.</p>
                        </form>

                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="space-y-2">@csrf @method('PATCH')
                            <p class="text-xs font-medium uppercase tracking-wide muted">{{ $suspended ? 'Restore access' : 'Suspend access' }}</p>
                            <input type="hidden" name="suspend" value="{{ $suspended ? 0 : 1 }}">
                            <input class="field mt-0" name="reason" placeholder="Reason" value="{{ $suspended ? 'Restore account' : '' }}" aria-label="Reason">
                            <button @class(['button-secondary', '!text-emerald-600 dark:!text-emerald-300' => $suspended, '!text-rose-600 dark:!text-rose-300' => ! $suspended])>{{ $suspended ? 'Restore' : 'Suspend' }}</button>
                            <p class="text-xs muted">{{ $suspended ? 'Returns the account to normal immediately.' : 'Signs the customer out and blocks access. Their servers keep running.' }}</p>
                        </form>
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
