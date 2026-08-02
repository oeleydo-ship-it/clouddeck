@extends('layouts.marketing')
@section('marketing')
@php $platform = app(\App\Services\SystemSettings::class)->branding()['name']; @endphp
<section class="mx-auto grid max-w-7xl items-center gap-16 px-5 py-20 lg:grid-cols-2">
    <div>
        <span class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-medium text-cyan-600 dark:border-cyan-400/20 dark:bg-cyan-400/10 dark:text-cyan-300">Laravel infrastructure, simplified</span>
        <h1 class="mt-6 text-4xl font-semibold leading-tight heading sm:text-5xl">Ship Laravel to your own servers, without the sysadmin work.</h1>
        <p class="mt-5 text-lg muted">{{ $platform }} provisions your server, configures Nginx and PHP-FPM, issues certificates, and deploys every release with zero downtime — on infrastructure you own.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="button-primary">Get started</a>
            <a href="{{ route('features') }}" class="button-secondary">See the features</a>
        </div>
        <p class="mt-4 text-xs muted">Bring your own provider account. Your servers stay yours.</p>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[.03]">
        <div class="flex gap-1.5"><span class="size-3 rounded-full bg-rose-400"></span><span class="size-3 rounded-full bg-amber-400"></span><span class="size-3 rounded-full bg-emerald-400"></span></div>
        <pre class="mt-4 overflow-x-auto rounded-xl bg-slate-900 p-4 font-mono text-xs leading-6 text-slate-300">[1/9] Cloning main into 20260802-release
[2/9] Installing Composer dependencies
[3/9] Linking persistent state
[4/9] Running database migrations
[5/9] Building frontend assets
[8/9] Switching the current release atomically
<span class="text-emerald-400">Release is live</span></pre>
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 pb-8">
    <div class="grid gap-4 md:grid-cols-3">
        @foreach([
            ['One-click provisioning', 'A queued pipeline installs and secures your whole Laravel stack — Nginx, PHP, Redis, and your database.'],
            ['Zero downtime releases', 'Atomic release symlinks, shared state, and an instant rollback to the release before it.'],
            ['Operated for you', 'Certificates, cron, queue workers, backups, and monitoring, all from one screen.'],
        ] as [$title, $copy])
            <article class="panel"><h2 class="font-semibold heading">{{ $title }}</h2><p class="mt-2 text-sm muted">{{ $copy }}</p></article>
        @endforeach
    </div>
</section>

@if($posts->isNotEmpty())
    <section class="mx-auto max-w-7xl px-5 py-16">
        <div class="flex items-end justify-between gap-4">
            <h2 class="text-2xl font-semibold heading">From the blog</h2>
            <a href="{{ route('blog') }}" class="text-sm font-medium text-cyan-600 dark:text-cyan-300">All posts →</a>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach($posts as $post)
                @include('blog.partials.card', ['post' => $post])
            @endforeach
        </div>
    </section>
@endif

<section class="mx-auto max-w-3xl px-5 py-16 text-center">
    <h2 class="text-2xl font-semibold heading">Ready to deploy?</h2>
    <p class="mt-3 muted">Connect a provider, point a domain, and push. Everything else is handled.</p>
    <a href="{{ route('register') }}" class="button-primary mt-6 inline-block">Create your account</a>
</section>
@endsection
