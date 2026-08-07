@extends('layouts.admin')
@section('admin-title', 'SEO')
@section('admin-description', 'Titles, meta tags, robots.txt, and the public sitemap.')
@section('admin')
    @php
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
        $systemSettings = app(\App\Services\SystemSettings::class);
        $seoDefaults = $systemSettings->seo();
        $marketingPages = $systemSettings->marketingSeoPages();
        $sitemapUrl = url('/sitemap.xml');
        $robotsUrl = url('/robots.txt');
    @endphp

    <div class="space-y-6">
        <form method="POST" action="{{ route('admin.settings.seo') }}" class="space-y-6">@csrf @method('PUT')

            <section class="panel">
                <h2 class="font-semibold heading">Defaults</h2>
                <p class="mt-1 text-sm muted">Fallback title, description, and robots meta used when a page or post does not set its own.</p>
                <div class="mt-5 max-w-3xl space-y-4">
                    <label class="block text-sm heading">Default title<input class="field" name="seo_default_title" value="{{ $value('seo_default_title', $seoDefaults['title']) }}" maxlength="180" placeholder="{{ $seoDefaults['title'] }}"></label>
                    <label class="block text-sm heading">Title template<input class="field font-mono text-xs" name="seo_title_template" value="{{ $value('seo_title_template', $seoDefaults['title_template']) }}" maxlength="180" placeholder="{page} | {site}"></label>
                    <p class="text-xs muted">Use <code>{page}</code> and <code>{site}</code>. Applied to marketing pages and blog posts without a custom title.</p>
                    <label class="block text-sm heading">Default meta description<textarea class="field" name="seo_default_description" rows="3" maxlength="320" placeholder="Shown in search results when a page does not set its own description.">{{ $value('seo_default_description', $seoDefaults['description']) }}</textarea></label>
                    <label class="block text-sm heading">Keywords<input class="field" name="seo_keywords" value="{{ $value('seo_keywords') }}" maxlength="255" placeholder="laravel hosting, wordpress vps, server panel"></label>
                    <label class="block text-sm heading">Open Graph image URL<input class="field" type="url" name="seo_og_image" value="{{ $value('seo_og_image') }}" maxlength="500" placeholder="https://cdn.example.com/og.png"></label>
                    <label class="block text-sm heading">Robots (meta)<input class="field" name="seo_robots" value="{{ $value('seo_robots', $seoDefaults['robots']) }}" maxlength="80" placeholder="index,follow"></label>
                </div>
            </section>

            <section class="panel">
                <h2 class="font-semibold heading">Homepage</h2>
                <p class="mt-1 text-sm muted">Optional overrides for the public home page. Blank fields keep the defaults above.</p>
                <div class="mt-5 max-w-3xl space-y-4">
                    <label class="block text-sm heading">Title<input class="field" name="seo_home_title" value="{{ $value('seo_home_title') }}" maxlength="180"></label>
                    <label class="block text-sm heading">Meta description<textarea class="field" name="seo_home_description" rows="2" maxlength="320">{{ $value('seo_home_description') }}</textarea></label>
                    <label class="block text-sm heading">Open Graph image URL<input class="field" type="url" name="seo_home_og_image" value="{{ $value('seo_home_og_image') }}" maxlength="500"></label>
                </div>
            </section>

            <section class="panel">
                <h2 class="font-semibold heading">Marketing pages</h2>
                <p class="mt-1 text-sm muted">Per-page title and description overrides. Leave blank to use the title template and default description.</p>
                <div class="mt-5 max-w-3xl space-y-6">
                    @foreach ($marketingPages as $key => $page)
                        @continue($key === 'home')
                        <div class="space-y-3 border-t border-black/5 pt-5 first:border-0 first:pt-0 dark:border-white/10">
                            <h3 class="text-sm font-semibold heading">{{ $page['label'] }}</h3>
                            <label class="block text-sm heading">Title<input class="field" name="seo_page_{{ $key }}_title" value="{{ $value("seo_page_{$key}_title") }}" maxlength="180" placeholder="{{ $systemSettings->applyTitleTemplate($page['label']) }}"></label>
                            <label class="block text-sm heading">Meta description<textarea class="field" name="seo_page_{{ $key }}_description" rows="2" maxlength="320">{{ $value("seo_page_{$key}_description") }}</textarea></label>
                            <label class="block text-sm heading">Open Graph image URL<input class="field" type="url" name="seo_page_{{ $key }}_og_image" value="{{ $value("seo_page_{$key}_og_image") }}" maxlength="500"></label>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <h2 class="font-semibold heading">Robots.txt</h2>
                <p class="mt-1 text-sm muted">Served at <a class="underline" href="{{ $robotsUrl }}" target="_blank" rel="noopener">{{ $robotsUrl }}</a>. Leave blank for the default allow-all body with a Sitemap line. Do not disallow <code>/sitemap.xml</code>.</p>
                <div class="mt-5 max-w-3xl space-y-4">
                    <label class="block text-sm heading">Full robots.txt body<textarea class="field font-mono text-xs" name="seo_robots_txt" rows="8" maxlength="10000" placeholder="User-agent: *&#10;Allow: /&#10;Sitemap: {{ $sitemapUrl }}">{{ $value('seo_robots_txt') }}</textarea></label>
                    <div class="rounded-lg bg-black/[0.03] p-4 text-xs dark:bg-white/[0.04]">
                        <p class="font-medium heading">Currently served content</p>
                        <pre class="mt-2 whitespace-pre-wrap font-mono muted">{{ trim($systemSettings->robotsTxt()) }}</pre>
                    </div>
                </div>
            </section>

            <section class="panel">
                <h2 class="font-semibold heading">Sitemap</h2>
                <p class="mt-1 text-sm muted">Public XML sitemap of marketing pages and published blog posts: <a class="underline" href="{{ $sitemapUrl }}" target="_blank" rel="noopener">{{ $sitemapUrl }}</a>. Draft and scheduled posts are omitted. When the public marketing site is disabled, the sitemap is empty.</p>
            </section>

            <button class="button-primary">Save SEO settings</button>
        </form>
    </div>
@endsection
