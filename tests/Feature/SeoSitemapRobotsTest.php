<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoSitemapRobotsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::create([...[
            'title' => 'Deploying Laravel without downtime',
            'slug' => 'deploying-laravel-without-downtime',
            'excerpt' => 'How atomic releases work.',
            'body' => "First paragraph.\n\nSecond paragraph.",
            'published_at' => now()->subDay(),
        ], ...$attributes]);
    }

    public function test_homepage_uses_seo_title_and_description_from_settings(): void
    {
        $this->markInstalled();
        $settings = app(SystemSettings::class);
        $settings->put('seo_home_title', 'Home Title Override', 'string', true);
        $settings->put('seo_home_description', 'Home description override for search.', 'string', true);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Home Title Override</title>', false)
            ->assertSee('content="Home description override for search."', false);
    }

    public function test_marketing_page_title_template_and_page_overrides(): void
    {
        $this->markInstalled();
        $settings = app(SystemSettings::class);
        $settings->put('platform_name', 'Acme Panel', 'string', true);
        $settings->put('seo_title_template', '{page} · {site}', 'string', true);

        $this->get('/about')
            ->assertOk()
            ->assertSee('<title>About · Acme Panel</title>', false);

        $settings->put('seo_page_about_title', 'About Acme custom', 'string', true);
        $settings->put('seo_page_about_description', 'About page description.', 'string', true);

        $this->get('/about')
            ->assertOk()
            ->assertSee('<title>About Acme custom</title>', false)
            ->assertSee('content="About page description."', false);
    }

    public function test_blog_post_uses_meta_fields_with_excerpt_fallback(): void
    {
        $this->markInstalled();
        $post = $this->makePost([
            'meta_title' => 'Custom post meta title',
            'meta_description' => 'Custom post meta description.',
        ]);

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('<title>Custom post meta title</title>', false)
            ->assertSee('content="Custom post meta description."', false);

        $fallback = $this->makePost([
            'title' => 'Fallback title post',
            'slug' => 'fallback-title-post',
            'excerpt' => 'Excerpt becomes the description.',
            'meta_title' => null,
            'meta_description' => null,
        ]);
        app(SystemSettings::class)->put('platform_name', 'Uplary', 'string', true);
        app(SystemSettings::class)->put('seo_title_template', '{page} | {site}', 'string', true);

        $this->get('/blog/'.$fallback->slug)
            ->assertOk()
            ->assertSee('<title>Fallback title post | Uplary</title>', false)
            ->assertSee('content="Excerpt becomes the description."', false);
    }

    public function test_admin_can_save_extended_seo_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/settings/seo', [
            'seo_default_title' => 'Uplary Cloud',
            'seo_title_template' => '{page} | {site}',
            'seo_default_description' => 'Default description.',
            'seo_home_title' => 'Welcome home',
            'seo_page_about_title' => 'About us',
            'seo_robots_txt' => "User-agent: *\nAllow: /\nSitemap: https://example.test/sitemap.xml\n",
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertSame('Uplary Cloud', $settings->seo()['title']);
        $this->assertSame('Welcome home', $settings->pageSeo('home')['title']);
        $this->assertSame('About us', $settings->pageSeo('about')['title']);
        $this->assertStringContainsString('Sitemap: https://example.test/sitemap.xml', $settings->robotsTxt());
    }

    public function test_sitemap_includes_published_posts_and_excludes_drafts(): void
    {
        $this->markInstalled();
        $published = $this->makePost();
        $this->makePost(['title' => 'Draft post', 'slug' => 'draft-post', 'published_at' => null]);

        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();

        $this->assertStringContainsString(route('home'), $xml);
        $this->assertStringContainsString(route('about'), $xml);
        $this->assertStringContainsString(route('blog.show', $published->slug), $xml);
        $this->assertStringNotContainsString(route('blog.show', 'draft-post'), $xml);
        $this->assertStringContainsString('<urlset', $xml);
    }

    public function test_sitemap_is_empty_when_public_site_is_disabled(): void
    {
        $this->markInstalled();
        $this->makePost();
        app(SystemSettings::class)->put('public_site_enabled', '0', 'boolean', true);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('<loc>', $xml);
        $this->assertStringContainsString('<urlset', $xml);
    }

    public function test_robots_txt_includes_sitemap_line_by_default(): void
    {
        $this->markInstalled();

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Allow: /', $body);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
        $this->assertStringNotContainsString('Disallow: /sitemap.xml', $body);
    }

    public function test_robots_txt_serves_custom_admin_content(): void
    {
        $this->markInstalled();
        app(SystemSettings::class)->put(
            'seo_robots_txt',
            "User-agent: *\nDisallow: /admin\nAllow: /\nSitemap: ".url('/sitemap.xml')."\n",
            'string',
            true
        );

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
    }

    public function test_admin_can_save_post_meta_fields(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/posts', [
            'title' => 'SEO post',
            'body' => 'Body copy.',
            'meta_title' => 'SEO meta title',
            'meta_description' => 'SEO meta description.',
        ])->assertSessionHas('status');

        $post = Post::where('slug', 'seo-post')->sole();
        $this->assertSame('SEO meta title', $post->meta_title);
        $this->assertSame('SEO meta description.', $post->meta_description);
    }
}
