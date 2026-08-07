{{-- Shared by the create and edit forms so the two cannot drift apart. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <label class="text-sm heading">Title<input class="field" name="title" value="{{ old('title', $post?->title) }}" required maxlength="160"></label>
    <label class="text-sm heading">Slug<input class="field font-mono text-xs" name="slug" value="{{ old('slug', $post?->slug) }}" placeholder="Generated from the title"></label>
</div>

<label class="mt-4 block text-sm heading">Excerpt<textarea class="field" name="excerpt" rows="2" maxlength="500" placeholder="Shown on the blog index and in the card on the home page.">{{ old('excerpt', $post?->excerpt) }}</textarea></label>

<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <label class="text-sm heading">Meta title<input class="field" name="meta_title" value="{{ old('meta_title', $post?->meta_title) }}" maxlength="180" placeholder="Defaults to the post title with the SEO title template"></label>
    <label class="text-sm heading">Meta description<textarea class="field" name="meta_description" rows="2" maxlength="320" placeholder="Defaults to the excerpt, then the site default description">{{ old('meta_description', $post?->meta_description) }}</textarea></label>
</div>
<p class="mt-2 text-xs muted">Optional search / Open Graph overrides for this post only.</p>

<label class="mt-4 block text-sm heading">Body<textarea class="field min-h-72 leading-6" name="body" required>{{ old('body', $post?->body) }}</textarea></label>
<p class="mt-2 text-xs muted">Plain text. Blank lines separate paragraphs; HTML is escaped rather than rendered.</p>

<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <label class="text-sm heading">Publish at<input class="field" type="datetime-local" name="published_at" value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}"></label>
    <label class="text-sm heading">Cover image<input class="field !py-2" type="file" name="cover" accept="image/png,image/jpeg,image/webp"></label>
</div>
<p class="mt-2 text-xs muted">Leave the date empty to keep it a draft. A future date schedules it — the blog hides it until then.</p>
