@extends('layouts.marketing')
@section('marketing')
<section class="mx-auto max-w-7xl px-5 py-20">
    <h1 class="text-4xl font-semibold heading">Blog</h1>
    <p class="mt-5 text-lg muted">Notes on deploying and operating Laravel in production.</p>

    @if($posts->isEmpty())
        <div class="panel mt-12 text-center">
            <p class="font-medium heading">Nothing published yet</p>
            <p class="mt-1 text-sm muted">Check back shortly.</p>
        </div>
    @else
        <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                @include('blog.partials.card', ['post' => $post])
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    @endif
</section>
@endsection
