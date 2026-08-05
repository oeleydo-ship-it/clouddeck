@extends('layouts.admin')
@section('admin-title', 'Pages')
@section('admin-description', 'Landing page copy for the public homepage.')
@section('admin')
    @php
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
        $landing = app(\App\Services\SystemSettings::class)->landing();
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">Landing page</h2>
            <p class="mt-1 text-sm muted">Edit the public homepage hero, getting-started blurb, and closing call to action. Leave a field blank to keep the built-in default.</p>
            <form method="POST" action="{{ route('admin.settings.landing') }}" class="mt-5 max-w-3xl space-y-4">@csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Hero eyebrow<input class="field" name="landing_hero_eyebrow" value="{{ $value('landing_hero_eyebrow', $landing['hero_eyebrow']) }}" maxlength="80"></label>
                    <label class="text-sm heading">Hero microcopy<input class="field" name="landing_hero_microcopy" value="{{ $value('landing_hero_microcopy', $landing['hero_microcopy']) }}" maxlength="120"></label>
                </div>
                <label class="block text-sm heading">Hero headline<input class="field" name="landing_hero_headline" value="{{ $value('landing_hero_headline', $landing['hero_headline']) }}" maxlength="160"></label>
                <label class="block text-sm heading">Hero supporting copy<textarea class="field" name="landing_hero_subcopy" rows="3" maxlength="600">{{ $value('landing_hero_subcopy', $landing['hero_subcopy']) }}</textarea></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Primary CTA label<input class="field" name="landing_hero_cta_primary" value="{{ $value('landing_hero_cta_primary', $landing['hero_cta_primary']) }}" maxlength="60"></label>
                    <label class="text-sm heading">Secondary CTA label<input class="field" name="landing_hero_cta_secondary" value="{{ $value('landing_hero_cta_secondary', $landing['hero_cta_secondary']) }}" maxlength="60"></label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Steps eyebrow<input class="field" name="landing_steps_eyebrow" value="{{ $value('landing_steps_eyebrow', $landing['steps_eyebrow']) }}" maxlength="80"></label>
                    <label class="text-sm heading">Steps headline<input class="field" name="landing_steps_headline" value="{{ $value('landing_steps_headline', $landing['steps_headline']) }}" maxlength="160"></label>
                </div>
                <label class="block text-sm heading">Steps supporting copy<textarea class="field" name="landing_steps_subcopy" rows="2" maxlength="400">{{ $value('landing_steps_subcopy', $landing['steps_subcopy']) }}</textarea></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Closing CTA headline<input class="field" name="landing_cta_headline" value="{{ $value('landing_cta_headline', $landing['cta_headline']) }}" maxlength="160"></label>
                    <label class="text-sm heading">Closing CTA button<input class="field" name="landing_cta_button" value="{{ $value('landing_cta_button', $landing['cta_button']) }}" maxlength="60"></label>
                </div>
                <label class="block text-sm heading">Closing CTA copy<textarea class="field" name="landing_cta_subcopy" rows="2" maxlength="400">{{ $value('landing_cta_subcopy', $landing['cta_subcopy']) }}</textarea></label>
                <button class="button-primary mt-2">Save landing page</button>
            </form>
        </section>
    </div>
@endsection
