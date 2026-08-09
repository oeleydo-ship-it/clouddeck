@extends('layouts.admin')
@section('admin-title', 'SMTP')
@section('admin-description', 'Outgoing mail for verification, password resets, invitations, and notifications.')
@section('admin')
@php
    $branding = app(\App\Services\SystemSettings::class)->branding();
    $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
    $passwordSaved = filled($settings->get('mail_password')?->value);
@endphp

<div class="space-y-6">
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
