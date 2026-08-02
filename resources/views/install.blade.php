@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-2xl px-5 py-16">
    <div class="text-center">
        <h1 class="text-3xl font-semibold heading">Install CloudDeck</h1>
        <p class="mt-2 text-sm muted">One-time setup. Creates your administrator account, the default plans, and your system settings.</p>
    </div>

    @if($errors->any())
        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
            <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('install') }}" class="mt-8 space-y-6">@csrf
        <section class="panel">
            <h2 class="font-semibold heading">Administrator</h2>
            <p class="mt-1 text-sm muted">This account gets full access to every server, site, and customer on this instance.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm heading">Name<input class="field" name="name" value="{{ old('name') }}" required autofocus></label>
                <label class="text-sm heading">Email<input class="field" type="email" name="email" value="{{ old('email') }}" required></label>
                <label class="text-sm heading">Password<input class="field" type="password" name="password" required autocomplete="new-password"></label>
                <label class="text-sm heading">Confirm password<input class="field" type="password" name="password_confirmation" required autocomplete="new-password"></label>
            </div>
            <p class="mt-3 text-xs muted">At least 12 characters, with letters and numbers.</p>
        </section>

        <section class="panel">
            <h2 class="font-semibold heading">Settings</h2>
            <div class="mt-5 space-y-4">
                <label class="block text-sm heading">Support email<input class="field" type="email" name="support_email" value="{{ old('support_email') }}" placeholder="support@example.com"></label>
                <label class="flex gap-2 text-sm heading"><input type="checkbox" name="registration_enabled" value="1" @checked(old('registration_enabled', true))>Allow public registration</label>
                <label class="flex gap-2 text-sm heading"><input type="checkbox" name="email_verification_required" value="1" @checked(old('email_verification_required', true))>Require email verification</label>
                <p class="text-xs muted">Verification needs working mail credentials. Leave it off until mail is configured, or you will lock new customers out.</p>
            </div>
        </section>

        <section class="panel">
            <h2 class="font-semibold heading">Payment gateway <span class="text-xs font-normal muted">— optional</span></h2>
            <p class="mt-1 text-sm muted">Stripe keys are encrypted at rest and override any value in <code>.env</code>. Leave blank to run on manual billing; you can add them later from the admin settings.</p>
            <div class="mt-5 space-y-4">
                <label class="block text-sm heading">Secret key<input class="field font-mono text-xs" name="stripe_secret" value="{{ old('stripe_secret') }}" placeholder="sk_live_..." autocomplete="off"></label>
                <label class="block text-sm heading">Webhook signing secret<input class="field font-mono text-xs" name="stripe_webhook_secret" value="{{ old('stripe_webhook_secret') }}" placeholder="whsec_..." autocomplete="off"></label>
            </div>
        </section>

        <button class="button-primary w-full">Install CloudDeck</button>
    </form>
</div>
@endsection
