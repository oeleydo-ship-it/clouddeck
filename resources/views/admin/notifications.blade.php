@extends('layouts.admin')
@section('admin-title', 'Notification center')
@section('admin-description', 'Control which client alert emails leave your SMTP server so provider quotas are not exhausted.')
@section('admin')
@php
    use App\Models\NotificationChannel;
@endphp

<div class="space-y-6">
    <section class="panel">
        <h2 class="font-semibold heading">Client email alerts</h2>
        <p class="mt-1 text-sm muted">
            Customers still see every event in the in-app notification bell. Turning an email off here only stops SMTP delivery — useful when providers rate-limit or when high-volume events (deploys, site added) burn quota.
            Configure SMTP under <a class="underline" href="{{ route('admin.mail') }}">SMTP</a>.
            Password resets, email verification, and team invitations always send.
        </p>

        <form method="POST" action="{{ route('admin.settings.notifications') }}" class="mt-5 space-y-6">@csrf @method('PUT')
            <label class="flex gap-2 text-sm heading">
                <input type="checkbox" name="client_email_notifications_enabled" value="1" @checked($clientEmailEnabled)>
                Send operational alert emails
            </label>
            <p class="text-xs muted -mt-4 ml-6">Master switch for the events below. Off = database only for all operational alerts.</p>

            <fieldset class="rounded-lg border border-slate-200 p-4 dark:border-white/10 {{ $clientEmailEnabled ? '' : 'opacity-60' }}">
                <legend class="px-1 text-sm font-medium heading">Events that may email clients</legend>
                <p class="mb-3 text-xs muted">Uncheck noisy types to keep SMTP for incidents that matter. Selections are kept even if the master switch is off.</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach(NotificationChannel::EVENTS as $key => $label)
                        <label class="flex gap-2 text-sm heading">
                            <input type="checkbox" name="events[]" value="{{ $key }}" @checked($eventToggles[$key] ?? true)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="rounded-lg border border-slate-200 p-4 dark:border-white/10">
                <h3 class="text-sm font-medium heading">Billing</h3>
                <label class="mt-3 flex gap-2 text-sm heading">
                    <input type="checkbox" name="client_email_billing_payment_failed" value="1" @checked($billingFailedAllowed)>
                    Email clients when a Stripe payment fails
                </label>
                <p class="mt-1 ml-6 text-xs muted">Independent of the operational master switch.</p>
            </div>

            <button class="button-primary">Save notification center</button>
        </form>
    </section>
</div>
@endsection
