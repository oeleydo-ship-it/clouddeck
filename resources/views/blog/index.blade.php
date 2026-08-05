@extends('layouts.marketing')
@section('marketing')
<section class="relative overflow-hidden border-b border-slate-200 dark:border-white/10">
    <div class="landing-hero-wash opacity-60" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-5 py-20 lg:py-28">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">Blog</p>
        <h1 class="mt-4 max-w-3xl font-display text-4xl font-extrabold tracking-tight heading sm:text-5xl">Tips, updates, and how-tos.</h1>
        <p class="mt-6 max-w-2xl text-lg muted">Short posts about deploying, running servers, and using {{ $branding['name'] ?? 'Uplary' }}.</p>
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 py-16 lg:py-20">
    @if($posts->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200 px-6 py-16 text-center dark:border-white/10">
            <p class="font-display text-lg font-semibold heading">No posts yet</p>
            <p class="mt-2 text-sm muted">Check back soon — or explore the product while you wait.</p>
            <a href="{{ route('features') }}" class="button-primary mt-6 inline-flex">See features</a>
        </div>
    @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                @include('blog.partials.card', ['post' => $post])
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    @endif
</section>
@endsection
