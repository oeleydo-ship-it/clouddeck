@extends('layouts.admin')
@section('admin-title', 'Settings')
@section('admin-description', 'Platform-wide configuration applied to every customer.')
@section('admin')
    @php
        $branding = app(\App\Services\SystemSettings::class)->branding();
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
        $passwordSaved = filled($settings->get('mail_password')?->value);
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">General information</h2>
            <p class="mt-1 text-sm muted">Shown to customers across the platform and in outgoing email.</p>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-5 max-w-2xl">@csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Platform name<input class="field" name="platform_name" value="{{ $value('platform_name', config('app.name')) }}" maxlength="60"></label>
                    <label class="text-sm heading">Support email<input class="field" type="email" name="support_email" value="{{ $value('support_email') }}" placeholder="support@example.com"></label>
                </div>
                <label class="mt-4 block text-sm heading">Maintenance banner<textarea class="field" name="maintenance_banner" rows="2" placeholder="Shown to every signed-in customer while set.">{{ $value('maintenance_banner') }}</textarea></label>
                <label class="mt-4 flex gap-2 text-sm heading"><input type="checkbox" name="registration_enabled" value="1" @checked($settings->get('registration_enabled')?->value !== '0')>Public registration enabled</label>
                <label class="mt-3 flex gap-2 text-sm heading"><input type="checkbox" name="email_verification_required" value="1" @checked(($settings->get('email_verification_required')?->value ?? (config('clouddeck.email_verification_required') ? '1' : '0')) === '1')>Require email verification</label>
                <p class="mt-2 text-xs muted">Verification needs working mail below. With it off, new registrations are marked verified immediately and existing unverified accounts are let through.</p>
                <button class="button-primary mt-5">Save general settings</button>
            </form>
        </section>

        <section class="panel">
            <h2 class="font-semibold heading">Logo</h2>
            <p class="mt-1 text-sm muted">Replaces the mark in the header. PNG, JPG, WEBP, or SVG up to 1&nbsp;MB.</p>
            <div class="mt-5 flex flex-wrap items-center gap-6">
                <div class="grid size-16 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5">
                    @if($branding['logo_url'])
                        <img src="{{ $branding['logo_url'] }}" alt="Current logo" class="size-14 rounded-xl object-contain">
                    @else
                        <span class="grid size-14 place-items-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 text-xl font-bold text-white">{{ Str::upper(Str::substr($branding['name'], 0, 1)) }}</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.settings.logo') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">@csrf
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" required class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:file:bg-white/10 dark:file:text-slate-200">
                    <button class="button-primary">Upload</button>
                </form>
                @if($branding['logo_url'])
                    <form method="POST" action="{{ route('admin.settings.logo.destroy') }}">@csrf @method('DELETE')<button class="button-secondary !text-rose-600 dark:!text-rose-300">Remove</button></form>
                @endif
            </div>
        </section>

        <section class="panel">
            <h2 class="font-semibold heading">Outgoing mail (SMTP)</h2>
            <p class="mt-1 text-sm muted">
                Used for verification, password resets, invitations, and deployment notifications.
                Works with any SMTP provider — for <strong>Resend</strong>, use host <code>smtp.resend.com</code>, port <code>587</code>, username <code>resend</code>, and your API key as the password.
            </p>
            <form method="POST" action="{{ route('admin.settings.mail') }}" class="mt-5 max-w-2xl">@csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Host<input class="field" name="mail_host" value="{{ $value('mail_host') }}" placeholder="smtp.resend.com"></label>
                    <label class="text-sm heading">Port<input class="field" type="number" name="mail_port" value="{{ $value('mail_port', '587') }}"></label>
                    <label class="text-sm heading">Encryption<select class="field" name="mail_encryption">@foreach(['tls' => 'TLS (recommended)', 'ssl' => 'SSL', 'none' => 'None'] as $option => $label)<option value="{{ $option }}" @selected($value('mail_encryption', 'tls') === $option)>{{ $label }}</option>@endforeach</select></label>
                    <label class="text-sm heading">Username<input class="field" name="mail_username" value="{{ $value('mail_username') }}" autocomplete="off" placeholder="resend"></label>
                    <label class="text-sm heading sm:col-span-2">Password or API key<input class="field font-mono text-xs" type="password" name="mail_password" autocomplete="new-password" placeholder="{{ $passwordSaved ? 'Saved — leave blank to keep it' : 're_...' }}"></label>
                    <label class="text-sm heading">From address<input class="field" type="email" name="mail_from_address" value="{{ $value('mail_from_address') }}" placeholder="noreply@example.com"></label>
                    <label class="text-sm heading">From name<input class="field" name="mail_from_name" value="{{ $value('mail_from_name', $branding['name']) }}"></label>
                </div>
                <button class="button-primary mt-5">Save mail settings</button>
            </form>

            <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="mt-6 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-5 dark:border-white/5">@csrf
                <label class="text-sm heading">Send a test message to<input class="field" type="email" name="test_email" value="{{ auth()->user()->email }}"></label>
                <button class="button-secondary">Send test</button>
                <p class="w-full text-xs muted">Confirm delivery before relying on password resets — wrong credentials here are otherwise invisible until a customer is locked out.</p>
            </form>
        </section>
    </div>
@endsection
