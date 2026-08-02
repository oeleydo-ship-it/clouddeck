@extends('layouts.app')
@section('content')
@php
    $roleLabels = ['owner' => 'Owner', 'admin' => 'Administrator', 'operator' => 'Operator', 'viewer' => 'Viewer'];
    $roleHelp = [
        'owner' => 'Full control, including deleting the team.',
        'admin' => 'Manages members, and can transfer or delete servers.',
        'operator' => 'Deploys and operates servers, but cannot manage members.',
        'viewer' => 'Read-only access to shared infrastructure.',
    ];
    $activeTeamId = auth()->user()->current_team_id;
@endphp
<div class="mx-auto max-w-5xl px-5 py-10" x-data="{ creating: {{ $owned->isEmpty() ? 'true' : 'false' }} }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-cyan-600 dark:text-cyan-300">Collaboration</p>
            <h1 class="mt-1 text-3xl font-semibold heading">Teams</h1>
            <p class="mt-2 muted">Share servers with colleagues. Roles decide who can look, who can deploy, and who can remove things.</p>
        </div>
        <button type="button" @click="creating = ! creating" class="button-primary shrink-0" x-text="creating ? 'Cancel' : 'New team'">New team</button>
    </div>

    @if($errors->any())
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
            <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form x-cloak x-show="creating" method="POST" action="{{ route('teams.store') }}" class="panel mt-6 flex flex-wrap items-end gap-4">@csrf
        <label class="grow text-sm heading">Team name<input class="field" name="name" value="{{ old('name') }}" placeholder="Platform engineering" required></label>
        <button class="button-primary shrink-0">Create team</button>
    </form>

    <div class="mt-6 space-y-5">
        @forelse($owned as $team)
            @php $isActive = $activeTeamId === $team->id; @endphp
            <section class="panel">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold heading">{{ $team->name }}</h2>
                            @if($isActive)<span class="badge bg-cyan-50 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300"><span class="badge-dot bg-cyan-500"></span>Active workspace</span>@endif
                        </div>
                        <p class="mt-1 text-sm muted">You own this team · {{ $team->memberships->count() }} {{ Str::plural('member', $team->memberships->count()) }}</p>
                    </div>
                    @unless($isActive)
                        <form method="POST" action="{{ route('teams.switch',$team) }}">@csrf<button class="button-secondary">Switch to this workspace</button></form>
                    @endunless
                </div>

                <div class="mt-5 divide-y divide-slate-100 dark:divide-white/5">
                    @foreach($team->memberships as $membership)
                        <div class="flex flex-wrap items-center gap-4 py-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold uppercase text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ Str::substr($membership->user->name, 0, 2) }}</div>
                            <div class="min-w-0 grow">
                                <p class="truncate text-sm font-medium heading">{{ $membership->user->name }}</p>
                                <p class="truncate text-xs muted">{{ $membership->user->email }}</p>
                            </div>
                            @if($membership->role === 'owner')
                                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300">Owner</span>
                            @else
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('teams.members.role',[$team,$membership]) }}" class="flex items-center gap-2">@csrf @method('PATCH')
                                        <select class="field mt-0 !py-1.5 text-sm" name="role" aria-label="Role for {{ $membership->user->email }}">
                                            @foreach(['viewer','operator','admin'] as $role)
                                                <option value="{{ $role }}" @selected($membership->role === $role)>{{ $roleLabels[$role] }}</option>
                                            @endforeach
                                        </select>
                                        <button class="button-secondary !px-3 !py-1.5 text-xs">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('teams.members.remove',[$team,$membership]) }}" onsubmit="return confirm('Remove {{ $membership->user->email }} from {{ $team->name }}?')">@csrf @method('DELETE')
                                        <button class="button-secondary !px-3 !py-1.5 text-xs !text-rose-600 dark:!text-rose-300">Remove</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @php $pending = $team->invitations->whereNull('accepted_at'); @endphp
                @if($pending->isNotEmpty())
                    <div class="mt-4 rounded-xl bg-amber-50 p-4 dark:bg-amber-400/10">
                        <p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Awaiting acceptance</p>
                        <div class="mt-2 space-y-1 text-sm text-amber-800 dark:text-amber-200/90">
                            @foreach($pending as $invitation)
                                <p>{{ $invitation->email }} · {{ $roleLabels[$invitation->role] ?? $invitation->role }} · expires {{ $invitation->expires_at?->diffForHumans() ?? 'soon' }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('teams.invite',$team) }}" class="mt-5 border-t border-slate-100 pt-5 dark:border-white/5">@csrf
                    <p class="text-sm font-medium heading">Invite someone</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_180px_auto]">
                        <input class="field mt-0" type="email" name="email" placeholder="colleague@example.com" required aria-label="Email to invite">
                        <select class="field mt-0" name="role" aria-label="Role for the invitation">
                            @foreach(['viewer','operator','admin'] as $role)
                                <option value="{{ $role }}">{{ $roleLabels[$role] }}</option>
                            @endforeach
                        </select>
                        <button class="button-primary shrink-0">Send invitation</button>
                    </div>
                </form>
            </section>
        @empty
            <div class="panel text-center">
                <p class="font-medium heading">You do not own a team yet</p>
                <p class="mt-1 text-sm muted">Create one to share servers with colleagues without handing over your own account.</p>
            </div>
        @endforelse
    </div>

    @if($memberships->isNotEmpty())
        <section class="panel mt-6">
            <h2 class="font-semibold heading">Teams you belong to</h2>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                @foreach($memberships as $membership)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium heading">{{ $membership->team->name }}</p>
                            <p class="truncate text-xs muted">{{ $roleLabels[$membership->role] ?? $membership->role }} · owned by {{ $membership->team->owner->email }}</p>
                        </div>
                        @if($activeTeamId === $membership->team_id)
                            <span class="badge bg-cyan-50 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300"><span class="badge-dot bg-cyan-500"></span>Active</span>
                        @else
                            <form method="POST" action="{{ route('teams.switch',$membership->team) }}">@csrf<button class="button-secondary !px-3 !py-1.5 text-xs">Switch</button></form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel mt-6">
        <h2 class="font-semibold heading">What each role can do</h2>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach($roleHelp as $role => $description)
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-white/5">
                    <dt class="text-sm font-medium heading">{{ $roleLabels[$role] }}</dt>
                    <dd class="mt-1 text-sm muted">{{ $description }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
@endsection
