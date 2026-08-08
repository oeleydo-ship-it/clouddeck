<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AuditLogger;
use App\Services\BlogPostGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AdminPostController extends Controller
{
    public function index(Request $request, BlogPostGenerator $generator): View
    {
        return view('admin.posts', [
            'aiBlogEnabled' => $generator->enabled(),
            'posts' => Post::with('author')
                ->when($request->query('search'), fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
                ->latest('created_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    /**
     * Suggest SEO-friendly topics grounded in the platform (JSON for the admin Alpine panel).
     */
    public function suggestTopics(Request $request, BlogPostGenerator $generator): JsonResponse
    {
        abort_unless($generator->enabled(), 404);

        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $topics = $generator->suggestTopics($data['keyword'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['topics' => $topics]);
    }

    /**
     * Generate a draft matching Post fields (plain-text body). Does not persist — admin reviews first.
     */
    public function generate(Request $request, BlogPostGenerator $generator): JsonResponse
    {
        abort_unless($generator->enabled(), 404);

        $data = $request->validate([
            'topic' => ['nullable', 'string', 'max:200'],
            'keyword' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $draft = $generator->generate($data['topic'] ?? null, $data['keyword'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['draft' => $draft]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $post = Post::create([...$data, 'user_id' => $request->user()->id]);
        $this->storeCover($request, $post);
        $audit->record($request, 'post.created', $post, [], ['title' => $post->title]);

        return back()->with('status', 'Post created.');
    }

    public function update(Request $request, Post $post, AuditLogger $audit): RedirectResponse
    {
        $old = $post->only(['title', 'slug', 'published_at']);
        $post->update($this->validated($request, $post));
        $this->storeCover($request, $post);
        $audit->record($request, 'post.updated', $post, $old, $post->only(['title', 'slug', 'published_at']));

        return back()->with('status', 'Post saved.');
    }

    /**
     * Publishing and unpublishing is its own action rather than a field on the form, so it
     * takes one click and cannot be done by accident while editing prose.
     */
    public function publish(Request $request, Post $post, AuditLogger $audit): RedirectResponse
    {
        $publishing = ! $post->isPublished();
        $post->update(['published_at' => $publishing ? now() : null]);
        $audit->record($request, $publishing ? 'post.published' : 'post.unpublished', $post, [], ['title' => $post->title]);

        return back()->with('status', $publishing ? 'Post published.' : 'Post moved back to draft.');
    }

    public function destroy(Request $request, Post $post, AuditLogger $audit): RedirectResponse
    {
        $audit->record($request, 'post.deleted', $post, ['title' => $post->title], []);
        $post->delete();

        return back()->with('status', 'Post deleted.');
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        // Derived before validation, not after: otherwise the unique rule never sees a
        // generated slug, and a second post sharing a title reaches the database and
        // fails there instead of coming back as a message on the field.
        $request->merge(['slug' => $request->filled('slug') ? $request->input('slug') : Str::slug((string) $request->input('title'))]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'alpha_dash', 'max:180', Rule::unique('posts', 'slug')->ignore($post)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'body' => ['required', 'string', 'max:200000'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        return [
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'body' => $data['body'],
            'published_at' => $data['published_at'] ?? $post?->published_at,
        ];
    }

    private function storeCover(Request $request, Post $post): void
    {
        if (! $request->hasFile('cover')) {
            return;
        }

        $previous = $post->cover_path;
        $post->update(['cover_path' => $request->file('cover')->store('posts', 'public')]);

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }
}
