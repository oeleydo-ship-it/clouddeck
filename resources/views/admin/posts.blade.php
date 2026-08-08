@extends('layouts.admin')
@section('admin-title', 'Blog')
@section('admin-description', 'Write, schedule, and publish posts for the public blog.')
@section('admin')
    <div
        x-data="adminBlogAi({
            enabled: @js((bool) ($aiBlogEnabled ?? false)),
            suggestUrl: @js(route('admin.posts.ai.suggest')),
            generateUrl: @js(route('admin.posts.ai.generate')),
        })"
        class="space-y-5"
    >
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

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-white/[.03]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold heading">Generate with AI</p>
                        <p class="mt-1 text-xs muted">Platform-aware drafts for servers, sites, deployments, staging, monitoring, and related SEO topics. Review before creating — nothing is saved until you submit.</p>
                    </div>
                    @unless($aiBlogEnabled ?? false)
                        <a href="{{ route('admin.ai') }}" class="button-secondary !px-3 !py-1.5 text-xs shrink-0">Enable in AI settings</a>
                    @endunless
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]" :class="! enabled && 'opacity-50 pointer-events-none'">
                    <label class="text-sm heading sm:col-span-1">Topic or keyword
                        <input class="field" type="text" x-model="keyword" maxlength="120" placeholder="e.g. Laravel zero-downtime deploys" :disabled="! enabled || busy">
                    </label>
                    <button type="button" class="button-secondary self-end" @click="suggestTopics" :disabled="! enabled || busy" x-text="suggesting ? 'Suggesting…' : 'Suggest topics'">Suggest topics</button>
                    <button type="button" class="button-primary self-end" @click="generateDraft()" :disabled="! enabled || busy" x-text="generating ? 'Generating…' : 'Generate draft'">Generate draft</button>
                </div>

                <p x-show="error" x-cloak class="mt-3 text-sm text-rose-600 dark:text-rose-300" x-text="error"></p>
                <p x-show="status" x-cloak class="mt-3 text-sm text-emerald-700 dark:text-emerald-300" x-text="status"></p>

                <div x-show="topics.length" x-cloak class="mt-4 space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide muted">Suggested topics — click to fill and generate</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(item, index) in topics" :key="index">
                            <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-xs heading transition hover:border-sky-300 dark:border-white/10 dark:bg-white/5 dark:hover:border-sky-500/40" @click="applyTopic(item)" :disabled="busy">
                                <span class="font-medium" x-text="item.title"></span>
                                <span class="mt-0.5 block muted" x-text="item.keyword || item.angle"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <p x-show="suggestedKeywords.length" x-cloak class="mt-3 text-xs muted">
                    Suggested keywords:
                    <span class="heading" x-text="suggestedKeywords.join(', ')"></span>
                </p>
            </div>

            <form method="POST" action="{{ route('admin.posts.store') }}" enctype="multipart/form-data" class="mt-5" x-ref="createForm">@csrf
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

    <script>
        function adminBlogAi({ enabled, suggestUrl, generateUrl }) {
            return {
                enabled,
                suggestUrl,
                generateUrl,
                editing: null,
                creating: false,
                keyword: '',
                topics: [],
                suggestedKeywords: [],
                error: '',
                status: '',
                suggesting: false,
                generating: false,
                get busy() {
                    return this.suggesting || this.generating;
                },
                csrf() {
                    return document.querySelector('meta[name=csrf-token]')?.content || '';
                },
                async suggestTopics() {
                    if (! this.enabled || this.busy) return;
                    this.error = '';
                    this.status = '';
                    this.suggesting = true;
                    try {
                        const response = await fetch(this.suggestUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf(),
                            },
                            body: JSON.stringify({ keyword: this.keyword || null }),
                        });
                        const data = await response.json();
                        if (! response.ok) {
                            this.error = data.message || 'Could not suggest topics.';
                            this.topics = [];
                            return;
                        }
                        this.topics = data.topics || [];
                        this.status = this.topics.length ? 'Pick a topic below, or generate a draft from your keyword.' : '';
                    } catch (e) {
                        this.error = 'Network error while suggesting topics.';
                    } finally {
                        this.suggesting = false;
                    }
                },
                applyTopic(item) {
                    this.keyword = item.keyword || item.title || this.keyword;
                    this.generateDraft(item.title || null);
                },
                async generateDraft(topic = null) {
                    if (! this.enabled || this.busy) return;
                    const topicText = typeof topic === 'string' && topic.trim() !== '' ? topic.trim() : null;
                    this.error = '';
                    this.status = '';
                    this.generating = true;
                    try {
                        const response = await fetch(this.generateUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf(),
                            },
                            body: JSON.stringify({
                                topic: topicText || (this.keyword || null),
                                keyword: this.keyword || null,
                            }),
                        });
                        const data = await response.json();
                        if (! response.ok) {
                            this.error = data.message || 'Could not generate a draft.';
                            return;
                        }
                        this.fillCreateForm(data.draft || {});
                        this.suggestedKeywords = data.draft?.suggested_keywords || [];
                        this.status = 'Draft filled into the form below. Review and create when ready.';
                        this.creating = true;
                    } catch (e) {
                        this.error = 'Network error while generating a draft.';
                    } finally {
                        this.generating = false;
                    }
                },
                fillCreateForm(draft) {
                    const form = this.$refs.createForm;
                    if (! form) return;
                    const set = (name, value) => {
                        const el = form.querySelector(`[name="${name}"]`);
                        if (el && value != null) el.value = value;
                    };
                    set('title', draft.title || '');
                    set('slug', '');
                    set('excerpt', draft.excerpt || '');
                    set('meta_title', draft.meta_title || '');
                    set('meta_description', draft.meta_description || '');
                    set('body', draft.body || '');
                },
            };
        }
    </script>
@endsection
