@extends('layouts.marketing')
@section('marketing')
@php
    $platform = $branding['name'] ?? app(\App\Services\SystemSettings::class)->branding()['name'];
    $landing = $landing ?? app(\App\Services\SystemSettings::class)->landing();
    $providers = ['DigitalOcean', 'Hetzner', 'Vultr', 'Linode', 'AWS', 'UpCloud', 'Contabo', 'Custom VPS'];
@endphp

{{-- Hero: two columns — copy left, product plane right --}}
<section class="landing-hero relative overflow-hidden border-b border-sky-900/10">
    <div class="landing-hero-wash" aria-hidden="true"></div>
    <div class="landing-hero-grid" aria-hidden="true"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-5 py-14 md:grid-cols-2 md:gap-8 md:py-16 lg:gap-12 lg:py-20">
        <div class="landing-fade-up relative z-10 max-w-xl">
            <p class="font-display text-sm font-semibold uppercase tracking-[0.22em] text-sky-700 dark:text-sky-300">{{ $landing['hero_eyebrow'] }}</p>
            <h1 class="mt-5 font-display text-4xl font-extrabold leading-[1.05] tracking-tight text-slate-950 sm:text-5xl dark:text-white">
                {{ $landing['hero_headline'] }}
            </h1>
            <p class="mt-5 max-w-md text-lg leading-relaxed text-slate-600 dark:text-slate-300">
                {{ $landing['hero_subcopy'] }}
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ route('register') }}" class="button-primary !px-7 !py-3.5">{{ $landing['hero_cta_primary'] }}</a>
                <a href="#how-it-works" class="button-secondary !bg-white/90 !text-sky-700 hover:!bg-white dark:!bg-white/10 dark:!text-sky-200">{{ $landing['hero_cta_secondary'] }}</a>
            </div>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ $landing['hero_microcopy'] }}</p>
        </div>

        <div class="landing-fade-up landing-fade-up-delay relative z-10 mx-auto w-full max-w-[460px] md:-my-5 md:origin-right md:scale-[0.88] md:justify-self-end lg:-my-2 lg:scale-95" aria-hidden="true">
            <div class="landing-product overflow-hidden rounded-2xl border border-slate-800/80 bg-[#0f1720] shadow-[0_20px_60px_rgba(14,165,233,0.16)] md:rounded-3xl">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-3">
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full bg-emerald-400"></span>
                        <span class="font-mono text-xs text-slate-300">production-api</span>
                    </div>
                    <span class="rounded-md bg-emerald-400/15 px-2 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-emerald-300">Active</span>
                </div>
                <div class="grid gap-0 sm:grid-cols-[132px_minmax(0,1fr)]">
                    <div class="hidden border-r border-white/10 p-4 sm:block">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">Menu</p>
                        <ul class="mt-3 space-y-2 text-xs text-slate-400">
                            <li class="rounded-md bg-sky-500/20 px-2 py-1.5 font-medium text-sky-200">Servers</li>
                            <li class="px-2 py-1.5">Sites</li>
                            <li class="px-2 py-1.5">Deployments</li>
                            <li class="px-2 py-1.5">Monitoring</li>
                        </ul>
                    </div>
                    <div class="p-4 sm:p-5">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <p class="text-xs text-slate-500">DigitalOcean · Amsterdam · 4 vCPU</p>
                                <p class="mt-1 font-display text-lg font-semibold text-white">production-api</p>
                            </div>
                            <p class="font-mono text-xs text-sky-300">203.0.113.42</p>
                        </div>
                        <div class="mt-5 overflow-hidden rounded-xl border border-white/10 bg-black/40">
                            <div class="border-b border-white/10 px-3 py-2 font-mono text-[10px] text-slate-500">deploy.log · main · 9f3a1c2</div>
                            <pre class="landing-terminal overflow-hidden p-3 font-mono text-[11px] leading-5 text-slate-300">$ {{ Str::lower($platform) }} deploy
→ composer install --no-dev -o
→ npm run build
→ php artisan migrate --force
→ switch live release
<span class="text-emerald-400">✓ Live in 42s</span></pre>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            @foreach([['CPU', '18%'], ['Memory', '41%'], ['Disk', '29%']] as [$metricLabel, $metricValue])
                                <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                                    <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ $metricLabel }}</p>
                                    <p class="mt-1 font-display text-sm font-semibold text-white">{{ $metricValue }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Providers --}}
<section class="border-b border-slate-200 bg-white py-10 dark:border-white/10 dark:bg-slate-950">
    <div class="mx-auto max-w-7xl px-5">
        <p class="text-center text-xs font-semibold uppercase tracking-[0.18em] muted">Use the provider or VPS you already pay for</p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-x-8 gap-y-3">
            @foreach($providers as $provider)
                <span class="font-display text-sm font-semibold text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200">{{ $provider }}</span>
            @endforeach
        </div>
    </div>
</section>

{{-- Steps --}}
<section id="how-it-works" class="mx-auto max-w-7xl px-5 py-20 lg:py-28">
    <div class="max-w-2xl">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">{{ $landing['steps_eyebrow'] }}</p>
        <h2 class="mt-3 font-display text-3xl font-bold tracking-tight heading sm:text-4xl">{{ $landing['steps_headline'] }}</h2>
        <p class="mt-4 text-lg muted">{{ $landing['steps_subcopy'] }}</p>
    </div>

    <ol class="mt-14 grid gap-10 lg:grid-cols-3">
        @foreach([
            ['01', 'Add a server', "Create one on DigitalOcean, or connect an Ubuntu VPS with its IP. {$platform} installs Nginx, PHP, Redis, and your database."],
            ['02', 'Add a site', 'Pick Laravel or WordPress, set the domain, and link your Git repo if you use one.'],
            ['03', 'Deploy', 'Click deploy or push code. If something goes wrong, you can roll back to an earlier release.'],
        ] as [$step, $stepTitle, $stepCopy])
            <li class="relative">
                <span class="font-display text-5xl font-extrabold text-sky-100 dark:text-sky-900/50">{{ $step }}</span>
                <h3 class="mt-3 font-display text-xl font-semibold heading">{{ $stepTitle }}</h3>
                <p class="mt-3 text-sm leading-relaxed muted">{{ $stepCopy }}</p>
            </li>
        @endforeach
    </ol>
</section>

{{-- What you can do --}}
<section class="border-y border-slate-200 bg-slate-50 py-20 dark:border-white/10 dark:bg-white/[.02] lg:py-28">
    <div class="mx-auto max-w-7xl px-5">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">What you can do</p>
            <h2 class="mt-3 font-display text-3xl font-bold tracking-tight heading sm:text-4xl">Everyday tools in one dashboard.</h2>
            <p class="mt-4 text-lg muted">Manage servers, sites, SSL, backups, and alerts without jumping between many tools.</p>
        </div>

        <div class="mt-14 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['Laravel apps', 'Install packages, run migrations, queues, the scheduler, Horizon, and workers from the panel.'],
                ['WordPress sites', 'Install WordPress with a database and SSL. Uploads and plugins stay safe between deploys.'],
                ['Free SSL', "Get Let's Encrypt certificates and keep HTTPS on. Renewals are handled for you."],
                ['DNS records', 'Connect Cloudflare and edit A, CNAME, and TXT records next to your sites.'],
                ['SSH keys', "Create a managed key for {$platform}, or upload keys your team already uses."],
                ['Queues and cron', 'Add queue workers and scheduled jobs in the UI. They sync to the server for you.'],
                ['Server health', 'Watch CPU, memory, and disk. Get alerts by email, Slack, Discord, or Telegram.'],
                ['Backups', 'Schedule database backups and provider snapshots. Restores ask for confirmation first.'],
                ['Remote tools', 'Edit files, change PHP or Nginx settings, and run safe commands from the browser.'],
        ] as [$featureTitle, $featureCopy])
                <article>
                    <div class="mb-4 h-1 w-10 rounded-full bg-sky-500"></div>
                    <h3 class="font-display text-lg font-semibold heading">{{ $featureTitle }}</h3>
                    <p class="mt-2 text-sm leading-relaxed muted">{{ $featureCopy }}</p>
                </article>
            @endforeach
        </div>

        <div class="mt-14 flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="button-primary">Start free</a>
            <a href="{{ route('features') }}" class="button-secondary !bg-white !text-sky-700 dark:!bg-white/10 dark:!text-sky-200">See all features</a>
        </div>
    </div>
</section>

{{-- Why --}}
<section class="mx-auto max-w-7xl px-5 py-20 lg:py-28">
    <div class="max-w-2xl">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">Why {{ $platform }}</p>
        <h2 class="mt-3 font-display text-3xl font-bold tracking-tight heading sm:text-4xl">Built for people who want less server stress.</h2>
    </div>
    <div class="mt-14 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
        @foreach([
            ['Your servers stay yours', 'We connect to your provider or VPS. You keep the account, the IP, and the billing.'],
            ['Clear status', 'See what is running, what failed, and the log output — not a blank spinner.'],
            ['Ready out of the box', 'Nginx, PHP, MySQL or PostgreSQL, Redis, Supervisor, Node, Composer, firewall, and Certbot.'],
            ['Safer deploys', 'New code builds in a separate folder, then goes live only when it succeeds. Old releases stay for rollback.'],
            ['Teams and plans', 'Invite teammates, set limits, and upgrade when you grow. Stripe billing is optional.'],
            ['API access', 'Create tokens with limited permissions to automate servers, sites, and deploys.'],
        ] as [$reasonTitle, $reasonCopy])
            <article class="border-t border-slate-200 pt-6 dark:border-white/10">
                <h3 class="font-display text-lg font-semibold heading">{{ $reasonTitle }}</h3>
                <p class="mt-2 text-sm leading-relaxed muted">{{ $reasonCopy }}</p>
            </article>
        @endforeach
    </div>
</section>

{{-- Pricing is rendered directly from active, public plans managed by super admins. --}}
<section id="pricing" class="border-y border-slate-200 bg-slate-50 py-20 dark:border-white/10 dark:bg-white/[.02] lg:py-28"
         x-data="{ annual: true }">
    @php
        // When platform managed servers are off, hide those quotas from public pricing so
        // the page only advertises BYOS infrastructure customers can actually use.
        $managedServersEnabled = $managedServersEnabled ?? false;
        $limitLabels = $managedServersEnabled
            ? [
                'servers' => 'BYOS servers',
                'managed_servers' => 'managed servers',
                'sites' => 'BYOS sites',
                'managed_sites' => 'managed sites',
                'databases' => 'databases',
                'api_tokens' => 'API tokens',
                'teams' => 'teams',
                'team_members' => 'team members',
            ]
            : [
                'servers' => 'servers',
                'sites' => 'sites',
                'databases' => 'databases',
                'api_tokens' => 'API tokens',
                'teams' => 'teams',
                'team_members' => 'team members',
            ];
        // managed_servers is a quota above (when enabled); never repeat it as a feature row.
        $featureLabels = collect(\App\Services\FeatureManager::catalog())->except('managed_servers')->all();
        $featuredPlan = $plans->firstWhere('slug', 'pro') ?? $plans->get(1);
        $money = fn (int $cents, string $currency) => $cents === 0
            ? 'Free'
            : Str::upper($currency).' '.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    @endphp

    <div class="mx-auto max-w-7xl px-5">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">Pricing</p>
                <h2 class="mt-3 font-display text-3xl font-bold tracking-tight heading sm:text-4xl">Simple plans. Clear limits.</h2>
                <p class="mt-4 text-lg muted">You pay {{ $platform }} for the panel. Your cloud provider bills you for the servers.</p>
            </div>

            <div class="inline-flex w-fit rounded-xl border border-slate-200 bg-white p-1 dark:border-white/10 dark:bg-slate-900" aria-label="Billing cycle">
                <button type="button" @click="annual = false"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="!annual ? 'bg-sky-500 text-white' : 'text-slate-500 dark:text-slate-400'">Monthly</button>
                <button type="button" @click="annual = true"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="annual ? 'bg-sky-500 text-white' : 'text-slate-500 dark:text-slate-400'">
                    Annual
                    <span class="ml-1 text-[10px]" :class="annual ? 'text-sky-100' : 'text-emerald-600 dark:text-emerald-300'">save more</span>
                </button>
            </div>
        </div>

        @if($plans->isEmpty())
            <div class="mt-12 rounded-2xl border border-dashed border-slate-300 px-6 py-14 text-center dark:border-white/10">
                <p class="font-display text-lg font-semibold heading">Plans coming soon</p>
                <p class="mt-2 text-sm muted">Public pricing will show here once an admin publishes a plan.</p>
                <a href="{{ route('contact') }}" class="button-primary mt-6 inline-flex">Contact us</a>
            </div>
        @else
            <div class="mt-12 grid items-stretch gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($plans as $publicPlan)
                    @php
                        $featured = $featuredPlan?->id === $publicPlan->id && $plans->count() > 1;
                        $annualMonthly = $publicPlan->yearly_price > 0
                            ? (int) round($publicPlan->yearly_price / 12)
                            : $publicPlan->monthly_price;
                        $annualSaving = $publicPlan->monthly_price > 0 && $publicPlan->yearly_price > 0
                            ? max(0, (int) round((1 - ($publicPlan->yearly_price / ($publicPlan->monthly_price * 12))) * 100))
                            : 0;
                    @endphp
                    <article @class([
                        'relative flex flex-col rounded-2xl border bg-white p-6 dark:bg-slate-950 sm:p-8',
                        'border-sky-500 shadow-xl shadow-sky-500/10 dark:border-sky-400' => $featured,
                        'border-slate-200 dark:border-white/10' => ! $featured,
                    ])>
                        @if($featured)
                            <span class="absolute -top-3 left-6 rounded-full bg-sky-500 px-3 py-1 text-xs font-bold text-white">Popular</span>
                        @endif

                        <div>
                            <h3 class="font-display text-xl font-bold heading">{{ $publicPlan->name }}</h3>
                            <p class="mt-2 text-sm muted">
                                {{ $publicPlan->monthly_price === 0 ? 'Good for trying the product with a small setup.' : ($featured ? 'More room for active apps and small teams.' : 'For larger setups and more people.') }}
                            </p>
                        </div>

                        <div class="mt-7 min-h-20">
                            <div x-show="!annual" class="flex items-end gap-1">
                                <span class="font-display text-4xl font-extrabold heading">{{ $money($publicPlan->monthly_price, $publicPlan->currency) }}</span>
                                @if($publicPlan->monthly_price)<span class="mb-1 text-sm muted">/month</span>@endif
                            </div>
                            <div x-cloak x-show="annual" class="flex flex-wrap items-end gap-x-1 gap-y-2">
                                <span class="font-display text-4xl font-extrabold heading">{{ $money($annualMonthly, $publicPlan->currency) }}</span>
                                @if($annualMonthly)<span class="mb-1 text-sm muted">/month</span>@endif
                                @if($annualSaving > 0)<span class="mb-1 ml-2 rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Save {{ $annualSaving }}%</span>@endif
                                @if($publicPlan->yearly_price > 0)
                                    <p class="w-full text-xs muted">{{ $money($publicPlan->yearly_price, $publicPlan->currency) }} billed yearly</p>
                                @endif
                            </div>
                        </div>

                        <ul class="mt-7 grow space-y-3 border-t border-slate-100 pt-6 text-sm dark:border-white/10">
                            @foreach($limitLabels as $limitKey => $limitLabel)
                                @if(array_key_exists($limitKey, $publicPlan->limits ?? []))
                                    @php $limitValue = (int) ($publicPlan->limits[$limitKey] ?? 0); @endphp
                                    <li class="flex items-center gap-3">
                                        <svg class="size-4 shrink-0 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                                        <span class="heading"><strong>{{ $limitValue < 0 ? 'Unlimited' : $limitValue }}</strong> {{ $limitLabel }}</span>
                                    </li>
                                @endif
                            @endforeach
                            @foreach($featureLabels as $featureKey => $featureLabel)
                                <li class="flex items-center gap-3 {{ ($publicPlan->features[$featureKey] ?? false) ? '' : 'opacity-40' }}">
                                    <svg class="size-4 shrink-0 {{ ($publicPlan->features[$featureKey] ?? false) ? 'text-sky-500' : 'text-slate-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        @if($publicPlan->features[$featureKey] ?? false)<path d="M20 6 9 17l-5-5"/>@else<path d="M18 6 6 18M6 6l12 12"/>@endif
                                    </svg>
                                    <span @class(['heading' => $publicPlan->features[$featureKey] ?? false, 'muted line-through' => ! ($publicPlan->features[$featureKey] ?? false)])>{{ $featureLabel }}</span>
                                </li>
                            @endforeach
                            <li class="flex items-center gap-3">
                                <svg class="size-4 shrink-0 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                                <span class="heading">Unlimited deployments</span>
                            </li>
                        </ul>

                        <a href="{{ route('register') }}" @class([
                            'mt-8 w-full justify-center',
                            'button-primary' => $featured,
                            'button-secondary !bg-sky-50 !text-sky-700 hover:!bg-sky-100 dark:!bg-sky-400/10 dark:!text-sky-200' => ! $featured,
                        ])>
                            {{ $publicPlan->monthly_price === 0 ? 'Start free' : 'Choose '.$publicPlan->name }}
                        </a>
                    </article>
                @endforeach
            </div>
        @endif

        <p class="mt-8 text-center text-sm muted">All plans include provider links, deploy history, SSH keys, and SSL. Admins can change prices and limits anytime.</p>
    </div>
</section>

@if($posts->isNotEmpty())
    <section class="border-t border-slate-200 bg-white py-20 dark:border-white/10 dark:bg-slate-950">
        <div class="mx-auto max-w-7xl px-5">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-300">Blog</p>
                    <h2 class="mt-3 font-display text-3xl font-bold heading">Latest posts</h2>
                </div>
                <a href="{{ route('blog') }}" class="text-sm font-semibold text-sky-600 hover:underline dark:text-sky-300">View all →</a>
            </div>
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach($posts as $post)
                    @include('blog.partials.card', ['post' => $post])
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Final CTA --}}
<section class="relative overflow-hidden border-t border-sky-900/10">
    <div class="landing-cta-wash absolute inset-0" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-3xl px-5 py-24 text-center">
        <p class="font-display text-sm font-semibold uppercase tracking-[0.22em] text-sky-800 dark:text-sky-200">{{ $platform }}</p>
        <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl dark:text-white">{{ $landing['cta_headline'] }}</h2>
        <p class="mx-auto mt-4 max-w-xl text-lg text-slate-600 dark:text-slate-300">{{ $landing['cta_subcopy'] }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('register') }}" class="button-primary !px-7 !py-3.5">{{ $landing['cta_button'] }}</a>
            <a href="{{ route('features') }}" class="button-secondary !bg-white/90 !text-sky-700 dark:!bg-white/10 dark:!text-sky-200">Browse features</a>
        </div>
    </div>
</section>
@endsection
