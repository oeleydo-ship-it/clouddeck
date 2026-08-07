@extends('layouts.admin')
@section('admin-title', 'Google Auth')
@section('admin-description', 'Let customers register and sign in with Google.')
@section('admin')
    @php
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
        $secretSaved = filled($settings->get('google_client_secret')?->value) || filled(config('services.google.client_secret'));
        $idSaved = filled($settings->get('google_client_id')?->value) || filled(config('services.google.client_id'));
        $enabledStored = $settings->get('google_auth_enabled')?->value;
        $enabledChecked = $enabledStored !== null
            ? $enabledStored === '1'
            : (filter_var(config('services.google.enabled', true), FILTER_VALIDATE_BOOLEAN) && ($idSaved || $secretSaved));
        $redirectUri = url('/auth/google/callback');
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">Google Sign-In</h2>
            <p class="mt-1 text-sm muted">Shows <strong class="heading">Continue with Google</strong> on login and register when enabled and both Client ID and Client Secret are available. The secret is encrypted and never shown back in this form.</p>

            <form method="POST" action="{{ route('admin.settings.google-auth') }}" class="mt-5 max-w-2xl space-y-4">@csrf @method('PUT')
                <label class="flex gap-2 text-sm heading">
                    <input type="checkbox" name="google_auth_enabled" value="1" @checked($enabledChecked)>
                    Enable Google sign-in
                </label>

                <label class="block text-sm heading">Google Client ID
                    <input class="field font-mono text-xs" type="text" name="google_client_id" value="{{ $value('google_client_id', config('services.google.client_id')) }}" autocomplete="off" placeholder="{{ $idSaved && ! $value('google_client_id') ? 'Using .env — paste to store in settings' : 'xxxx.apps.googleusercontent.com' }}">
                </label>

                <label class="block text-sm heading">Google Client Secret
                    <input class="field font-mono text-xs" type="password" name="google_client_secret" autocomplete="new-password" placeholder="{{ $secretSaved ? 'Saved — leave blank to keep it' : 'GOCSPX-...' }}">
                </label>

                <label class="block text-sm heading">Authorized redirect URI
                    <input class="field font-mono text-xs" type="text" value="{{ $redirectUri }}" readonly>
                </label>
                <p class="text-xs muted">Copy this exact URI into Google Cloud Console → APIs &amp; Services → Credentials → your OAuth client → Authorized redirect URIs.</p>

                <button class="button-primary mt-2">Save Google Auth</button>
            </form>
        </section>

        <section class="panel">
            <h2 class="font-semibold heading">Setup in Google Cloud</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm muted">
                <li>Open <a class="link-action" href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console → Credentials</a>.</li>
                <li>Create an OAuth 2.0 Client ID (application type <strong class="heading">Web application</strong>).</li>
                <li>Add the redirect URI shown above to Authorized redirect URIs.</li>
                <li>Paste the Client ID and Client Secret here, enable Google sign-in, and save.</li>
                <li>You can also set <code class="rounded bg-slate-100 px-1 dark:bg-white/10">GOOGLE_CLIENT_ID</code> / <code class="rounded bg-slate-100 px-1 dark:bg-white/10">GOOGLE_CLIENT_SECRET</code> in <code class="rounded bg-slate-100 px-1 dark:bg-white/10">.env</code>; admin settings override env when saved. <code class="rounded bg-slate-100 px-1 dark:bg-white/10">GOOGLE_AUTH_ENABLED</code> defaults to true when no admin toggle has been saved yet.</li>
            </ol>
        </section>
    </div>
@endsection
