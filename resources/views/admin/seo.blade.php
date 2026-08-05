@extends('layouts.admin')
@section('admin-title', 'SEO')
@section('admin-description', 'Default meta tags for public pages.')
@section('admin')
    @php
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
        $seoDefaults = app(\App\Services\SystemSettings::class)->seo();
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">Search engine defaults</h2>
            <p class="mt-1 text-sm muted">Fallback meta tags used when a public page does not set its own description or robots directive.</p>
            <form method="POST" action="{{ route('admin.settings.seo') }}" class="mt-5 max-w-3xl space-y-4">@csrf @method('PUT')
                <label class="block text-sm heading">Default meta description<textarea class="field" name="seo_default_description" rows="3" maxlength="320" placeholder="Shown in search results when a page does not set its own description.">{{ $value('seo_default_description', $seoDefaults['description']) }}</textarea></label>
                <label class="block text-sm heading">Keywords<input class="field" name="seo_keywords" value="{{ $value('seo_keywords') }}" maxlength="255" placeholder="laravel hosting, wordpress vps, server panel"></label>
                <label class="block text-sm heading">Open Graph image URL<input class="field" type="url" name="seo_og_image" value="{{ $value('seo_og_image') }}" maxlength="500" placeholder="https://cdn.example.com/og.png"></label>
                <label class="block text-sm heading">Robots<input class="field" name="seo_robots" value="{{ $value('seo_robots', $seoDefaults['robots']) }}" maxlength="80" placeholder="index,follow"></label>
                <button class="button-primary mt-2">Save SEO settings</button>
            </form>
        </section>
    </div>
@endsection
