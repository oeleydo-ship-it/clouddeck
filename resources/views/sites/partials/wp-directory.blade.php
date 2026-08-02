{{-- Browsing the public wordpress.org directory, so something can be chosen by name
     rather than by knowing its slug in advance. --}}
<section class="panel">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-semibold heading">Browse {{ $plural }}</h2>
            <p class="mt-1 text-sm muted">From the wordpress.org directory. Installing activates it immediately.</p>
        </div>
        <form method="GET" action="{{ route('sites.show',$site) }}#{{ $target }}s" class="flex gap-2">
            <input class="field mt-0" name="{{ $target }}_search" value="{{ request($target.'_search') }}" placeholder="Search {{ $plural }}">
            <button class="button-secondary shrink-0">Search</button>
            @if(request($target.'_search'))<a href="{{ route('sites.show',$site) }}#{{ $target }}s" class="button-secondary shrink-0">Clear</a>@endif
        </form>
    </div>

    @if(empty($results))
        <p class="mt-5 text-sm muted">
            {{ request($target.'_search') ? 'Nothing matched that search.' : 'The wordpress.org directory could not be reached. Installing by slug below still works.' }}
        </p>
    @else
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($results as $item)
                <article class="flex flex-col overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
                    @if($item['screenshot'])
                        <img src="{{ Str::startsWith($item['screenshot'], 'http') ? $item['screenshot'] : 'https:'.$item['screenshot'] }}" alt="" class="h-32 w-full object-cover" loading="lazy">
                    @endif
                    <div class="flex grow flex-col p-4">
                        <p class="truncate text-sm font-medium heading">{{ $item['name'] }}</p>
                        <p class="mt-1 truncate text-xs muted">
                            {{ $item['author'] ?: 'Unknown author' }}@if($item['installs']) · {{ number_format($item['installs']) }}+ installs @endif
                        </p>
                        @if(! empty($item['description']))<p class="mt-2 line-clamp-2 text-xs muted">{{ $item['description'] }}</p>@endif
                        <form method="POST" action="{{ route('wordpress.manage',$site) }}" class="mt-3 pt-1">@csrf
                            <input type="hidden" name="target" value="{{ $target }}">
                            <input type="hidden" name="action" value="install">
                            <input type="hidden" name="slug" value="{{ $item['slug'] }}">
                            <button class="button-secondary w-full !px-3 !py-1.5 text-xs">Install and activate</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('wordpress.manage',$site) }}" class="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-5 dark:border-white/5">@csrf
        <input type="hidden" name="target" value="{{ $target }}">
        <input type="hidden" name="action" value="install">
        <label class="grow text-sm heading">Install by slug<input class="field font-mono text-sm" name="slug" placeholder="{{ $target === 'plugin' ? 'wordfence' : 'twentytwentyfour' }}" required pattern="[a-z0-9][a-z0-9-]*"></label>
        <button class="button-primary shrink-0">Install and activate</button>
    </form>
</section>
