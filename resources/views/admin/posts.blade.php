@extends('layouts.admin')
@section('admin-title', 'Blog')
@section('admin-description', 'Write, schedule, and publish posts for the public blog.')
@section('admin')
    <div x-data="{ editing: null, creating: false }" class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex grow gap-3">
                <input class="field mt-0" name="search" value="{{ request('search') }}" placeholder="Search posts by title">
                <button class="button-secondary shrink-0">Search</button>
                @if(request('search'))<a href="{{ route('admin.posts') }}" class="button-secondary shrink-0">Clear</a>@endif
            </form>
            <button type="button" @click="creating = ! creating; editing = null" class="button-primary shrink-0" x-text="creating ? 'Cancel' : 'New post'">New post</button>
        </div>

        <section x-cloak x-show="creating" class="panel">
            <h2 class="font-semibold heading">New post</h2>
            <form method="POST" action="{{ route('admin.posts.store') }}" enctype="multipart/form-data" class="mt-5">@csrf
                @include('admin.partials.post-fields', ['post' => null])
                <button class="button-primary mt-6">Create post</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 dark:border-white/10 dark:bg-white/[.03] dark:shadow-none">
            @forelse($posts as $post)
                <div class="border-b border-slate-100 last:border-0 dark:border-white/5">
                    <div class="flex flex-wrap items-center gap-4 px-6 py-4">
                        @if($post->cover_url)<img src="{{ $post->cover_url }}" alt="" class="size-12 shrink-0 rounded-lg object-cover">@endif
                        <div class="min-w-0 grow">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-medium heading">{{ $post->title }}</p>
                                @php $label = $post->status_label; @endphp
                                <span @class([
                                    'badge',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $label === 'Published',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' => $label === 'Scheduled',
                                    'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300' => $label === 'Draft',
                                ])>{{ $label }}</span>
                            </div>
                            <p class="truncate text-xs muted">
                                /blog/{{ $post->slug }}
                                @if($post->published_at) · {{ $post->published_at->toDayDateTimeString() }}@endif
                                @if($post->author) · {{ $post->author->name }}@endif
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($post->isPublished())<a href="{{ route('blog.show', $post->slug) }}" target="_blank" rel="noopener" class="button-secondary !px-3 !py-1.5 text-xs">View</a>@endif
                            <form method="POST" action="{{ route('admin.posts.publish', $post) }}">@csrf @method('PATCH')<button class="button-secondary !px-3 !py-1.5 text-xs">{{ $post->isPublished() ? 'Unpublish' : 'Publish' }}</button></form>
                            <button type="button" @click="editing = editing === '{{ $post->id }}' ? null : '{{ $post->id }}'; creating = false" class="button-secondary !px-3 !py-1.5 text-xs" x-text="editing === '{{ $post->id }}' ? 'Close' : 'Edit'">Edit</button>
                            <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" onsubmit="return confirm('Delete {{ $post->title }}?')">@csrf @method('DELETE')<button class="button-secondary !px-3 !py-1.5 text-xs !text-rose-600 dark:!text-rose-300">Delete</button></form>
                        </div>
                    </div>

                    <div x-cloak x-show="editing === '{{ $post->id }}'" class="border-t border-slate-100 bg-slate-50/60 px-6 py-5 dark:border-white/5 dark:bg-white/[.02]">
                        <form method="POST" action="{{ route('admin.posts.update', $post) }}" enctype="multipart/form-data">@csrf @method('PATCH')
                            @include('admin.partials.post-fields', ['post' => $post])
                            <button class="button-primary mt-6">Save post</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <p class="font-medium heading">{{ request('search') ? 'No posts match that search' : 'No posts yet' }}</p>
                    <p class="mt-1 text-sm muted">The public blog shows nothing until a post is published.</p>
                </div>
            @endforelse
        </section>

        <div>{{ $posts->links() }}</div>
    </div>
@endsection
