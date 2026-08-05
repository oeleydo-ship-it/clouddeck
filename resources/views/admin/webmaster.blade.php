@extends('layouts.admin')
@section('admin-title', 'Webmaster')
@section('admin-description', 'Search Console and site ownership verification.')
@section('admin')
    @php
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">Google Search Console</h2>
            <p class="mt-1 text-sm muted">Adds a verification meta tag on public pages so you can claim the property in Search Console.</p>
            <form method="POST" action="{{ route('admin.settings.webmaster') }}" class="mt-5 max-w-xl space-y-4">@csrf @method('PUT')
                <label class="block text-sm heading">Verification token<input class="field font-mono text-xs" name="gsc_verification" value="{{ $value('gsc_verification') }}" maxlength="120" placeholder="content value only"></label>
                <p class="text-xs muted">Paste only the <code>content</code> value from Google’s meta tag — not the full HTML.</p>
                <button class="button-primary mt-2">Save verification</button>
            </form>
        </section>
    </div>
@endsection
