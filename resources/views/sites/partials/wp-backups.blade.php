@php $localWpBackups = $site->backups->where('kind', 'wordpress_local')->sortByDesc('created_at'); @endphp
<section class="panel">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="font-semibold heading">On-server WordPress backup</h2>
            <p class="mt-1 text-sm muted">Captures the database and <code>wp-content</code> on this VPS — the two things a deployment cannot bring back, since core files come from wordpress.org. The ten most recent are kept on the server (not offloaded to {{ $branding['name'] }} storage).</p>
        </div>
        <form method="POST" action="{{ route('wordpress.backup',$site) }}">@csrf<button class="button-primary shrink-0">Back up now</button></form>
    </div>

    <div class="mt-5 divide-y divide-slate-100 dark:divide-white/5">
        @forelse($localWpBackups as $backup)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <p class="font-mono text-sm heading">{{ $backup->label }}</p>
                    <p class="mt-1 text-xs muted">
                        {{ $backup->created_at->diffForHumans() }}
                        @if($backup->size) · {{ $backup->size_for_humans }}@endif
                        @if($backup->user) · {{ $backup->user->name }}@endif
                    </p>
                    @if($backup->failure_reason)<p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $backup->failure_reason }}</p>@endif
                </div>
                <div class="flex items-center gap-3">
                    <span @class([
                        'badge',
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $backup->status === 'completed',
                        'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300' => $backup->status === 'failed',
                        'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' => ! in_array($backup->status, ['completed', 'failed'], true),
                    ])>{{ ucfirst($backup->status) }}</span>
                    @if($backup->status === 'completed')
                        <form method="POST" action="{{ route('wordpress.restore',$backup) }}" onsubmit="return confirm('Restore {{ $backup->label }}? The database and wp-content are replaced with this backup. The current state is captured first.')">@csrf
                            <button class="button-secondary !px-3 !py-1.5 text-xs !text-amber-600 dark:!text-amber-300">Restore</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm muted">No backups yet. Take one before installing a plugin or updating core.</p>
        @endforelse
    </div>
</section>
