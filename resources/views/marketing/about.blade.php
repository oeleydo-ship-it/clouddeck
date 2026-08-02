@extends('layouts.marketing')
@section('marketing')
@php $platform = app(\App\Services\SystemSettings::class)->branding()['name']; @endphp
<section class="mx-auto max-w-3xl px-5 py-20">
    <h1 class="text-4xl font-semibold heading">About us</h1>
    <p class="mt-6 text-lg muted">{{ $platform }} exists because deploying Laravel well is a solved problem that most teams still solve from scratch, one server at a time.</p>

    <div class="mt-10 space-y-8">
        <div>
            <h2 class="text-xl font-semibold heading">What we do</h2>
            <p class="mt-3 muted">We take a fresh server and turn it into a production Laravel host: Nginx and PHP-FPM tuned per site, Redis, your database, certificates that renew themselves, queue workers under Supervisor, scheduled tasks, backups, and monitoring. Then we deploy your releases atomically, so a bad one is a click away from being undone.</p>
        </div>
        <div>
            <h2 class="text-xl font-semibold heading">Your infrastructure stays yours</h2>
            <p class="mt-3 muted">You connect your own provider account. The servers are billed to you, live in your account, and keep running whether or not you keep using us. There is no lock-in to walk away from, only a control panel you stop opening.</p>
        </div>
        <div>
            <h2 class="text-xl font-semibold heading">How we build</h2>
            <p class="mt-3 muted">Every remote action is a queued job with recorded output, so when something fails you get the actual error rather than a spinner. Credentials are encrypted at rest, and destructive operations ask before they act.</p>
        </div>
    </div>

    <div class="panel mt-12">
        <h2 class="font-semibold heading">Want to talk?</h2>
        <p class="mt-2 text-sm muted">We answer our own support.</p>
        <a href="{{ route('contact') }}" class="button-primary mt-4 inline-block">Contact us</a>
    </div>
</section>
@endsection
