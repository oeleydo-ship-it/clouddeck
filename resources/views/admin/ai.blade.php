@extends('layouts.admin')
@section('admin-title', 'AI')
@section('admin-description', 'OpenAI-compatible providers for the console guide and blog draft generation.')
@section('admin')
    @php
        $system = app(\App\Services\SystemSettings::class);
        $branding = $system->branding();
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
        $aiKeySaved = filled($settings->get('openai_api_key')?->value);
        $provider = $value('ai_provider', \App\Services\SystemSettings::AI_PROVIDER_OPENAI);
        $modelDefault = $system->defaultAiModel($provider);
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">AI connection</h2>
            <p class="mt-1 text-sm muted">One encrypted API key powers both the customer guide and admin blog drafts. The key is never shown back in the form. Providers use the OpenAI-compatible Chat Completions API.</p>
            <form method="POST" action="{{ route('admin.settings.ai') }}" class="mt-5 max-w-3xl space-y-4" x-data="{
                provider: @js($provider),
                model: @js($value('openai_model', $modelDefault)),
                onProviderChange() {
                    if (this.provider === 'moonshot' && (this.model === '' || this.model === 'gpt-4o-mini')) {
                        this.model = 'kimi-k3';
                    }
                    if (this.provider === 'openai' && (this.model === '' || this.model.startsWith('kimi-'))) {
                        this.model = 'gpt-4o-mini';
                    }
                }
            }">@csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Provider
                        <select class="field" name="ai_provider" x-model="provider" @change="onProviderChange()">
                            <option value="openai">OpenAI</option>
                            <option value="moonshot">Moonshot (Kimi)</option>
                        </select>
                    </label>
                    <label class="text-sm heading">
                        <span x-text="provider === 'moonshot' ? 'Moonshot API key' : 'OpenAI API key'"></span>
                        <input class="field font-mono text-xs" type="password" name="openai_api_key" autocomplete="new-password" placeholder="{{ $aiKeySaved ? 'Saved — leave blank to keep it' : 'sk-...' }}">
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Model
                        <input class="field" name="openai_model" x-model="model" maxlength="80" :placeholder="provider === 'moonshot' ? 'kimi-k3' : 'gpt-4o-mini'">
                    </label>
                    <label class="text-sm heading">Base URL <span class="font-normal muted">(optional)</span>
                        <input class="field font-mono text-xs" name="ai_base_url" value="{{ $value('ai_base_url') }}" maxlength="255" placeholder="Leave blank for provider default" inputmode="url">
                    </label>
                </div>
                <p class="text-xs muted" x-show="provider === 'openai'" x-cloak>
                    Default model <code class="rounded bg-slate-100 px-1 dark:bg-white/10">gpt-4o-mini</code>; default base URL <code class="rounded bg-slate-100 px-1 dark:bg-white/10">https://api.openai.com/v1</code>.
                </p>
                <p class="text-xs muted" x-show="provider === 'moonshot'" x-cloak>
                    Default model <code class="rounded bg-slate-100 px-1 dark:bg-white/10">kimi-k3</code> (also <code class="rounded bg-slate-100 px-1 dark:bg-white/10">kimi-k2.6</code>, <code class="rounded bg-slate-100 px-1 dark:bg-white/10">kimi-k2.5</code>). International default base URL <code class="rounded bg-slate-100 px-1 dark:bg-white/10">https://api.moonshot.ai/v1</code>; China region use <code class="rounded bg-slate-100 px-1 dark:bg-white/10">https://api.moonshot.cn/v1</code>. Keys from <a href="https://platform.kimi.ai/console/api-keys" class="underline" target="_blank" rel="noopener">platform.kimi.ai</a>.
                </p>

                <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-white/5">
                    <h3 class="text-sm font-semibold heading">AI platform guide</h3>
                    <p class="text-sm muted">Shows a chat helper in the console so customers can ask how to provision, deploy, and operate.</p>
                    <label class="flex gap-2 text-sm heading"><input type="checkbox" name="ai_guide_enabled" value="1" @checked(($settings->get('ai_guide_enabled')?->value ?? '0') === '1')>Enable AI guide for signed-in users</label>
                    <label class="block text-sm heading">System prompt<textarea class="field font-mono text-xs" name="ai_guide_system_prompt" rows="6" maxlength="4000" placeholder="Leave blank to use the built-in {{ $branding['name'] }} guide prompt.">{{ $value('ai_guide_system_prompt') }}</textarea></label>
                </div>

                <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-white/5">
                    <h3 class="text-sm font-semibold heading">AI blog drafts</h3>
                    <p class="text-sm muted">Lets superadmins generate platform-aware draft posts from <a href="{{ route('admin.posts') }}" class="underline">Admin → Blog</a>. Drafts are never published automatically. Writing is steered toward a natural human voice.</p>
                    <label class="flex gap-2 text-sm heading"><input type="checkbox" name="ai_blog_enabled" value="1" @checked(($settings->get('ai_blog_enabled')?->value ?? '0') === '1')>Enable AI blog generation for superadmins</label>

                    @php
                        $avoidValue = $value('ai_blog_avoid_phrases');
                        $avoidPlaceholder = implode("\n", \App\Services\SystemSettings::defaultAiBlogAvoidPhrases());
                    @endphp
                    <label class="block text-sm heading">Phrases to avoid <span class="font-normal muted">(one per line)</span>
                        <textarea class="field font-mono text-xs" name="ai_blog_avoid_phrases" rows="8" maxlength="4000" placeholder="{{ $avoidPlaceholder }}">{{ $avoidValue }}</textarea>
                    </label>
                    <p class="text-xs muted">Leave blank to use the built-in list (includes “digital world”, “In today’s fast-paced digital landscape”, and similar AI clichés). Saved lines replace the defaults. Matching phrases are also scrubbed from drafts after generation.</p>

                    <label class="block text-sm heading">Words to weave in <span class="font-normal muted">(optional, one per line)</span>
                        <textarea class="field font-mono text-xs" name="ai_blog_insert_words" rows="4" maxlength="2000" placeholder="managed VPS&#10;zero-downtime deploy&#10;staging promote">{{ $value('ai_blog_insert_words') }}</textarea>
                    </label>
                    <p class="text-xs muted">House phrases the draft should use naturally when they fit — not stuffed into every paragraph.</p>

                    <label class="block text-sm heading">Style notes <span class="font-normal muted">(optional)</span>
                        <textarea class="field text-sm" name="ai_blog_style_notes" rows="4" maxlength="2000" placeholder="e.g. Write for solo Laravel freelancers. Prefer short sentences. Mention GitLab and Bitbucket when talking about git.">{{ $value('ai_blog_style_notes') }}</textarea>
                    </label>
                </div>

                <p class="text-xs muted">Each feature only works when its toggle is on <em>and</em> an API key is saved. Guide and blog endpoints are throttled.</p>
                <button class="button-primary mt-2">Save AI settings</button>
            </form>
        </section>
    </div>
@endsection
