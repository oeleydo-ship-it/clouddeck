<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\SystemSettings;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        $settings = app(SystemSettings::class);
        $seo = $settings->pageSeo('blog');

        return Inertia::render('Blog/Index', [
            'posts' => Post::published()
                ->with('author:id,name')
                ->latest('published_at')
                ->paginate(9)
                ->through(fn (Post $post) => $this->postCard($post)),
            'landing' => $settings->landing(),
            'title' => $seo['title'],
            'metaDescription' => $seo['description'],
            'ogImage' => $seo['og_image'],
        ]);
    }

    public function show(string $slug): Response
    {
        $post = Post::published()->with('author:id,name')->where('slug', $slug)->firstOrFail();
        $settings = app(SystemSettings::class);
        $seo = $settings->postSeo($post);

        return Inertia::render('Blog/Show', [
            'post' => [
                ...$this->postCard($post),
                'body' => $post->body,
                'reading_time' => $post->reading_time,
                'author' => $post->author ? ['name' => $post->author->name] : null,
            ],
            'related' => Post::published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get(['id', 'title', 'slug', 'excerpt', 'published_at', 'cover_path'])->map(fn (Post $item) => $this->postCard($item)),
            'landing' => $settings->landing(),
            'title' => $seo['title'],
            'metaDescription' => $seo['description'],
            'ogImage' => $seo['og_image'],
        ]);
    }

    /**
     * @return array{id: mixed, title: string, slug: string, excerpt: ?string, published_at: ?string, cover_url: ?string, author?: array{name: string}|null}
     */
    private function postCard(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toIso8601String(),
            'cover_url' => $post->cover_url,
            'author' => $post->author ? ['name' => $post->author->name] : null,
        ];
    }
}
