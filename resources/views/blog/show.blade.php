@extends('layouts.marketing')
@section('marketing')
<article class="mx-auto max-w-3xl px-5 py-20">
    <a href="{{ route('blog') }}" class="text-sm font-medium text-cyan-600 dark:text-cyan-300">← All posts</a>
    <h1 class="mt-4 text-4xl font-semibold leading-tight heading">{{ $post->title }}</h1>
    <p class="mt-4 text-sm muted">
        {{ $post->published_at->toFormattedDateString() }} · {{ $post->reading_time }} min read
        @if($post->author) · {{ $post->author->name }}@endif
    </p>

    @if($post->cover_url)
        <img src="{{ $post->cover_url }}" alt="" class="mt-8 w-full rounded-2xl object-cover">
    @endif

    @if($post->excerpt)
        <p class="mt-8 text-lg muted">{{ $post->excerpt }}</p>
    @endif

    {{-- Escaped, then paragraph breaks restored: posts are written by an administrator, but
         rendering raw HTML would turn the editor into a way to inject script into every
         reader's browser. --}}
    <div class="mt-8 space-y-5 leading-7 heading">
        @foreach(preg_split('/\R{2,}/', trim($post->body)) as $paragraph)
            <p>{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
    </div>
</article>

@if($related->isNotEmpty())
    <section class="mx-auto max-w-7xl px-5 pb-16">
        <h2 class="text-2xl font-semibold heading">More posts</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach($related as $post)
                @include('blog.partials.card', ['post' => $post])
            @endforeach
        </div>
    </section>
@endif
@endsection
