@extends('layouts.admin')
@section('admin-title', 'AI guide')
@section('admin-description', 'In-console chat helper powered by OpenAI.')
@section('admin')
    @php
        $branding = app(\App\Services\SystemSettings::class)->branding();
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
        $aiKeySaved = filled($settings->get('openai_api_key')?->value);
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">AI platform guide</h2>
            <p class="mt-1 text-sm muted">Shows a chat helper in the console so customers can ask how to provision, deploy, and operate. Uses OpenAI; the API key is encrypted and never shown back in the form.</p>
            <form method="POST" action="{{ route('admin.settings.ai') }}" class="mt-5 max-w-3xl space-y-4">@csrf @method('PUT')
                <label class="flex gap-2 text-sm heading"><input type="checkbox" name="ai_guide_enabled" value="1" @checked(($settings->get('ai_guide_enabled')?->value ?? '0') === '1')>Enable AI guide for signed-in users</label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">OpenAI API key<input class="field font-mono text-xs" type="password" name="openai_api_key" autocomplete="new-password" placeholder="{{ $aiKeySaved ? 'Saved — leave blank to keep it' : 'sk-...' }}"></label>
                    <label class="text-sm heading">Model<input class="field" name="openai_model" value="{{ $value('openai_model', 'gpt-4o-mini') }}" maxlength="80" placeholder="gpt-4o-mini"></label>
                </div>
                <label class="block text-sm heading">System prompt<textarea class="field font-mono text-xs" name="ai_guide_system_prompt" rows="6" maxlength="4000" placeholder="Leave blank to use the built-in {{ $branding['name'] }} guide prompt.">{{ $value('ai_guide_system_prompt') }}</textarea></label>
                <p class="text-xs muted">The guide only appears when enabled <em>and</em> an API key is saved. Replies are throttled and stay inside the signed-in console.</p>
                <button class="button-primary mt-2">Save AI guide</button>
            </form>
        </section>
    </div>
@endsection
