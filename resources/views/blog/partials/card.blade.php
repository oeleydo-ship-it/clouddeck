<article class="panel flex flex-col overflow-hidden !p-0">
    @if($post->cover_url)
        <img src="{{ $post->cover_url }}" alt="" class="h-40 w-full object-cover">
    @endif
    <div class="flex grow flex-col p-5">
        <p class="text-xs muted">{{ $post->published_at->toFormattedDateString() }} · {{ $post->reading_time }} min read</p>
        <h3 class="mt-2 font-semibold heading"><a href="{{ route('blog.show', $post->slug) }}" class="hover:text-cyan-600 dark:hover:text-cyan-300">{{ $post->title }}</a></h3>
        @if($post->excerpt)<p class="mt-2 line-clamp-3 text-sm muted">{{ $post->excerpt }}</p>@endif
        <a href="{{ route('blog.show', $post->slug) }}" class="mt-4 text-sm font-medium text-cyan-600 dark:text-cyan-300">Read more →</a>
    </div>
</article>
