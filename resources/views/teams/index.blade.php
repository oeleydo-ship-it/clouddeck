@extends('layouts.app')
@section('content')
@php
    $roleLabels = ['owner' => 'Owner', 'admin' => 'Administrator', 'operator' => 'Operator', 'viewer' => 'Viewer'];
    $roleHelp = [
        'owner' => [
            'summary' => 'Full control of the team and its servers.',
            'can' => ['Manage members and invitations', 'View, operate, transfer, and delete team servers', 'Switch this team as active workspace'],
            'cannot' => ['Be removed or demoted by other members'],
        ],
        'admin' => [
            'summary' => 'Team management plus full server control.',
            'can' => ['Invite, edit, resend, and cancel invitations', 'Change member roles and remove members', 'View, operate, transfer, and delete team servers'],
            'cannot' => ['Remove or demote the team owner'],
        ],
        'operator' => [
            'summary' => 'Deploy and operate shared infrastructure.',
            'can' => ['View team servers', 'Update and operate team servers (deploy, configure)'],
            'cannot' => ['Manage members or invitations', 'Transfer or delete team servers'],
        ],
        'viewer' => [
            'summary' => 'Read-only access to shared infrastructure.',
            'can' => ['View team servers and related console pages'],
            'cannot' => ['Deploy or change servers', 'Manage members or invitations', 'Transfer or delete servers'],
        ],
    ];
    $inviteRoles = ['viewer', 'operator', 'admin'];
    $activeTeamId = auth()->user()->current_team_id;
@endphp
<div class="app-main !max-w-5xl" x-data="{ creating: {{ $owned->isEmpty() ? 'true' : 'false' }} }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="page-eyebrow">Collaboration</p>
            <h1 class="page-title">Teams</h1>
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
            @php
                $isActive = $activeTeamId === $team->id;
                $pending = $team->invitations;
            @endphp
            <section class="panel">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold heading">{{ $team->name }}</h2>
                            @if($isActive)<span class="badge badge-info"><span class="badge-dot bg-[#0070eb]"></span>Active workspace</span>@endif
                        </div>
                        <p class="mt-1 text-sm muted">You own this team · {{ $team->memberships->count() }} {{ Str::plural('member', $team->memberships->count()) }}@if($pending->isNotEmpty()) · {{ $pending->count() }} pending {{ Str::plural('invite', $pending->count()) }}@endif</p>
                    </div>
                    @unless($isActive)
                        <form method="POST" action="{{ route('teams.switch',$team) }}">@csrf<button class="button-secondary">Switch to this workspace</button></form>
                    @endunless
                </div>

                <div class="mt-5">
                    <h3 class="text-sm font-medium heading">Members</h3>
                    <div class="mt-2 divide-y divide-slate-100 dark:divide-white/5">
                        @foreach($team->memberships as $membership)
                            <div class="flex flex-wrap items-center gap-4 py-3">
                                <div class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold uppercase text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ Str::substr($membership->user->name, 0, 2) }}</div>
                                <div class="min-w-0 grow">
                                    <p class="truncate text-sm font-medium heading">{{ $membership->user->name }}</p>
                                    <p class="truncate text-xs muted">{{ $membership->user->email }}</p>
                                </div>
                                @if($membership->role === 'owner')
                                    <span class="badge badge-neutral">Owner</span>
                                @else
                                    <div class="flex flex-wrap items-center gap-2">
                                        <form method="POST" action="{{ route('teams.members.role',[$team,$membership]) }}" class="flex items-center gap-2">@csrf @method('PATCH')
                                            <select class="field mt-0 !py-1.5 text-sm" name="role" aria-label="Role for {{ $membership->user->email }}">
                                                @foreach($inviteRoles as $role)
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
                </div>

                <div class="mt-5 border-t border-slate-100 pt-5 dark:border-white/5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-medium heading">Pending invitations</h3>
                        @if($pending->isNotEmpty())
                            <span class="badge badge-warning">{{ $pending->count() }} awaiting acceptance</span>
                        @endif
                    </div>

                    @if($pending->isEmpty())
                        <p class="mt-3 text-sm muted">No pending invitations. Invite a colleague below.</p>
                    @else
                        <div class="mt-3 space-y-2">
                            @foreach($pending as $invitation)
                                @php $expired = $invitation->isExpired(); @endphp
                                <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 p-3 dark:border-amber-400/20 dark:bg-amber-400/10" x-data="{ editing: false }">
                                    <div class="flex flex-wrap items-center gap-3" x-show="! editing">
                                        <div class="min-w-0 grow">
                                            <p class="truncate text-sm font-medium text-amber-950 dark:text-amber-100">{{ $invitation->email }}</p>
                                            <p class="mt-0.5 text-xs text-amber-800/80 dark:text-amber-200/80">
                                                @if($expired)
                                                    Expired {{ $invitation->expires_at?->diffForHumans() ?? '' }}
                                                @else
                                                    Expires {{ $invitation->expires_at?->diffForHumans() ?? 'soon' }}
                                                @endif
                                            </p>
                                        </div>
                                        <span class="badge {{ $expired ? 'badge-danger' : 'badge-warning' }}">{{ $expired ? 'Expired' : 'Pending' }}</span>
                                        <span class="badge badge-neutral">{{ $roleLabels[$invitation->role] ?? $invitation->role }}</span>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" class="button-secondary !px-3 !py-1.5 text-xs" @click="editing = true">Edit</button>
                                            <form method="POST" action="{{ route('teams.invitations.resend', [$team, $invitation]) }}">@csrf
                                                <button class="button-secondary !px-3 !py-1.5 text-xs">Resend</button>
                                            </form>
                                            <form method="POST" action="{{ route('teams.invitations.destroy', [$team, $invitation]) }}" onsubmit="return confirm('Cancel invitation to {{ $invitation->email }}?')">@csrf @method('DELETE')
                                                <button class="button-secondary !px-3 !py-1.5 text-xs !text-rose-600 dark:!text-rose-300">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                    <form x-cloak x-show="editing" method="POST" action="{{ route('teams.invitations.update', [$team, $invitation]) }}" class="flex flex-wrap items-end gap-3">@csrf @method('PATCH')
                                        <div class="min-w-0 grow">
                                            <p class="truncate text-sm font-medium text-amber-950 dark:text-amber-100">{{ $invitation->email }}</p>
                                            <label class="mt-2 block text-xs font-medium text-amber-900 dark:text-amber-200">Role
                                                <select class="field mt-1" name="role" aria-label="Role for invitation to {{ $invitation->email }}">
                                                    @foreach($inviteRoles as $role)
                                                        <option value="{{ $role }}" @selected($invitation->role === $role)>{{ $roleLabels[$role] }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </div>
                                        <button class="button-primary !px-3 !py-1.5 text-xs">Save role</button>
                                        <button type="button" class="button-secondary !px-3 !py-1.5 text-xs" @click="editing = false">Cancel</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('teams.invite',$team) }}" class="mt-5 border-t border-slate-100 pt-5 dark:border-white/5" x-data="{ role: '{{ old('role', 'viewer') }}' }">@csrf
                    <p class="text-sm font-medium heading">Invite someone</p>
                    <p class="mt-1 text-xs muted">They receive an email with a link that expires in seven days.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_200px_auto]">
                        <input class="field mt-0" type="email" name="email" value="{{ old('email') }}" placeholder="colleague@example.com" required aria-label="Email to invite">
                        <select class="field mt-0" name="role" x-model="role" aria-label="Role for the invitation">
                            @foreach($inviteRoles as $role)
                                <option value="{{ $role }}">{{ $roleLabels[$role] }}</option>
                            @endforeach
                        </select>
                        <button class="button-primary shrink-0">Send invitation</button>
                    </div>
                    <p class="mt-2 text-xs muted" x-text="{
                        viewer: '{{ $roleHelp['viewer']['summary'] }}',
                        operator: '{{ $roleHelp['operator']['summary'] }}',
                        admin: '{{ $roleHelp['admin']['summary'] }}'
                    }[role]">{{ $roleHelp['viewer']['summary'] }}</p>
                </form>
            </section>
        @empty
            <div class="panel text-center">
                <p class="font-medium heading">You do not own a team yet</p>
                <p class="mt-1 text-sm muted">Create one to share servers with colleagues without handing over your own account.</p>
                <button type="button" class="button-primary mt-4" @click="creating = true">Create your first team</button>
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
                            <span class="badge badge-info"><span class="badge-dot bg-[#0070eb]"></span>Active</span>
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
        <p class="mt-1 text-sm muted">Privileges match server and team access checks in the console.</p>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach($roleHelp as $role => $details)
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-white/5">
                    <dt class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium heading">{{ $roleLabels[$role] }}</span>
                        @if($role === 'viewer')<span class="badge badge-neutral">Read-only</span>
                        @elseif($role === 'operator')<span class="badge badge-info">Operate</span>
                        @elseif($role === 'admin')<span class="badge badge-warning">Manage</span>
                        @else<span class="badge badge-success">Full</span>@endif
                    </dt>
                    <dd class="mt-2 space-y-2 text-sm muted">
                        <p>{{ $details['summary'] }}</p>
                        <ul class="list-inside list-disc space-y-0.5 text-xs">
                            @foreach($details['can'] as $item)<li class="text-emerald-700 dark:text-emerald-300">{{ $item }}</li>@endforeach
                            @foreach($details['cannot'] as $item)<li class="text-slate-500 dark:text-slate-400">Cannot: {{ $item }}</li>@endforeach
                        </ul>
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
@endsection
