@extends('layouts.marketing')
@section('marketing')
@php
    $platform = $branding['name'] ?? app(\App\Services\SystemSettings::class)->branding()['name'];
    $support = app(\App\Services\SystemSettings::class)->get('support_email');
@endphp

<section class="relative overflow-hidden border-b border-slate-200 dark:border-white/10">
    <div class="landing-hero-wash opacity-60" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-5 py-20 lg:py-28">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">Contact</p>
        <h1 class="mt-4 max-w-3xl font-display text-4xl font-extrabold tracking-tight heading sm:text-5xl">Send us a message.</h1>
        <p class="mt-6 max-w-2xl text-lg muted">Ask about setup, pricing, or moving an existing server to {{ $platform }}. We read every message and reply ourselves.</p>
        @if($support)
            <p class="mt-4 text-sm muted">Or email <a class="font-semibold text-sky-600 hover:underline dark:text-sky-300" href="mailto:{{ $support }}">{{ $support }}</a>.</p>
        @endif
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 py-16 lg:py-20">
    <div class="grid gap-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:items-start">
        <div>
            @if($errors->any())
                <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
                    <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('contact.submit') }}" class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/[.03] sm:p-8">@csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Your name<input class="field" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>
                    <label class="text-sm heading">Email<input class="field" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
                </div>
                <label class="mt-4 block text-sm heading">Subject<input class="field" name="subject" value="{{ old('subject') }}" maxlength="160" placeholder="Optional"></label>
                <label class="mt-4 block text-sm heading">Message<textarea class="field min-h-40" name="body" required maxlength="5000" placeholder="What can we help with?">{{ old('body') }}</textarea></label>
                <button class="button-primary mt-6">Send message</button>
            </form>
        </div>

        <aside class="space-y-6">
            @foreach([
                ['Moving from another tool', 'Tell us your provider, Ubuntu version, and how you deploy today. We can help you attach existing servers.'],
                ['Plans and billing', 'Need more servers, sites, or seats? Ask before you hit a limit.'],
                ['Provisioning and deploys', "Need help connecting DigitalOcean, attaching a VPS by IP, or wiring auto-deploy webhooks? Ask — we use {$platform} the same way."],
            ] as [$topicTitle, $topicCopy])
                <div class="border-t border-slate-200 pt-5 dark:border-white/10">
                    <h2 class="font-display text-lg font-semibold heading">{{ $topicTitle }}</h2>
                    <p class="mt-2 text-sm muted">{{ $topicCopy }}</p>
                </div>
            @endforeach
        </aside>
    </div>
</section>
@endsection
