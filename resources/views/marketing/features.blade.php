@extends('layouts.marketing')
@section('marketing')
@php
    $platform = $branding['name'] ?? app(\App\Services\SystemSettings::class)->branding()['name'];
    $managedServersEnabled = $managedServersEnabled ?? false;
@endphp

<section class="relative overflow-hidden border-b border-slate-200 dark:border-white/10">
    <div class="landing-hero-wash opacity-60" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-5 py-20 lg:py-28">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">Features</p>
        <h1 class="mt-4 max-w-3xl font-display text-4xl font-extrabold tracking-tight heading sm:text-5xl">Everything you need to host and ship updates.</h1>
        <p class="mt-6 max-w-2xl text-lg muted">
            @if($managedServersEnabled)
                {{ $platform }} provisions managed servers for you, or connects a VPS you already pay for — then deploys sites, SSL, workers, backups, staging, and monitoring from one panel.
            @else
                {{ $platform }} is a SaaS panel for provisioning your servers, deploying sites, SSL, workers, backups, staging, and monitoring — in one place.
            @endif
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="button-primary">Start free</a>
            <a href="{{ route('contact') }}" class="button-secondary !bg-white/90 !text-sky-700 dark:!bg-white/10 dark:!text-sky-200">Ask a question</a>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 py-20">
    <div class="grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
        @foreach(array_values(array_filter([
            $managedServersEnabled
                ? ['Managed servers', "We create and host the VPS on {$platform}'s cloud account. Choose a region and size, subscribe monthly, and deploy Laravel or WordPress — no DigitalOcean or Hetzner API key required."]
                : null,
            ['Create or connect servers', $managedServersEnabled
                ? 'Prefer bring-your-own? Spin up a droplet on your provider, or add an Ubuntu server by IP. We install the stack and lock down access.'
                : 'Spin up a DigitalOcean droplet, or add an Ubuntu server by IP. We install the stack and lock down access.'],
            ['Safe deployments', 'Each deploy builds in its own folder. It goes live only after success. Keep older releases for quick rollback.'],
            ['Auto deploy from Git', 'Wire GitHub, GitLab, or Bitbucket webhooks so pushes to your branch deploy automatically.'],
            ['SSL certificates', "Request Let's Encrypt SSL per site, force HTTPS, and track renewals."],
            ['Laravel and WordPress', 'Laravel gets Composer, migrations, queues, and Horizon. WordPress installs with a database and lasting uploads.'],
            ['Staging sites', 'Spin up a linked staging hostname, test safely, then promote to production when ready.'],
            ['Workers and cron jobs', 'Add queue workers and schedules in the panel. They sync to Supervisor and cron on the server.'],
            ['Databases', 'Create databases and users, link them to a site, and import or export with protected downloads.'],
            ['Backups', 'Schedule database backups or full server snapshots. Choose how long to keep them and confirm before restore.'],
            ['Monitoring and auto-heal', 'Track CPU, memory, disk, and site uptime. Restart down services automatically and alert Slack, Discord, or Telegram.'],
            ['Security detection', 'Scan managed servers for suspicious SSH, integrity, and site signals. Review incidents and block IPs manually from the console.'],
            ['DNS', 'Connect Cloudflare and manage A, AAAA, CNAME, and TXT records next to your sites.'],
            ['SSH keys', 'Generate a managed key for automation, or upload public keys for your team.'],
            ['Remote access', 'Edit allowed files, change PHP or Nginx settings, and run a limited set of commands from the browser.'],
            ['Teams and API', 'Share servers with roles, respect plan limits, and create API tokens that expire.'],
        ])) as [$featureTitle, $featureCopy])
            <article>
                <div class="mb-4 h-1 w-10 rounded-full bg-sky-500"></div>
                <h2 class="font-display text-lg font-semibold heading">{{ $featureTitle }}</h2>
                <p class="mt-2 text-sm leading-relaxed muted">{{ $featureCopy }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="border-t border-slate-200 bg-slate-50 py-20 dark:border-white/10 dark:bg-white/[.02]">
    <div class="mx-auto max-w-3xl px-5 text-center">
        <h2 class="font-display text-3xl font-bold heading">
            @if($managedServersEnabled)
                Start with a managed server — or your own.
            @else
                Start with one server.
            @endif
        </h2>
        <p class="mt-3 muted">
            @if($managedServersEnabled)
                Provision through {{ $platform }}, connect a provider or VPS, add a site, and grow with the same tools.
            @else
                Connect a provider or VPS, add a site, and grow from there with the same tools.
            @endif
        </p>
        <a href="{{ route('register') }}" class="button-primary mt-8 inline-flex">Create free account</a>
    </div>
</section>
@endsection
