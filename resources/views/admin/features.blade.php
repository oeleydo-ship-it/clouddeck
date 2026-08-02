@extends('layouts.admin')
@section('admin-title', 'Feature flags')
@section('admin-description', 'Gate functionality across the platform, with percentage rollouts.')
@section('admin')
    <div x-data="{ creating: false }" class="space-y-5">
        <div class="flex justify-end">
            <button type="button" @click="creating = ! creating" class="button-primary" x-text="creating ? 'Cancel' : 'New flag'">New flag</button>
        </div>

        <section x-cloak x-show="creating" class="panel">
            <h2 class="font-semibold heading">Create flag</h2>
            <form method="POST" action="{{ route('admin.flags.store') }}" class="mt-5 max-w-2xl">@csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Key<input class="field font-mono text-xs" name="key" placeholder="remote_management" required></label>
                    <label class="text-sm heading">Name<input class="field" name="name" placeholder="Remote management" required></label>
                </div>
                <label class="mt-4 block text-sm heading">Description<input class="field" name="description" placeholder="What this gates, for whoever reads it next."></label>
                <div class="mt-4 flex flex-wrap items-end gap-6">
                    <label class="text-sm heading">Rollout %<input class="field !w-28" type="number" name="rollout_percentage" value="100" min="0" max="100" required></label>
                    <label class="flex items-center gap-2 pb-3 text-sm heading"><input type="checkbox" name="enabled" value="1" checked>Enabled</label>
                </div>
                <p class="mt-2 text-xs muted">The key is permanent — it is what the code checks. The name and description are for people.</p>
                <button class="button-primary mt-5">Create flag</button>
            </form>
        </section>

        <div class="space-y-3">
            @forelse($flags as $flag)
                <form method="POST" action="{{ route('admin.flags.update', $flag) }}" class="panel">@csrf @method('PATCH')
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <code class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs heading dark:bg-white/10">{{ $flag->key }}</code>
                                @if($flag->enabled)
                                    <span class="badge bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"><span class="badge-dot bg-emerald-500"></span>{{ $flag->rollout_percentage }}% of customers</span>
                                @else
                                    <span class="badge bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300"><span class="badge-dot bg-slate-400"></span>Off for everyone</span>
                                @endif
                            </div>
                            @if($flag->description)<p class="mt-2 text-sm muted">{{ $flag->description }}</p>@endif
                        </div>
                        <label class="flex shrink-0 items-center gap-2 text-sm heading"><input type="checkbox" name="enabled" value="1" @checked($flag->enabled)>Enabled</label>
                    </div>

                    <div class="mt-4 grid items-end gap-4 sm:grid-cols-[1fr_1fr_120px_auto]">
                        <label class="text-xs muted">Name<input class="field" name="name" value="{{ $flag->name }}" required></label>
                        <label class="text-xs muted">Description<input class="field" name="description" value="{{ $flag->description }}" placeholder="Optional"></label>
                        <label class="text-xs muted">Rollout %<input class="field" type="number" name="rollout_percentage" value="{{ $flag->rollout_percentage }}" min="0" max="100" required></label>
                        <button class="button-secondary">Save</button>
                    </div>
                </form>
            @empty
                <div class="panel text-center">
                    <p class="font-medium heading">No feature flags</p>
                    <p class="mt-1 text-sm muted">Nothing is being gated, so every customer sees everything the platform offers.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
