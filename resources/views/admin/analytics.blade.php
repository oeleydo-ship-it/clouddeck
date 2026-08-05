@extends('layouts.admin')
@section('admin-title', 'Analytics')
@section('admin-description', 'Google Analytics measurement for public and console pages.')
@section('admin')
    @php
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">Google Analytics</h2>
            <p class="mt-1 text-sm muted">Loads gtag.js on every public and console page when a measurement ID is set.</p>
            <form method="POST" action="{{ route('admin.settings.analytics') }}" class="mt-5 max-w-xl space-y-4">@csrf @method('PUT')
                <label class="block text-sm heading">Measurement ID<input class="field font-mono text-xs" name="ga_measurement_id" value="{{ $value('ga_measurement_id') }}" maxlength="40" placeholder="G-XXXXXXXXXX"></label>
                <p class="text-xs muted">Use a GA4 ID such as <code>G-XXXXXXXXXX</code>. Leave blank to disable tracking.</p>
                <button class="button-primary mt-2">Save analytics</button>
            </form>
        </section>
    </div>
@endsection
