@extends('layouts.marketing')
@section('marketing')
@php $platform = $branding['name'] ?? app(\App\Services\SystemSettings::class)->branding()['name']; @endphp

<section class="relative overflow-hidden border-b border-slate-200 dark:border-white/10">
    <div class="landing-hero-wash opacity-60" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-5 py-20 lg:py-28">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">About {{ $platform }}</p>
        <h1 class="mt-4 max-w-3xl font-display text-4xl font-extrabold tracking-tight heading sm:text-5xl">We help you run apps without living in the terminal.</h1>
        <p class="mt-6 max-w-2xl text-lg muted">{{ $platform }} is a SaaS panel for provisioning servers, deploying sites, and handling day-to-day tasks — on infrastructure you own and bill directly with your provider.</p>
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 py-20">
    <div class="grid gap-12 lg:grid-cols-3">
        @foreach([
            ['What we built', "{$platform} turns a fresh Ubuntu server into a place you can host Laravel or WordPress. It handles Nginx, PHP, databases, SSL, queues, backups, and deploys — and keeps a clear record of what ran."],
            ['Your servers stay yours', 'You connect your own cloud account or VPS. Servers stay in your name and on your bill. If you stop using the panel, the servers keep running.'],
            ['Safe by default', 'Passwords and keys are encrypted. Risky actions ask for confirmation. You can see job output when something fails, so you know what to fix.'],
        ] as [$sectionTitle, $sectionCopy])
            <article class="border-t border-sky-500/40 pt-6">
                <h2 class="font-display text-xl font-semibold heading">{{ $sectionTitle }}</h2>
                <p class="mt-3 text-sm leading-relaxed muted">{{ $sectionCopy }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="border-y border-slate-200 bg-slate-50 py-20 dark:border-white/10 dark:bg-white/[.02]">
    <div class="mx-auto max-w-7xl px-5">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">How we work</p>
            <h2 class="mt-3 font-display text-3xl font-bold heading">What matters to us</h2>
        </div>
        <div class="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['You own the servers', 'We never hide your VMs behind our own cloud account.'],
                ['Show real errors', 'When a job fails, you get the log — not silence.'],
                ['Easy rollback', 'Keep past releases so a bad deploy is quick to undo.'],
                ['Real people help', 'Support comes from people who know how the product works.'],
            ] as [$principleTitle, $principleCopy])
                <article>
                    <div class="mb-4 h-1 w-8 rounded-full bg-sky-500"></div>
                    <h3 class="font-display text-lg font-semibold heading">{{ $principleTitle }}</h3>
                    <p class="mt-2 text-sm muted">{{ $principleCopy }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="mx-auto max-w-3xl px-5 py-20 text-center">
    <h2 class="font-display text-3xl font-bold heading">Have a question?</h2>
    <p class="mt-3 muted">Tell us about your stack or plans. We will help you see if {{ $platform }} is a good fit.</p>
    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="{{ route('contact') }}" class="button-primary">Contact us</a>
        <a href="{{ route('register') }}" class="button-secondary !bg-white !text-sky-700 dark:!bg-white/10 dark:!text-sky-200">Create an account</a>
    </div>
</section>
@endsection
