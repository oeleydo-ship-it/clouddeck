@extends('layouts.admin')
@section('admin-title', 'Insert code')
@section('admin-description', 'Custom HTML and scripts for chat widgets, analytics, and similar embeds.')
@section('admin')
    @php
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
        $onMarketing = ($settings->get('insert_code_on_marketing')?->value ?? '1') === '1';
        $onConsole = ($settings->get('insert_code_on_console')?->value ?? '0') === '1';
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">Custom head &amp; body snippets</h2>
            <p class="mt-1 text-sm muted">Paste Intercom, Crisp, Tawk, custom analytics, or any other HTML/JS. Snippets are output as raw markup for visitors — only super administrators can edit them. Prefer the body field for widgets that say “place before <code>&lt;/body&gt;</code>”; head works for async loaders.</p>

            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100">
                Scripts run in visitors’ browsers. Do not paste untrusted code. This is intentional for platform operators; scripts are not stripped.
            </div>
            <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-100">
                Iframe-based chat widgets need the <em>widget host</em> to allow framing. If the widget is served from a site on this platform and you only see a blank box where the chat should be, open that site’s Remote → Nginx settings and enable <strong>Allow embedding in iframes on other sites</strong>, then apply.
            </div>

            <form method="POST" action="{{ route('admin.settings.insert-code') }}" class="mt-5 max-w-3xl space-y-4">@csrf @method('PUT')
                <label class="block text-sm heading">Before <code>&lt;/head&gt;</code>
                    <textarea class="field mt-1 font-mono text-xs" name="insert_code_head" rows="8" maxlength="50000" placeholder="<!-- e.g. chat widget loader -->">{{ $value('insert_code_head') }}</textarea>
                </label>
                <label class="block text-sm heading">Before <code>&lt;/body&gt;</code>
                    <textarea class="field mt-1 font-mono text-xs" name="insert_code_body" rows="8" maxlength="50000" placeholder="<!-- e.g. widget bootstrap script -->">{{ $value('insert_code_body') }}</textarea>
                </label>
                <div class="space-y-2">
                    <label class="flex gap-2 text-sm heading"><input type="checkbox" name="insert_code_on_marketing" value="1" @checked($onMarketing)>Inject on marketing and other public pages</label>
                    <label class="flex gap-2 text-sm heading"><input type="checkbox" name="insert_code_on_console" value="1" @checked($onConsole)>Inject in the signed-in console (including Admin)</label>
                </div>
                <p class="text-xs muted">Defaults: public/marketing on, console off. Clearing a textarea and saving removes that snippet.</p>
                <button class="button-primary mt-2">Save insert code</button>
            </form>
        </section>
    </div>
@endsection
