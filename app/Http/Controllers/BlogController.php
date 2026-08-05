<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\SystemSettings;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('blog.index', [
            'posts' => Post::published()->with('author')->latest('published_at')->paginate(9),
            'title' => 'Blog · '.app(SystemSettings::class)->branding()['name'],
        ]);
    }

    public function show(string $slug): View
    {
        // Scoped to published rather than resolved by binding, so a draft or a post
        // scheduled for next week is a 404 to the public even with the slug in hand.
        $post = Post::published()->with('author')->where('slug', $slug)->firstOrFail();

        return view('blog.show', [
            'post' => $post,
            'related' => Post::published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get(),
            'title' => $post->title.' · '.app(SystemSettings::class)->branding()['name'],
        ]);
    }
}
