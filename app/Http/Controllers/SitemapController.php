<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\SystemSettings;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(SystemSettings $settings): Response
    {
        $urls = [];

        if ($settings->publicSiteEnabled()) {
            foreach ($settings->marketingSeoPages() as $page) {
                $urls[] = [
                    'loc' => route($page['route']),
                    'lastmod' => null,
                ];
            }

            foreach (Post::published()->latest('published_at')->get(['slug', 'published_at', 'updated_at']) as $post) {
                $urls[] = [
                    'loc' => route('blog.show', $post->slug),
                    'lastmod' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
                ];
            }
        }

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
