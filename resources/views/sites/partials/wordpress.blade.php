<div class="space-y-6">
    @unless($site->wordpressIsInstalled())
        <div class="panel">
            <p class="font-medium heading">Finish the install first</p>
            <p class="mt-1 text-sm muted">Plugins, themes, and backups all act on the live install, so they need the database tables the browser setup creates.</p>
        </div>
    @else
        @foreach(['plugin' => 'Plugins', 'theme' => 'Themes'] as $target => $label)
            <section class="panel">
                <h2 class="font-semibold heading">{{ $label }}</h2>
                <p class="mt-1 text-sm muted">Installed from wordpress.org by slug — the name in its directory URL, such as <code>{{ $target === 'plugin' ? 'wordfence' : 'twentytwentyfour' }}</code>.</p>
                <form method="POST" action="{{ route('wordpress.manage',$site) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
                    <input type="hidden" name="target" value="{{ $target }}">
                    <input type="hidden" name="action" value="install">
                    <label class="grow text-sm heading">Slug<input class="field font-mono text-sm" name="slug" placeholder="{{ $target === 'plugin' ? 'wordfence' : 'twentytwentyfour' }}" required pattern="[a-z0-9][a-z0-9-]*"></label>
                    <button class="button-primary shrink-0">Install and activate</button>
                </form>
                <form method="POST" action="{{ route('wordpress.manage',$site) }}" class="mt-3">@csrf
                    <input type="hidden" name="target" value="{{ $target }}">
                    <input type="hidden" name="action" value="list">
                    <button class="button-secondary !px-3 !py-1.5 text-xs">List installed {{ Str::lower($label) }}</button>
                </form>
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm heading">Manage an installed {{ $target }}</summary>
                    <form method="POST" action="{{ route('wordpress.manage',$site) }}" class="mt-3 flex flex-wrap items-end gap-3">@csrf
                        <input type="hidden" name="target" value="{{ $target }}">
                        <label class="grow text-sm heading">Slug<input class="field font-mono text-sm" name="slug" required pattern="[a-z0-9][a-z0-9-]*"></label>
                        <label class="text-sm heading">Action<select class="field" name="action">
                            <option value="update">Update</option>
                            <option value="activate">Activate</option>
                            @if($target === 'plugin')<option value="deactivate">Deactivate</option>@endif
                            <option value="delete">Delete</option>
                        </select></label>
                        <button class="button-secondary shrink-0">Run</button>
                    </form>
                </details>
            </section>
        @endforeach

        <section class="panel">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold heading">Backups</h2>
                    <p class="mt-1 text-sm muted">Captures the database and <code>wp-content</code> — the two things a deployment cannot bring back, since core files come from wordpress.org. The ten most recent are kept on the server.</p>
                </div>
                <form method="POST" action="{{ route('wordpress.backup',$site) }}">@csrf<button class="button-primary shrink-0">Back up now</button></form>
            </div>

            <div class="mt-5 divide-y divide-slate-100 dark:divide-white/5">
                @forelse($site->backups as $backup)
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
    @endunless
</div>
