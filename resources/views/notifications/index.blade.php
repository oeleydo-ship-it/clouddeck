@extends('layouts.app')
@section('content')
<div class="app-main" x-data="managedTabs({ tab: @js($notificationTab), keys: @js($notificationTabKeys) })" @submit.capture="ensureTab($event)">
    <header>
        <p class="page-eyebrow">Monitoring</p>
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle">Incidents across your servers and sites, plus email recipients for operational events. With no recipients configured, everything goes to your account address.</p>
    </header>

    <div class="mt-8 flex gap-2 overflow-x-auto border-b border-slate-200 dark:border-white/10">
        @foreach(['incidents' => 'Incidents', 'email' => 'Email recipients'] as $key => $label)
            <button type="button" @click="tab='{{ $key }}'" :class="tab==='{{ $key }}' ? 'border-cyan-400 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 dark:text-slate-400'" class="border-b-2 px-4 py-3 text-sm">{{ $label }}</button>
        @endforeach
    </div>

    <div x-cloak x-show="tab==='incidents'" class="mt-6">
        @include('incidents._list')
    </div>

    <div x-cloak x-show="tab==='email'" class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
        <form method="POST" action="{{ route('notification-channels.store') }}" class="panel h-fit">@csrf
            <input type="hidden" name="_tab" value="email">
            <h2 class="flex items-center gap-3 section-title">
                <span class="stat-icon bg-sky-50 text-[#0058bc] dark:bg-sky-400/10 dark:text-sky-300">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9ZM10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                </span>
                Add recipient
            </h2>
            <p class="mt-2 text-xs muted">Recipients are account-wide — not tied to a single server. Leave every event box clear to receive all of them.</p>

            <label class="mt-5 block text-sm heading">Name
                <input class="field" name="name" value="{{ old('name') }}" placeholder="Operations team" required>
            </label>
            <label class="mt-4 block text-sm heading">Email
                <input class="field" type="email" name="address" value="{{ old('address') }}" placeholder="Leave blank to use your account address">
            </label>

            <fieldset class="mt-4">
                <legend class="text-sm font-medium heading">Notify about</legend>
                <p class="mt-1 text-xs muted">Leave every box clear to receive all of them.</p>
                <div class="mt-2 grid gap-2">
                    @foreach($notificationEvents as $key => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="events[]" value="{{ $key }}" @checked(is_array(old('events')) && in_array($key, old('events'), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button class="button-primary mt-5">Add recipient</button>
        </form>

        <section class="panel">
            <h2 class="font-semibold heading">Recipients</h2>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                @forelse($notificationChannels as $channel)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate heading">{{ $channel->name }} <span class="muted">/ {{ $channel->configuration['address'] ?? auth()->user()->email }}</span></p>
                            <p class="mt-0.5 text-xs muted">{{ $channel->events ? implode(', ', array_map(fn ($event) => $notificationEvents[$event] ?? $event, $channel->events)) : 'All events' }}</p>
                        </div>
                        <form method="POST" action="{{ route('notification-channels.destroy', $channel) }}">@csrf @method('DELETE')
                            <input type="hidden" name="_tab" value="email">
                            <button class="text-rose-600 dark:text-rose-300">Remove</button>
                        </form>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <p class="text-sm font-medium heading">No recipients yet</p>
                        <p class="mt-1 text-sm muted">Alerts currently go to {{ auth()->user()->email }}. Add a recipient to send elsewhere or narrow which events you hear about.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
