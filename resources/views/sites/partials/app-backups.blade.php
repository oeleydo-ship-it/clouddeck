@php
    $canSiteBackups = (bool) ($planFeatures['site_backups'] ?? false);
    $fullAppBackups = $site->backups->where('kind', 'full_app')->sortByDesc('created_at');
    $defaultBackupDisk = app(\App\Services\BackupStorage::class)->defaultDisk();
    $diskLabels = [
        'local' => 'Local',
        's3' => 'Object storage',
    ];
@endphp
<section class="panel">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="font-semibold heading">Full site backups</h2>
            <p class="mt-1 text-sm muted">Offloads the live application tree (<code>current</code> + <code>shared</code>) and a database dump to {{ $branding['name'] }} storage ({{ $diskLabels[$defaultBackupDisk] ?? $defaultBackupDisk }}). Works on custom IP servers — no provider snapshot required.</p>
        </div>
        @if($canSiteBackups)
            <form method="POST" action="{{ route('site-backups.store', $site) }}">@csrf
                <button class="button-primary shrink-0">Create full backup</button>
            </form>
        @else
            <a href="{{ route('billing.index') }}" class="button-primary shrink-0">Upgrade to unlock</a>
        @endif
    </div>

    @unless($canSiteBackups)
        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
            Site backups (code + database) aren’t on your plan. <a class="font-medium underline" href="{{ route('billing.index') }}">Upgrade or subscribe</a> to create, download, or restore offloaded archives.
        </p>
    @endunless

    <div class="mt-5 divide-y divide-slate-100 dark:divide-white/5">
        @forelse($fullAppBackups as $backup)
            <article class="space-y-3 py-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-mono text-sm heading">{{ $backup->label }}</p>
                            <span @class([
                                'badge',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $backup->status === 'ready',
                                'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300' => $backup->status === 'failed',
                                'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' => ! in_array($backup->status, ['ready', 'failed'], true),
                            ])>{{ ucfirst($backup->status) }}</span>
                        </div>
                        <p class="mt-1 text-xs muted">
                            {{ $backup->created_at->diffForHumans() }}
                            @if($backup->size) · {{ $backup->size_for_humans }}@endif
                            @if($backup->disk) · {{ $diskLabels[$backup->disk] ?? $backup->disk }}@endif
                            @if($backup->user) · {{ $backup->user->name }}@endif
                        </p>
                        @if($backup->failure_reason)
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $backup->failure_reason }}</p>
                        @endif
                    </div>

                    @if($canSiteBackups && in_array($backup->status, ['ready', 'failed', 'pending', 'running'], true))
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if($backup->status === 'ready')
                                <a class="button-secondary !px-3 !py-1.5 text-xs" href="{{ route('site-backups.download', $backup) }}">Download</a>
                            @endif
                            <form method="POST" action="{{ route('site-backups.destroy', $backup) }}" onsubmit="return confirm('{{ $backup->status === 'ready' ? 'Delete this archive from storage?' : 'Remove this backup record?' }}')">@csrf @method('DELETE')
                                <button class="button-secondary !px-3 !py-1.5 text-xs !text-rose-600 dark:!text-rose-300">{{ $backup->status === 'running' ? 'Cancel' : 'Delete' }}</button>
                            </form>
                        </div>
                    @elseif($backup->status === 'ready' && ! $canSiteBackups)
                        <a href="{{ route('billing.index') }}" class="button-secondary !px-3 !py-1.5 text-xs shrink-0">Upgrade to manage</a>
                    @endif
                </div>

                @if($backup->status === 'ready' && $canSiteBackups)
                    <div class="w-full rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-white/[.03]">
                        <p class="text-xs font-medium heading">Restore this archive</p>
                        <p class="mt-1 text-xs muted">Replaces the live site. Type <span class="font-mono heading">{{ $site->domain }}</span> to confirm.</p>
                        <form method="POST" action="{{ route('site-backups.restore', $backup) }}" class="mt-3 flex w-full max-w-xl flex-wrap items-center gap-2">
                            @csrf
                            <input class="field mt-0 min-w-[12rem] flex-1 !py-1.5 text-xs" name="confirmation" placeholder="Type {{ $site->domain }}" autocomplete="off">
                            <button class="button-secondary shrink-0 !px-3 !py-1.5 text-xs !text-amber-600 dark:!text-amber-300">Restore</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <p class="py-6 text-center text-sm muted">No full site backups yet. Create one before a risky deploy or plugin change.</p>
        @endforelse
    </div>
</section>
