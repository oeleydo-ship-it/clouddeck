@extends('layouts.marketing')
@section('marketing')
@php $platform = $branding['name'] ?? app(\App\Services\SystemSettings::class)->branding()['name']; @endphp

<section class="relative overflow-hidden border-b border-slate-200 dark:border-white/10">
    <div class="landing-hero-wash opacity-60" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-5 py-20 lg:py-28">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">Use cases</p>
        <h1 class="mt-4 max-w-3xl font-display text-4xl font-extrabold tracking-tight heading sm:text-5xl">Who {{ $platform }} is for.</h1>
        <p class="mt-6 max-w-2xl text-lg muted">Whether you run one app or many client sites, the same simple workflow applies.</p>
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 py-20">
    <div class="space-y-6">
        @foreach([
            ['01', 'Agencies', 'Host many client sites on a few servers. Each site keeps its own domain, SSL, database, and deploy branch — all in one panel.'],
            ['02', 'Solo developers', 'Skip learning every Linux detail under deadline. Connect a provider, set up a server, and ship the same day.'],
            ['03', 'SaaS products', 'Deploy often with rollback, queues, Horizon, and WebSockets. Keep workers and schedules running after every release.'],
            ['04', 'Staging sites', 'Spin up a second server or subdomain for testing. Use the same setup as production, then remove it when you are done.'],
            ['05', 'Servers you already have', "Import a droplet or attach any Ubuntu box by IP. Keep managing it from {$platform} without rebuilding from scratch."],
        ] as [$step, $caseTitle, $caseCopy])
            <article class="grid gap-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/[.03] md:grid-cols-[80px_minmax(0,1fr)] md:p-8">
                <span class="font-display text-4xl font-extrabold text-sky-100 dark:text-sky-900/40">{{ $step }}</span>
                <div>
                    <h2 class="font-display text-xl font-semibold heading">{{ $caseTitle }}</h2>
                    <p class="mt-3 text-sm leading-relaxed muted">{{ $caseCopy }}</p>
                </div>
            </article>
        @endforeach
    </div>
</section>

<section class="border-t border-slate-200 bg-slate-50 py-20 dark:border-white/10 dark:bg-white/[.02]">
    <div class="mx-auto max-w-3xl px-5 text-center">
        <h2 class="font-display text-3xl font-bold heading">Does this sound like you?</h2>
        <p class="mt-3 muted">Check the features, then start with a free plan and a server you already have — or a new one.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('register') }}" class="button-primary">Get started</a>
            <a href="{{ route('features') }}" class="button-secondary !bg-white !text-sky-700 dark:!bg-white/10 dark:!text-sky-200">Browse features</a>
        </div>
    </div>
</section>
@endsection
