<div class="space-y-6">
    @unless($site->wordpressIsInstalled())
        <div class="panel">
            <p class="font-medium heading">Finish the install first</p>
            <p class="mt-1 text-sm muted">Plugins, themes, and backups all act on the live install, so they need the database tables the browser setup creates.</p>
        </div>
    @else
        {{-- Installed plugins and themes, read from the site itself --}}
        @foreach(['plugin' => 'Plugins', 'theme' => 'Themes'] as $target => $label)
            @php $items = $site->wordpressInventory($target); @endphp
            <section class="panel">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold heading">Installed {{ Str::lower($label) }}</h2>
                        <p class="mt-1 text-xs muted">
                            @if($site->wordpress_inventory_at)Read {{ $site->wordpress_inventory_at->diffForHumans() }}@else Not read yet @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('wordpress.refresh',$site) }}">@csrf<button class="button-secondary !px-3 !py-1.5 text-xs">Refresh list</button></form>
                </div>

                <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                    @forelse($items as $item)
                        @php $active = in_array($item['status'] ?? '', ['active', 'active-network'], true); @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-medium heading">{{ $item['title'] ?? $item['name'] ?? 'Unknown' }}</p>
                                    <span @class([
                                        'badge',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $active,
                                        'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300' => ! $active,
                                    ])>{{ $active ? 'Active' : ucfirst($item['status'] ?? 'inactive') }}</span>
                                    @if(($item['update'] ?? 'none') === 'available')<span class="badge bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Update available</span>@endif
                                </div>
                                <p class="mt-1 font-mono text-xs muted">{{ $item['name'] ?? '' }} @if(! empty($item['version'])) · {{ $item['version'] }}@endif</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach(array_filter([
                                    ($item['update'] ?? 'none') === 'available' ? 'update' : null,
                                    $active ? ($target === 'plugin' ? 'deactivate' : null) : 'activate',
                                    $active ? null : 'delete',
                                ]) as $action)
                                    <form method="POST" action="{{ route('wordpress.manage',$site) }}"
                                          @if($action === 'delete') onsubmit="return confirm('Delete {{ $item['name'] ?? '' }} from {{ $site->domain }}?')" @endif>
                                        @csrf
                                        <input type="hidden" name="target" value="{{ $target }}">
                                        <input type="hidden" name="action" value="{{ $action }}">
                                        <input type="hidden" name="slug" value="{{ $item['name'] ?? '' }}">
                                        <button @class(['button-secondary !px-3 !py-1.5 text-xs', '!text-rose-600 dark:!text-rose-300' => $action === 'delete'])>{{ ucfirst($action) }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm muted">
                            @if($site->wordpress_inventory_at) No {{ Str::lower($label) }} installed. @else Press “Refresh list” to read what this site has installed. @endif
                        </p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('wordpress.manage',$site) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-white/5">@csrf
                    <input type="hidden" name="target" value="{{ $target }}">
                    <input type="hidden" name="action" value="install">
                    <label class="grow text-sm heading">Install by slug<input class="field font-mono text-sm" name="slug" placeholder="{{ $target === 'plugin' ? 'wordfence' : 'twentytwentyfour' }}" required pattern="[a-z0-9][a-z0-9-]*"></label>
                    <button class="button-primary shrink-0">Install and activate</button>
                </form>
            </section>
        @endforeach

        {{-- Browsing the public directory, so a theme can be chosen without knowing its slug --}}
        <section class="panel">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold heading">Browse themes</h2>
                    <p class="mt-1 text-sm muted">From the wordpress.org directory. Installing activates it immediately.</p>
                </div>
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="tab" value="wordpress">
                    <input class="field mt-0" name="theme_search" value="{{ request('theme_search') }}" placeholder="Search themes">
                    <button class="button-secondary shrink-0">Search</button>
                </form>
            </div>

            @if(empty($directoryThemes))
                <p class="mt-5 text-sm muted">
                    {{ request('theme_search') ? 'Nothing matched that search.' : 'The wordpress.org directory could not be reached. Installing by slug above still works.' }}
                </p>
            @else
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($directoryThemes as $theme)
                        <article class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
                            @if($theme['screenshot'])
                                <img src="{{ Str::start($theme['screenshot'], 'http') === $theme['screenshot'] ? $theme['screenshot'] : 'https:'.$theme['screenshot'] }}" alt="" class="h-32 w-full object-cover" loading="lazy">
                            @endif
                            <div class="p-4">
                                <p class="truncate text-sm font-medium heading">{{ $theme['name'] }}</p>
                                <p class="mt-1 truncate text-xs muted">{{ $theme['author'] ?: 'Unknown author' }}@if($theme['installs']) · {{ number_format($theme['installs']) }}+ installs @endif</p>
                                <form method="POST" action="{{ route('wordpress.manage',$site) }}" class="mt-3">@csrf
                                    <input type="hidden" name="target" value="theme">
                                    <input type="hidden" name="action" value="install">
                                    <input type="hidden" name="slug" value="{{ $theme['slug'] }}">
                                    <button class="button-secondary w-full !px-3 !py-1.5 text-xs">Install</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Backups --}}
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
