{{-- Installed plugins or themes, read from the site itself. Shared by both tabs so the
     two can never drift apart. --}}
@php $items = $site->wordpressInventory($target); @endphp
<section class="panel">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-semibold heading">Installed {{ $plural }}</h2>
            <p class="mt-1 text-xs muted">
                @if($site->wordpress_inventory_at)
                    Read {{ $site->wordpress_inventory_at->diffForHumans() }}
                @else
                    Reading from the server…
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('wordpress.refresh',$site) }}">@csrf<button class="button-secondary !px-3 !py-1.5 text-xs">Refresh list</button></form>
    </div>

    @if($site->wordpress_inventory_error)
        {{-- The reason, rather than an empty list the operator has to guess about. --}}
        <p class="mt-4 rounded-lg bg-rose-50 p-3 font-mono text-xs text-rose-700 dark:bg-rose-400/10 dark:text-rose-300">
            The last read failed: {{ $site->wordpress_inventory_error }}
        </p>
    @endif

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
                    <p class="mt-1 font-mono text-xs muted">{{ $item['name'] ?? '' }}@if(! empty($item['version'])) · {{ $item['version'] }}@endif</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    {{-- Only the actions that apply: offering to delete the active theme,
                         or to activate what is already running, invites a broken site. --}}
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
                @if($site->wordpress_inventory_error) Nothing could be read — see above. @elseif($site->wordpress_inventory_at) No {{ $plural }} installed. @else {{ $branding['name'] }} is reading the list from the server — refresh the page in a moment. @endif
            </p>
        @endforelse
    </div>
</section>
