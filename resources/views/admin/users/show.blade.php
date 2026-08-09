@extends('layouts.admin')
@section('admin-title', $user->name)
@section('admin-description', 'Account management, support access, and impersonation history.')
@section('admin')
@php
    $suspended = (bool) $user->suspended_at;
    $busy = (bool) $activeImpersonation;
    $impersonateBlocked = $suspended
        || $busy
        || $user->id === auth()->id()
        || ($user->isSuperAdmin() && ! $canImpersonateAdmins);
@endphp

<div class="space-y-6" x-data="{ tab: '{{ request('tab', 'overview') }}', impersonateOpen: false }">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-4">
            <div class="grid size-12 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold uppercase text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ Str::substr($user->name, 0, 2) }}</div>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-lg font-semibold heading">{{ $user->name }}</h2>
                    @if($user->isSuperAdmin())<span class="badge bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Super admin</span>@endif
                    @if($suspended)<span class="badge bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300">Suspended</span>@endif
                    @if($busy)<span class="badge bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300">Being impersonated</span>@endif
                </div>
                <p class="truncate text-sm muted">{{ $user->email }}</p>
                <p class="mt-1 text-xs muted">Joined {{ $user->created_at->toFormattedDateString() }} · {{ $user->currentSubscription?->plan?->name ?? 'No subscription' }}</p>
            </div>
        </div>
        <a href="{{ route('admin.users') }}" class="button-secondary text-xs">Back to users</a>
    </div>

    <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-white/10">
        @foreach(['overview' => 'View user', 'subscription' => 'View subscription', 'billing' => 'View billing', 'activity' => 'View activity', 'impersonation' => 'Impersonation history'] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-cyan-500 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 dark:text-slate-400'"
                    class="shrink-0 border-b-2 px-3 py-2.5 text-sm">{{ $label }}</button>
        @endforeach
    </nav>

    <div x-show="tab === 'overview'" class="space-y-5">
        <section class="panel">
            <h3 class="font-semibold heading">Support actions</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @can('users.impersonate')
                    <button type="button" @click="impersonateOpen = true" @disabled($impersonateBlocked)
                            class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:hover:bg-white/5">
                        <span class="mt-0.5 grid size-8 place-items-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-medium heading">Impersonate user</span>
                            <span class="mt-0.5 block text-xs muted">Open their console for support without changing their password.</span>
                        </span>
                    </button>
                @endcan

                <a href="#suspend-panel" @click="tab = 'overview'" class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-left transition hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5">
                    <span class="mt-0.5 grid size-8 place-items-center rounded-lg bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><circle cx="12" cy="12" r="10"/><path d="M4.9 4.9l14.2 14.2"/></svg>
                    </span>
                    <span>
                        <span class="block text-sm font-medium heading">{{ $suspended ? 'Restore user' : 'Suspend user' }}</span>
                        <span class="mt-0.5 block text-xs muted">{{ $suspended ? 'Return this account to normal access.' : 'Sign them out and block console access.' }}</span>
                    </span>
                </a>
            </div>

            @if($impersonateBlocked && $canImpersonate)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100">
                    @if($user->id === auth()->id())
                        You cannot impersonate your own account.
                    @elseif($suspended)
                        Suspended accounts cannot be impersonated. Restore the account first.
                    @elseif($busy)
                        {{ $activeImpersonation?->admin?->name ?? 'Another administrator' }} is already impersonating this account.
                    @elseif($user->isSuperAdmin())
                        Impersonating another super admin is disabled. Enable it under Admin → Settings if required.
                    @endif
                </div>
            @endif
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <form method="POST" action="{{ route('admin.users.subscription', $user) }}" class="panel space-y-2">@csrf
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
            </form>

            <form method="POST" action="{{ route('admin.users.role', $user) }}" class="panel space-y-2">@csrf @method('PATCH')
                <p class="text-xs font-medium uppercase tracking-wide muted">Role</p>
                <select class="field mt-0" name="role" aria-label="Role for {{ $user->email }}">
                    <option value="customer" @selected($user->role === 'customer')>Customer</option>
                    <option value="super_admin" @selected($user->role === 'super_admin')>Super admin</option>
                </select>
                <button class="button-secondary">Update role</button>
            </form>

            <form id="suspend-panel" method="POST" action="{{ route('admin.users.suspend', $user) }}" class="panel space-y-2">@csrf @method('PATCH')
                <p class="text-xs font-medium uppercase tracking-wide muted">{{ $suspended ? 'Restore access' : 'Suspend access' }}</p>
                <input type="hidden" name="suspend" value="{{ $suspended ? 0 : 1 }}">
                <input class="field mt-0" name="reason" placeholder="Reason" value="{{ $suspended ? 'Restore account' : '' }}" aria-label="Reason">
                <button @class(['button-secondary', '!text-emerald-600 dark:!text-emerald-300' => $suspended, '!text-rose-600 dark:!text-rose-300' => ! $suspended])>{{ $suspended ? 'Restore' : 'Suspend' }}</button>
            </form>
        </div>
    </div>

    <div x-cloak x-show="tab === 'subscription'" class="panel">
        <h3 class="font-semibold heading">Subscription</h3>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="muted">Plan</dt><dd class="heading">{{ $user->currentSubscription?->plan?->name ?? 'None' }}</dd></div>
            <div><dt class="muted">Status</dt><dd class="heading capitalize">{{ $user->currentSubscription?->status ?? '—' }}</dd></div>
            <div><dt class="muted">Provider</dt><dd class="heading">{{ $user->currentSubscription?->provider ?? '—' }}</dd></div>
            <div><dt class="muted">Period ends</dt><dd class="heading">{{ $user->currentSubscription?->current_period_ends_at?->toDayDateTimeString() ?? '—' }}</dd></div>
        </dl>
    </div>

    <div x-cloak x-show="tab === 'billing'" class="panel">
        <h3 class="font-semibold heading">Billing</h3>
        <p class="mt-2 text-sm muted">Stripe customer: <code class="text-xs">{{ $user->stripe_customer_id ?: 'Not linked' }}</code></p>
        <p class="mt-3 text-sm muted">Open the customer billing portal from their account after impersonation, or review invoices under Admin → Billing review.</p>
        <a href="{{ route('admin.billing') }}" class="button-secondary mt-4 inline-flex text-xs">Open billing review</a>
    </div>

    <div x-cloak x-show="tab === 'activity'" class="panel">
        <h3 class="font-semibold heading">Recent activity</h3>
        <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
            @forelse($activity as $log)
                <div class="flex flex-wrap justify-between gap-3 py-3 text-sm">
                    <div>
                        <p class="font-medium heading">{{ str_replace('.', ' · ', $log->action) }}</p>
                        <p class="text-xs muted">{{ $log->actor?->name ?? 'System' }} · {{ $log->ip_address }}</p>
                    </div>
                    <p class="text-xs muted">{{ $log->created_at?->toDayDateTimeString() }}</p>
                </div>
            @empty
                <p class="py-8 text-center text-sm muted">No audit events for this account yet.</p>
            @endforelse
        </div>
    </div>

    <div x-cloak x-show="tab === 'impersonation'" class="space-y-4">
        <form method="GET" class="panel grid gap-3 md:grid-cols-4">
            <input type="hidden" name="tab" value="impersonation">
            <label class="text-sm heading">Administrator
                <select class="field" name="admin_id">
                    <option value="">Any</option>
                    @foreach($historyAdmins as $admin)
                        <option value="{{ $admin->id }}" @selected((string) request('admin_id') === (string) $admin->id)>{{ $admin->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm heading">From<input class="field" type="date" name="from" value="{{ request('from') }}"></label>
            <label class="text-sm heading">To<input class="field" type="date" name="to" value="{{ request('to') }}"></label>
            <label class="text-sm heading">Status
                <select class="field" name="status">
                    <option value="">Any</option>
                    @foreach(['active' => 'Active', 'ended' => 'Ended', 'terminated' => 'Terminated'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="md:col-span-4"><button class="button-secondary">Filter history</button></div>
        </form>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/[.03]">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 text-xs uppercase tracking-wide muted dark:border-white/5">
                        <tr>
                            <th class="px-4 py-3 font-medium">Administrator</th>
                            <th class="px-4 py-3 font-medium">Started</th>
                            <th class="px-4 py-3 font-medium">Ended</th>
                            <th class="px-4 py-3 font-medium">Duration</th>
                            <th class="px-4 py-3 font-medium">IP</th>
                            <th class="px-4 py-3 font-medium">Support mode</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @forelse($impersonationHistory as $session)
                            <tr>
                                <td class="px-4 py-3 heading">{{ $session->admin?->name ?? '—' }}</td>
                                <td class="px-4 py-3 muted">{{ $session->started_at?->toDayDateTimeString() }}</td>
                                <td class="px-4 py-3 muted">{{ $session->ended_at?->toDayDateTimeString() ?? '—' }}</td>
                                <td class="px-4 py-3 muted">{{ $session->durationForHumans() }}</td>
                                <td class="px-4 py-3 muted">{{ $session->ip_address ?? '—' }}</td>
                                <td class="px-4 py-3 muted">{{ $session->support_mode === 'read_only' ? 'Read only' : 'Full access' }}</td>
                                <td class="px-4 py-3 capitalize">{{ $session->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center muted">No impersonation sessions for this account.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3 dark:border-white/5">{{ $impersonationHistory->links() }}</div>
        </section>
    </div>

    @can('users.impersonate')
        <div x-cloak x-show="impersonateOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="impersonate-title">
            <div class="absolute inset-0 bg-slate-950/50" @click="impersonateOpen = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-white/10 dark:bg-slate-950">
                <h3 id="impersonate-title" class="text-lg font-semibold heading">Impersonate user?</h3>
                <p class="mt-2 text-sm muted">You are about to access this account as <strong class="heading">{{ $user->name }}</strong>. Any actions you perform may affect their account.</p>

                @if(! $impersonateBlocked)
                    <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="mt-5 space-y-4">@csrf
                        <fieldset>
                            <legend class="text-xs font-medium uppercase tracking-wide muted">Support mode</legend>
                            <label class="mt-2 flex gap-2 text-sm heading"><input type="radio" name="support_mode" value="full" checked>Full access</label>
                            <label class="mt-2 flex gap-2 text-sm heading"><input type="radio" name="support_mode" value="read_only">Read only</label>
                            <p class="mt-2 text-xs muted">Read only blocks deletes, credential changes, and other destructive actions.</p>
                        </fieldset>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="button-secondary" @click="impersonateOpen = false">Cancel</button>
                            <button class="button-primary">Start impersonation</button>
                        </div>
                    </form>
                @else
                    <div class="mt-5 flex justify-end">
                        <button type="button" class="button-secondary" @click="impersonateOpen = false">Close</button>
                    </div>
                @endif
            </div>
        </div>
    @endcan
</div>
@endsection
