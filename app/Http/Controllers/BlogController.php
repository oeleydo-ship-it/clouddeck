<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\SystemSettings;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $seo = app(SystemSettings::class)->pageSeo('blog');

        return view('blog.index', [
            'posts' => Post::published()->with('author')->latest('published_at')->paginate(9),
            'title' => $seo['title'],
            'metaDescription' => $seo['description'],
            'ogImage' => $seo['og_image'],
        ]);
    }

    public function show(string $slug): View
    {
        // Scoped to published rather than resolved by binding, so a draft or a post
        // scheduled for next week is a 404 to the public even with the slug in hand.
        $post = Post::published()->with('author')->where('slug', $slug)->firstOrFail();
        $seo = app(SystemSettings::class)->postSeo($post);

        return view('blog.show', [
            'post' => $post,
            'related' => Post::published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get(),
            'title' => $seo['title'],
            'metaDescription' => $seo['description'],
            'ogImage' => $seo['og_image'],
        ]);
    }
}
