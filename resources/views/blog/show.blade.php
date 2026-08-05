@extends('layouts.marketing')
@section('marketing')
<article class="relative overflow-hidden border-b border-slate-200 dark:border-white/10">
    <div class="landing-hero-wash opacity-40" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-3xl px-5 py-16 lg:py-20">
        <a href="{{ route('blog') }}" class="text-sm font-semibold text-sky-600 hover:underline dark:text-sky-300">← All posts</a>
        <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight tracking-tight heading sm:text-5xl">{{ $post->title }}</h1>
        <p class="mt-4 text-sm muted">
            {{ $post->published_at->toFormattedDateString() }} · {{ $post->reading_time }} min read
            @if($post->author) · {{ $post->author->name }}@endif
        </p>
    </div>
</article>

<article class="mx-auto max-w-3xl px-5 py-12">
    @if($post->cover_url)
        <img src="{{ $post->cover_url }}" alt="" class="mb-10 w-full rounded-2xl object-cover">
    @endif

    @if($post->excerpt)
        <p class="text-lg muted">{{ $post->excerpt }}</p>
    @endif

    {{-- Escaped, then paragraph breaks restored: posts are written by an administrator, but
         rendering raw HTML would turn the editor into a way to inject script into every
         reader's browser. --}}
    <div class="mt-8 space-y-5 text-base leading-7 heading">
        @foreach(preg_split('/\R{2,}/', trim($post->body)) as $paragraph)
            <p>{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
    </div>
</article>

@if($related->isNotEmpty())
    <section class="border-t border-slate-200 bg-slate-50 py-16 dark:border-white/10 dark:bg-white/[.02]">
        <div class="mx-auto max-w-7xl px-5">
            <h2 class="font-display text-2xl font-bold heading">More posts</h2>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach($related as $relatedPost)
                    @include('blog.partials.card', ['post' => $relatedPost])
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
