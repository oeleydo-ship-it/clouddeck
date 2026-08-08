@extends('layouts.app')
@section('content')
@php
    $sections = [
        ['id' => 'getting-started', 'label' => 'Getting started'],
        ['id' => 'whats-new', 'label' => "What's new"],
        ['id' => 'providers', 'label' => 'Providers & IPs'],
        ['id' => 'ssh-keys', 'label' => 'SSH keys'],
        ['id' => 'provisioning', 'label' => 'Provisioning'],
        ['id' => 'sites', 'label' => 'Adding a site'],
        ['id' => 'deployments', 'label' => 'Deployments'],
        ['id' => 'ssl', 'label' => 'SSL certificates'],
        ['id' => 'databases', 'label' => 'Databases'],
        ['id' => 'workers', 'label' => 'Workers & cron'],
        ['id' => 'monitoring', 'label' => 'Monitoring'],
        ['id' => 'security-detection', 'label' => 'Security detection'],
        ['id' => 'notifications', 'label' => 'Notifications'],
        ['id' => 'firewall', 'label' => 'Firewall'],
        ['id' => 'backups', 'label' => 'Backups'],
        ['id' => 'staging', 'label' => 'Staging sites'],
        ['id' => 'remote', 'label' => 'Remote management'],
        ['id' => 'maintenance', 'label' => 'Server maintenance'],
        ['id' => 'dns', 'label' => 'DNS'],
        ['id' => 'teams', 'label' => 'Teams & API'],
        ['id' => 'plans', 'label' => 'Plans & billing'],
        ['id' => 'password', 'label' => 'Account security'],
        ['id' => 'support', 'label' => 'Getting help'],
    ];
@endphp
<div class="app-main !max-w-6xl" x-data="{ active: 'getting-started' }"
     @scroll.window.throttle.100ms="
        const ids = {{ Js::from(collect($sections)->pluck('id')) }};
        let current = ids[0];
        for (const id of ids) {
            const el = document.getElementById(id);
            if (el && el.getBoundingClientRect().top <= 120) current = id;
        }
        active = current;
     ">
    <p class="page-eyebrow">Help center</p>
    <h1 class="page-title">Support &amp; documentation</h1>
    <p class="page-subtitle">{{ $branding['name'] }} is a SaaS control plane. Connect your cloud account or VPS, auto-provision servers, deploy Laravel and WordPress sites, and operate them from one dashboard — your infrastructure stays on your provider bill.</p>

    <div class="mt-10 grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)]">
        <nav class="lg:sticky lg:top-6 lg:self-start" aria-label="Documentation">
            <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.12em] muted">On this page</p>
            <ul class="space-y-1">
                @foreach($sections as $section)
                    <li>
                        <a href="#{{ $section['id'] }}"
                           @click="active = '{{ $section['id'] }}'"
                           class="block rounded-lg px-3 py-2 text-sm transition"
                           :class="active === '{{ $section['id'] }}' ? 'bg-[#0058bc]/10 font-semibold text-[#0058bc] dark:bg-cyan-400/10 dark:text-cyan-300' : 'muted hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white'">
                            {{ $section['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('contact') }}" class="button-secondary mt-6 w-full justify-center text-sm">Contact support</a>
        </nav>

        <div class="docs-body min-w-0 space-y-10">
            {{-- Getting started --}}
            <section id="getting-started" class="panel scroll-mt-8">
                <h2 class="section-title">Getting started</h2>
                <p class="mt-3 text-sm muted">Sign up at {{ $branding['name'] }}, then work through this loop. You do not install the panel yourself — you manage servers and sites through the hosted product.</p>

                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm muted">
                    <li><strong class="heading">Create an account</strong> — register, verify email if required, and land on the dashboard.</li>
                    <li><strong class="heading">Connect a provider or VPS</strong> — DigitalOcean for API provisioning, or attach any Ubuntu box by IP.</li>
                    <li><strong class="heading">Add a managed SSH key</strong> — required to create and bootstrap new servers.</li>
                    <li><strong class="heading">Provision or import a server</strong> — {{ $branding['name'] }} installs Nginx, PHP, MySQL/PostgreSQL, Redis, Supervisor, and related services.</li>
                    <li><strong class="heading">Create a site</strong> — Laravel from Git, or WordPress with a managed database.</li>
                    <li><strong class="heading">Deploy and operate</strong> — SSL, workers, monitoring, backups, and optional staging from the same console.</li>
                </ol>

                <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm muted dark:bg-white/5">
                    <p><strong class="heading">What you own:</strong> cloud accounts, VMs, and domains stay in your name. {{ $branding['name'] }} orchestrates SSH and provider APIs; stopping the panel does not delete your servers.</p>
                    <p class="mt-2">Need a nudge while working? Use the floating <strong class="heading">AI guide</strong> (bottom-right) when a superadmin has enabled it. Use <strong class="heading">View website</strong> in the sidebar to open the public marketing site in a new tab.</p>
                </div>
            </section>

            {{-- What's new --}}
            <section id="whats-new" class="panel scroll-mt-8">
                <h2 class="section-title">What's new</h2>
                <p class="mt-3 text-sm muted">Recent console updates for customers. Jump to a section for the full walkthrough.</p>
                <ul class="mt-4 list-inside list-disc space-y-2 text-sm muted">
                    <li><a class="link-action" href="#security-detection">Security detection</a> — sidebar <strong class="heading">Security</strong> page with live scan status (<strong class="heading">Queued</strong> → <strong class="heading">Scanning…</strong> → last scan / Failed), scheduled checks every five minutes, and configurable per-rule detection settings (enabled by default).</li>
                    <li><a class="link-action" href="#firewall">Firewall</a> — manage per-server UFW allow/deny rules from the sidebar.</li>
                    <li><a class="link-action" href="#notifications">Notifications</a> — fleet incidents (including security) plus account-wide email recipients (backup failure and security incident alerts).</li>
                    <li><a class="link-action" href="#teams">Teams</a> — invite Edit / Resend / Delete, plus Viewer, Operator, Admin, and Owner privileges.</li>
                    <li><a class="link-action" href="#workers">Cron presets</a> — one-click Laravel <code>schedule:run</code> on site and server Cron tabs.</li>
                    <li><a class="link-action" href="#sites">PHP 8.5</a> — available when creating sites; new servers install 8.5 (plus 8.4/8.3/8.2) with 8.5 as the default.</li>
                    <li><a class="link-action" href="#backups">Backups</a> — schedule, run now, restore/download databases, DigitalOcean snapshots, storage disk, and BYO limits.</li>
                    <li><a class="link-action" href="#maintenance">Server maintenance</a> — software hardening, Ubuntu package updates, and major release upgrades from Services.</li>
                    <li><a class="link-action" href="#password">Google sign-in</a> — use <strong class="heading">Continue with Google</strong> on login/register when the platform enables it.</li>
                </ul>
            </section>

            {{-- Providers & IPs --}}
            <section id="providers" class="panel scroll-mt-8">
                <h2 class="section-title">Adding providers and IPs</h2>
                <p class="mt-3 text-sm muted">Open <a class="link-action" href="{{ route('cloud-accounts') }}">Providers</a> in the sidebar to connect a cloud account. Tokens are validated, then encrypted at rest.</p>

                <h3 class="mt-6 text-sm font-semibold heading">API providers (DigitalOcean)</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Choose <strong class="heading">DigitalOcean</strong> and give the connection a name (for example, Production).</li>
                    <li>Paste an API token with read and write access to droplets and SSH keys.</li>
                    <li>Click <strong class="heading">Validate and connect</strong>. {{ $branding['name'] }} checks the token before storing it.</li>
                    <li>Use <strong class="heading">Discover and connect servers</strong> to import existing droplets, or provision new ones from Servers.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Connect by IP (AWS, Hetzner, Vultr, Linode, OCI, UpCloud, Contabo)</h3>
                <p class="mt-2 text-sm muted">These providers are attached by IP — {{ $branding['name'] }} cannot create VMs through their APIs yet.</p>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Select the provider and enter a connection name.</li>
                    <li>Enter the server’s public IP and SSH port (default 22).</li>
                    <li>Continue to SSH setup, then use <strong class="heading">Add a server by IP</strong> (or <a class="link-action" href="{{ route('servers.custom') }}">Servers → Attach existing</a>).</li>
                    <li>On the server as <code>root</code>, run the authorisation command shown in the console so {{ $branding['name'] }}’s public key is trusted.</li>
                    <li>Submit the IP, port, name, and Ubuntu version (22.04 or 24.04). {{ $branding['name'] }} verifies SSH access and boots the stack.</li>
                </ol>
                <p class="mt-3 rounded-xl bg-slate-50 p-4 text-sm muted dark:bg-white/5">The target must be a fresh Ubuntu 22.04 or 24.04 box with root SSH. {{ $branding['name'] }} installs Nginx, PHP-FPM, Redis, a database engine, and related services during bootstrap.</p>
            </section>

            {{-- SSH keys --}}
            <section id="ssh-keys" class="panel scroll-mt-8">
                <h2 class="section-title">Adding SSH keys</h2>
                <p class="mt-3 text-sm muted">Managed keys are required to provision new cloud servers. Open <a class="link-action" href="{{ route('ssh-keys') }}">SSH keys</a> from the sidebar.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Generate a managed key</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Enter a key name (for example, {{ $branding['name'] }} primary).</li>
                    <li>Click <strong class="heading">Generate key</strong>. {{ $branding['name'] }} creates a keypair and encrypts the private half.</li>
                    <li>Download the private key immediately if offered — the one-time download is not shown again.</li>
                </ol>
                <p class="mt-3 text-sm muted">Provisioning workers use the managed private key to reach and bootstrap droplets. Only keys with a stored private key appear in the provision wizard.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Upload a public key</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Choose <strong class="heading">Upload public key</strong>.</li>
                    <li>Paste an OpenSSH public key (<code>ssh-rsa</code>, <code>ssh-ed25519</code>, and similar).</li>
                    <li>{{ $branding['name'] }} stores the fingerprint and public material only — useful for team access, not for automated provisioning.</li>
                </ol>
            </section>

            {{-- Provisioning --}}
            <section id="provisioning" class="panel scroll-mt-8">
                <h2 class="section-title">Provisioning a server</h2>
                <p class="mt-3 text-sm muted">Use <a class="link-action" href="{{ route('servers.create') }}">Servers → Provision</a> for DigitalOcean (API) servers. Attach existing boxes with <a class="link-action" href="{{ route('servers.custom') }}">Add existing server</a>.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Five-step wizard</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li><strong class="heading">Cloud account</strong> — pick a validated provider connection.</li>
                    <li><strong class="heading">Configuration</strong> — region, size (vCPU / RAM / monthly price), and Ubuntu image.</li>
                    <li><strong class="heading">SSH key</strong> — select a managed key so workers can bootstrap the host.</li>
                    <li><strong class="heading">Identity</strong> — display name and hostname (lowercase letters, numbers, hyphens).</li>
                    <li><strong class="heading">Review &amp; deploy</strong> — confirm details and start provisioning.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">What happens next</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>The droplet is created at the provider and progress is tracked on the dashboard.</li>
                    <li>Bootstrap installs Nginx, PHP 8.5 (plus 8.4/8.3/8.2), MySQL, Redis, Supervisor, Node, Composer, UFW, Fail2Ban, Certbot, and swap.</li>
                    <li>When status is ready, you can create sites on the server.</li>
                    <li>If a stage fails, open the server and use <strong class="heading">Retry provisioning</strong> after fixing the cause.</li>
                </ul>
                <p class="mt-3 text-sm muted">Your plan’s server quota is checked before deploy. Free capacity on Billing if the action is blocked.</p>
            </section>

            {{-- Sites --}}
            <section id="sites" class="panel scroll-mt-8">
                <h2 class="section-title">Adding a site</h2>
                <p class="mt-3 text-sm muted">Open <a class="link-action" href="{{ route('sites.create') }}">Sites → Create</a>. At least one ready server is required.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Laravel sites</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Choose the <strong class="heading">Laravel</strong> platform.</li>
                    <li>Select a ready server, enter the domain, and pick a PHP version (8.5, 8.4, 8.3, or 8.2).</li>
                    <li>Provide the Git repository URL and branch (defaults to <code>main</code>).</li>
                    <li>Optionally enable <strong class="heading">Auto deploy</strong> (webhook-driven) and <strong class="heading">Zero downtime</strong> releases.</li>
                    <li>Create the site. {{ $branding['name'] }} configures Nginx and prepares release directories in the background.</li>
                </ol>
                <p class="mt-3 text-sm muted">After creation, configure the encrypted <code>.env</code>, attach a database if needed, deploy from Git, and issue a Let’s Encrypt certificate from the site console.</p>

                <h3 class="mt-6 text-sm font-semibold heading">WordPress sites</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Choose <strong class="heading">WordPress</strong> — no Git repository is required.</li>
                    <li>Pick server, domain, and PHP version, then create the site.</li>
                    <li>Create and attach a managed database before the first deploy.</li>
                    <li>Deploy downloads WordPress, writes <code>wp-config.php</code>, and keeps <code>wp-content</code> outside releases so uploads and plugins survive updates.</li>
                    <li>Finish the WordPress installer in the browser.</li>
                    <li>Use the Themes, Plugins, and Backups tabs to install updates and take recovery points without SSH.</li>
                </ol>
            </section>

            {{-- Deployments --}}
            <section id="deployments" class="panel scroll-mt-8">
                <h2 class="section-title">Deployments and rollbacks</h2>
                <p class="mt-3 text-sm muted">Each Laravel deploy builds in a new release folder under <code>/var/www/&lt;domain&gt;/releases</code>. Nginx serves <code>current/public</code>. The live symlink switches only after Composer, builds, migrations, and caches succeed.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Manual deploy</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Open the site and click <strong class="heading">Deploy now</strong> (or Deploy from the overview).</li>
                    <li>Watch live command output on the deployment detail page.</li>
                    <li>Shared <code>.env</code> and <code>storage</code> persist across releases.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Repository URL</h3>
                <p class="mt-2 text-sm muted">Laravel sites accept HTTPS or SSH clone URLs from GitHub, GitLab, Bitbucket, or self-hosted Git (for example <code>https://gitlab.com/group/app.git</code>, <code>git@bitbucket.org:workspace/app.git</code>, or <code>ssh://git@github.com/acme/app.git</code>). Private repos need Git credentials on the managed server.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Automatic deploy (webhooks)</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Enable <strong class="heading">Automatic deployments</strong> on the Deployment settings tab.</li>
                    <li>Open the <strong class="heading">Webhook</strong> tab and copy the endpoint and secret.</li>
                    <li>GitHub: push webhook with HMAC (<code>X-Hub-Signature-256</code>).</li>
                    <li>Bitbucket: push webhook with HMAC (<code>X-Hub-Signature</code>, <code>sha256=…</code>).</li>
                    <li>GitLab: push webhook with the secret as <code>X-Gitlab-Token</code>.</li>
                    <li>Only pushes to the configured branch deploy; duplicate commit hashes and deleted branches are ignored.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Zero downtime and rollback</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li><strong class="heading">Zero downtime</strong> builds fully before switching <code>current</code>, so visitors keep the previous release until success.</li>
                    <li>The five newest successful releases are retained.</li>
                    <li>Rollback switches atomically to a previous release and reloads PHP-FPM, Nginx, and queue workers.</li>
                    <li>A failed deploy before the switch leaves the previous live release untouched.</li>
                </ul>
            </section>

            {{-- SSL --}}
            <section id="ssl" class="panel scroll-mt-8">
                <h2 class="section-title">SSL certificates</h2>
                <p class="mt-3 text-sm muted">From the site’s <strong class="heading">SSL</strong> tab, request a Let’s Encrypt certificate for the primary domain.</p>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Point DNS A/AAAA records at the server’s public IP before requesting.</li>
                    <li>Issue the certificate; {{ $branding['name'] }} configures Nginx for HTTPS.</li>
                    <li>Optionally force HTTPS redirects and track expiry from the same tab.</li>
                    <li>Renewals run on a schedule — keep DNS correct so Certbot can re-verify.</li>
                </ol>
            </section>

            {{-- Databases --}}
            <section id="databases" class="panel scroll-mt-8">
                <h2 class="section-title">Managed databases</h2>
                <p class="mt-3 text-sm muted">Create MySQL or PostgreSQL databases from the server manage page (<strong class="heading">Databases</strong> section).</p>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Choose engine, database name, and username.</li>
                    <li>Copy the one-time password when shown — it is not displayed again.</li>
                    <li>Attach the database to a site so {{ $branding['name'] }} writes <code>DB_*</code> (or WordPress credentials) into the encrypted environment.</li>
                    <li>Use import/export jobs for SQL dumps; downloads are private and expire after retention.</li>
                    <li>Optional: install phpMyAdmin on the server for browser SQL access (login with a managed database user).</li>
                </ol>
            </section>

            {{-- Workers & cron --}}
            <section id="workers" class="panel scroll-mt-8">
                <h2 class="section-title">Workers, cron, Horizon, and Reverb</h2>
                <p class="mt-3 text-sm muted">Keep queues and schedules running after every deploy without SSH.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Cron jobs</h3>
                <p class="mt-2 text-sm muted">Add schedules on the site <strong class="heading">Cron</strong> tab or at the server level. Jobs sync to the host crontab and can be toggled without deleting them.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Laravel scheduler presets</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Open the site’s <strong class="heading">Cron</strong> tab (preferred for app schedules) or the server’s <strong class="heading">Cron</strong> tab.</li>
                    <li>Choose the <strong class="heading">Laravel scheduler</strong> preset instead of Custom.</li>
                    <li>{{ $branding['name'] }} fills name, <code>* * * * *</code>, and <code>cd /var/www/{domain}/current &amp;&amp; php artisan schedule:run</code> for that site.</li>
                    <li>On the server Cron tab, pick a Laravel site from the preset chips (or add a site first if none appear).</li>
                    <li>Submit the job — it syncs to the host like any other cron entry.</li>
                </ol>
                <p class="mt-3 text-sm muted">Prefer the site Cron tab for Laravel schedulers so the entry stays bound to that site. Server-level cron runs as root and is unattached to a site.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Queue workers</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Open <strong class="heading">Queue &amp; Reverb</strong> on a Laravel site.</li>
                    <li>Add a worker (connection, queue name, processes).</li>
                    <li>{{ $branding['name'] }} writes a Supervisor program and keeps status in the panel.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Horizon and Reverb</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Install <code>laravel/horizon</code> or <code>laravel/reverb</code> as managed packages (or ship them in the repo).</li>
                    <li>Start them as Supervisor workers once the package is detected in the release.</li>
                    <li>Horizon dashboard access: list allowed app-user emails on the same tab — the allowlist file updates without a redeploy. App admins are also allowed once the site’s Horizon gate has been refreshed by a deploy.</li>
                </ul>
            </section>

            {{-- Monitoring --}}
            <section id="monitoring" class="panel scroll-mt-8">
                <h2 class="section-title">Monitoring and alerts</h2>
                <p class="mt-3 text-sm muted">Server metrics, auto-heal, and site uptime/DNS checks help you catch outages before customers do.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Server agent</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>On the server manage page, enable monitoring to install the metric agent.</li>
                    <li>Save the one-time secret if shown; rotate it anytime from the Monitoring controls.</li>
                    <li>Charts cover CPU, memory, disk, load, network, top processes, and service health (Nginx, PHP-FPM, MySQL, Redis, Supervisor).</li>
                    <li>Create alert rules with consecutive-sample thresholds and cooldowns.</li>
                    <li>Add notification channels: email, Slack, Discord, or Telegram (credentials encrypted).</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Auto-heal</h3>
                <p class="mt-2 text-sm muted">Opt in per server. When Nginx, PHP-FPM, MySQL, Redis, or Supervisor report down for consecutive samples, {{ $branding['name'] }} queues an allowlisted restart (with cooldown) and notifies you.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Site monitoring</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Enable monitoring on the site’s <strong class="heading">Monitoring</strong> tab.</li>
                    <li><strong class="heading">Website down</strong> — HTTP probes to your domain (HTTPS when SSL is active); incidents open after consecutive failures.</li>
                    <li><strong class="heading">DNS mismatch</strong> — compares public DNS to the server IP.</li>
                    <li>Laravel sites also get periodic queue-health checks for failed jobs.</li>
                    <li>Recovery notifications fire when probes succeed again.</li>
                </ol>
                <p class="mt-3 text-sm muted">Open incidents across your fleet also appear under <a class="link-action" href="{{ route('notifications.index') }}">Notifications → Incidents</a>.</p>
            </section>

            {{-- Security detection --}}
            <section id="security-detection" class="panel scroll-mt-8">
                <h2 class="section-title">Security detection</h2>
                <p class="mt-3 text-sm muted">Open <a class="link-action" href="{{ route('security.index') }}">Security</a> in the sidebar to watch managed servers for suspicious host and site signals. {{ $branding['name'] }} runs read-only checks over managed SSH, turns threshold breaches into workspace incidents, and never auto-blocks IPs or kills processes.</p>

                <h3 class="mt-6 text-sm font-semibold heading">What is detected</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li><strong class="heading">Server signals</strong> — repeated failed SSH logins, administrative user or group changes, known crypto-miner process names and high-confidence mining ports, and unexpected hash changes to cron, SSH authorized keys, web entry files, and <code>.env</code> paths (path and change indication only — secrets are never collected).</li>
                    <li><strong class="heading">Site signals</strong> — login and POST bursts, one address rapidly scanning routes, known scanner user agents, Fail2ban / WAF blocks, malware signature hits, and unexpected admin actions when those events are available.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">How scans work</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Use <strong class="heading">Scan now</strong> on a server (or <strong class="heading">Scan all now</strong>) to queue collection immediately.</li>
                    <li>Scheduled scans also run about every five minutes for eligible servers.</li>
                    <li>Jobs use the <code>operations</code> queue and existing managed SSH credentials — keep an operations worker running.</li>
                    <li>Each server shows a live status badge: <strong class="heading">Queued</strong> → <strong class="heading">Scanning…</strong> → last completed scan time, or <strong class="heading">Failed</strong> with a short message. The Security page refreshes status while a scan is in flight.</li>
                    <li>The first integrity scan builds a protected host-side hash baseline and does not raise an alert.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Configuration</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Detection is <strong class="heading">enabled by default</strong> for the workspace.</li>
                    <li>Team owners and administrators open <strong class="heading">Detection settings</strong> on the Security page to toggle each rule, and set threshold, lookback window (minutes), and severity (info / warning / critical).</li>
                    <li>Recommended defaults ship with the platform; workspace overrides are stored separately. Use <strong class="heading">Reset to recommended defaults</strong> to clear overrides.</li>
                    <li>Keep detection enabled. Observe a normal baseline first, then tune only noisy thresholds or windows. Turning the global setting off stops scheduled collection, manual scans, and incident creation from pushed agent events for that workspace.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Incidents and email</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Matching signals become incidents under <a class="link-action" href="{{ route('notifications.index') }}">Notifications → Incidents</a> (filter by Security). You can acknowledge or resolve them; reopen if needed. State and mitigation changes are audit logged.</li>
                    <li>New or escalated incidents can email you when a recipient is subscribed to <strong class="heading">Security incident</strong> under Notifications → Email recipients (or when no recipients are configured, mail goes to your account address).</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">Block IP (manual only)</h3>
                <p class="mt-2 text-sm muted">From a security incident, <strong class="heading">Block IP</strong> queues a normal UFW deny rule for a public source address. Private, reserved, loopback, and server-owned addresses are rejected. <strong class="heading">Unblock</strong> removes only the rule created for that incident. Automatic IP blocking is off and unavailable — review each case first. Related allow/deny management also lives under <a class="link-action" href="#firewall">Firewall</a>.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Limitations</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>This is pragmatic detection and response for managed hosts — not a full EDR or SIEM replacement.</li>
                    <li>Generic application login and admin events need a signed security-event push from your app or agent; they are not inferred from SSH collection alone.</li>
                    <li>{{ $branding['name'] }} never deletes users or kills processes automatically.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">Prerequisites</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>The server must be <strong class="heading">Ready</strong> with a working managed SSH key.</li>
                    <li>Security detection must stay enabled for the workspace.</li>
                    <li>An operations queue worker must process scan jobs (a status stuck on Queued usually means the worker is down).</li>
                </ul>
            </section>

            {{-- Notifications --}}
            <section id="notifications" class="panel scroll-mt-8">
                <h2 class="section-title">Notifications and incidents</h2>
                <p class="mt-3 text-sm muted">Open <a class="link-action" href="{{ route('notifications.index') }}">Notifications</a> in the sidebar (below Firewall). The page has two tabs: <strong class="heading">Incidents</strong> and <strong class="heading">Email recipients</strong>.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Incidents</h3>
                <p class="mt-2 text-sm muted">A single list of open and resolved events across your servers and sites — uptime, DNS mismatch, metric alerts, <a class="link-action" href="#security-detection">security detection</a>, and similar operational signals.</p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Filter by status (open / resolved / all), severity, type (including Security), and server.</li>
                    <li>Each row shows the message, severity, source, start/resolve times, and links back to the related server or site when available.</li>
                    <li>Monitoring and site-probe incidents often resolve when health returns; security incidents are acknowledged or resolved from the incident actions (with optional manual Block IP).</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">Email recipients</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Switch to <strong class="heading">Email recipients</strong>.</li>
                    <li>Add a name and optional address (blank uses your account email).</li>
                    <li>Optionally tick which events to receive — leave every box clear to get all of them.</li>
                    <li>Recipients are account-wide, not tied to a single server.</li>
                </ol>
                <p class="mt-3 text-sm muted">With no recipients configured, operational email goes to your account address. Event types include server provisioned, server down, disk full, site added, website down/recovered, DNS mismatch, deploy complete, SSL issued/expiring, queue failed, <strong class="heading">backup failed</strong>, <strong class="heading">security incident</strong>, and auto-heal actions. Per-server Slack, Discord, and Telegram channels still live on each server’s Monitoring controls.</p>
            </section>

            {{-- Firewall --}}
            <section id="firewall" class="panel scroll-mt-8">
                <h2 class="section-title">Firewall</h2>
                <p class="mt-3 text-sm muted">Manage per-server UFW allow and deny rules from the console without pasting raw shell. Open <a class="link-action" href="{{ route('firewall.index') }}">Firewall</a> in the sidebar (directly below <strong class="heading">Sites</strong>).</p>

                <h3 class="mt-6 text-sm font-semibold heading">Using the page</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Pick a ready server from the dropdown — rules belong to one host at a time.</li>
                    <li>Add an <strong class="heading">allow</strong> or <strong class="heading">deny</strong> rule with protocol, port (or an allowlisted profile such as OpenSSH / Nginx Full), optional source IP/CIDR, and an optional description.</li>
                    <li>New rules queue automatically; use <strong class="heading">Apply to server</strong> to re-sync every stored rule after a failure or drift.</li>
                    <li>Use <strong class="heading">Refresh remote status</strong> to pull <code>ufw status verbose</code> into the page without changing rules.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Requirements</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>The server must be ready with working SSH from {{ $branding['name'] }}.</li>
                    <li>UFW must be installed on the host (included in the Ubuntu bootstrap). If it is missing, the page shows a clear warning instead of failing silently.</li>
                    <li>Horizon (or an equivalent worker) must process the <code>operations</code> queue so sync and refresh jobs run.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">Statuses</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li><strong class="heading">pending</strong> — queued or in flight; not yet confirmed on the host.</li>
                    <li><strong class="heading">synced</strong> — last apply or remove succeeded.</li>
                    <li><strong class="heading">failed</strong> — remote command failed; check the rule’s status message.</li>
                    <li><strong class="heading">missing_ufw</strong> — UFW is not available on the host.</li>
                </ul>
                <p class="mt-3 text-sm muted">Bootstrap still opens OpenSSH and Nginx Full on new hosts. Custom rules layer on top of that baseline. The UI reflects the last synchronization job rather than assuming the remote action succeeded.</p>
            </section>

            {{-- Backups --}}
            <section id="backups" class="panel scroll-mt-8">
                <h2 class="section-title">Backups and restores</h2>
                <p class="mt-3 text-sm muted">Open a ready server → <strong class="heading">Backups</strong> tab to schedule recovery points, run them on demand, and restore or download databases.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Automated policies</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Create a policy with a name, type (database export or provider snapshot), frequency (daily / weekly / monthly), local run time, and timezone.</li>
                    <li>For database exports, pick the managed database and a private <strong class="heading">storage disk</strong> (local or S3-compatible disks configured for the platform).</li>
                    <li>Set how many recovery points to keep; older ready exports are pruned by count and by the platform retention window.</li>
                    <li>Use <strong class="heading">Run now</strong>, <strong class="heading">Pause</strong> / <strong class="heading">Enable</strong>, or <strong class="heading">Remove</strong> on each policy card.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Database recovery</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Ready exports appear under <strong class="heading">Database recovery points</strong>.</li>
                    <li><strong class="heading">Download</strong> pulls the SQL dump from the stored disk.</li>
                    <li><strong class="heading">Restore database</strong> requires typing the exact database name as confirmation. Restore does not delete the source recovery point.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">Server snapshots (DigitalOcean)</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Create on-demand or scheduled Droplet snapshots via the provider API when the server has a cloud provider ID.</li>
                    <li>Restore replaces the Droplet disk — confirm with the exact hostname.</li>
                    <li>External DNS, object storage, and resources outside the VM are not restored by a snapshot.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">BYO / custom servers</h3>
                <p class="mt-2 text-sm muted">Connected custom (bring-your-own) servers support <strong class="heading">database export policies only</strong>. Provider snapshots require a DigitalOcean Droplet. The Backups tab explains this when snapshots are unavailable.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Failure alerts</h3>
                <p class="mt-2 text-sm muted">Failed database exports, snapshot create/refresh failures, and restore failures send a <strong class="heading">Backup failed</strong> email. Subscribe under <a class="link-action" href="{{ route('notifications.index') }}">Notifications → Email recipients</a>.</p>

                <h3 class="mt-6 text-sm font-semibold heading">WordPress backups</h3>
                <p class="mt-2 text-sm muted">WordPress sites have a dedicated Backups tab for application-level recovery points before plugin or core updates. Those archives stay on the VPS.</p>
            </section>

            {{-- Staging --}}
            <section id="staging" class="panel scroll-mt-8">
                <h2 class="section-title">Staging sites</h2>
                <p class="mt-3 text-sm muted">When staging is enabled for the platform, create a linked staging environment from a production site’s Overview tab.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Hostname options</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li><strong class="heading">Platform subdomain</strong> — <code>{slug}.staging.uplary.com</code> (or your platform’s staging apex).</li>
                    <li><strong class="heading">Client domain</strong> — any FQDN such as <code>staging.client.com</code>.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">How it works</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Staging is a separate site on the same server (own Nginx vhost, release root, and environment).</li>
                    <li>Laravel staging typically seeds <code>APP_ENV=staging</code>.</li>
                    <li><strong class="heading">Promote to production</strong> copies repository, branch, deploy script, and PHP version onto production and queues a production deploy.</li>
                    <li>Point DNS for the staging hostname at the server before requesting SSL.</li>
                </ul>
            </section>

            {{-- Remote management --}}
            <section id="remote" class="panel scroll-mt-8">
                <h2 class="section-title">Remote management</h2>
                <p class="mt-3 text-sm muted">Operate the site without opening a root shell. Actions queue over SSH and keep an audit trail.</p>

                <h3 class="mt-6 text-sm font-semibold heading">PHP and Nginx settings</h3>
                <p class="mt-2 text-sm muted">Edit structured pool and vhost settings (memory, uploads, timeouts, caching, optional <code>www</code>). Config is validated (<code>php-fpm -t</code> / <code>nginx -t</code>), backed up, and can be rolled back from revision history.</p>

                <h3 class="mt-6 text-sm font-semibold heading">File manager</h3>
                <p class="mt-2 text-sm muted">Browse, edit, upload, download, rename, change permissions, ZIP, extract, and delete under the site root only. Paths cannot escape <code>/var/www/&lt;domain&gt;</code>.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Command console</h3>
                <p class="mt-2 text-sm muted">Run allowlisted programs (<code>php</code>, <code>composer</code>, <code>git</code>, <code>npm</code>, <code>node</code>, and common read tools) as <code>www-data</code> from the current release. Shell operators and absolute path tricks are rejected.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Server operations</h3>
                <p class="mt-2 text-sm muted">From the server console, restart allowlisted services (Nginx, PHP-FPM, MySQL, Redis, Supervisor), manage PHP extensions, and review operation logs. For package updates and hardening, see <a class="link-action" href="#maintenance">Server maintenance</a>.</p>
            </section>

            {{-- Server maintenance --}}
            <section id="maintenance" class="panel scroll-mt-8">
                <h2 class="section-title">Server maintenance</h2>
                <p class="mt-3 text-sm muted">On a ready server, open the <strong class="heading">Services</strong> tab → <strong class="heading">Maintenance</strong>. Each action queues an allowlisted script over SSH and records output in the server’s operation history.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Software hardening</h3>
                <p class="mt-2 text-sm muted">Re-applies baseline hardening: UFW and Fail2Ban present, additive UFW baseline (OpenSSH + Nginx Full — does not reset or remove console firewall rules), SSH password auth off when keys are in use, Fail2Ban sshd jail, and sysctl drop-ins. Confirm before running.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Update Ubuntu packages</h3>
                <p class="mt-2 text-sm muted">Runs package update and upgrade (plus autoremove) with apt-lock retries. Prefer this for routine maintenance. Services may briefly restart.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Major release upgrade</h3>
                <p class="mt-2 text-sm muted">Runs a noninteractive Ubuntu release upgrade (<code>do-release-upgrade</code>). It can break PHP, Nginx, or MySQL packages and usually requires a reboot. Expand the warning panel, type the exact hostname to confirm, and only use this when you intentionally need a new Ubuntu LTS. Prefer package updates otherwise.</p>
            </section>

            {{-- DNS --}}
            <section id="dns" class="panel scroll-mt-8">
                <h2 class="section-title">DNS (Cloudflare)</h2>
                <p class="mt-3 text-sm muted">Connect a Cloudflare account under <strong class="heading">DNS</strong> to manage zones next to your sites.</p>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Add an API token with zone DNS edit permissions.</li>
                    <li>Sync zones, then open a zone to create or edit A, AAAA, CNAME, and TXT records.</li>
                    <li>Point site hostnames at the server public IP before requesting SSL or enabling site DNS monitoring.</li>
                </ol>
            </section>

            {{-- Teams & API --}}
            <section id="teams" class="panel scroll-mt-8">
                <h2 class="section-title">Teams and API tokens</h2>

                <h3 class="mt-4 text-sm font-semibold heading">Teams</h3>
                <p class="mt-2 text-sm muted">Open <a class="link-action" href="{{ route('teams.index') }}">Teams</a> to create a workspace, invite colleagues, and switch the active team when collaborating across workspaces. Seat counts count against your plan.</p>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Create a team and invite members by email with a role: <strong class="heading">Viewer</strong>, <strong class="heading">Operator</strong>, or <strong class="heading">Admin</strong>.</li>
                    <li>Invitations expire (typically after 7 days) and can be used once.</li>
                    <li>On pending invitations: <strong class="heading">Edit</strong> changes the role before acceptance, <strong class="heading">Resend</strong> sends the email again (rate-limited), and <strong class="heading">Delete</strong> cancels the invite.</li>
                    <li>After acceptance, owners and admins can change a member’s role or remove them (the owner cannot be demoted or removed by others).</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Role privileges</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li><strong class="heading">Owner</strong> — full control of the team and its servers; manage members and invitations; transfer and delete team servers. Cannot be removed or demoted by other members.</li>
                    <li><strong class="heading">Admin</strong> — invite, edit, resend, and cancel invitations; change roles and remove members; view, operate, transfer, and delete team servers. Cannot remove or demote the owner.</li>
                    <li><strong class="heading">Operator</strong> — view and operate team servers (deploy, configure). Cannot manage members or invitations, or transfer/delete servers.</li>
                    <li><strong class="heading">Viewer</strong> — read-only access to shared infrastructure. Cannot deploy, change servers, or manage members.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">API tokens</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Create tokens from <a class="link-action" href="{{ route('account') }}">Account settings</a> (90-day scoped tokens).</li>
                    <li>Send <code>Authorization: Bearer &lt;token&gt;</code> to <code>/api/servers</code>, <code>/api/sites</code>, <code>/api/deployments</code>, <code>/api/databases</code>, <code>/api/ssl</code>, and <code>/api/metrics</code>.</li>
                    <li>Abilities use <code>servers:read</code> and <code>servers:write</code>; revoke unused tokens promptly.</li>
                </ol>
            </section>

            {{-- Plans --}}
            <section id="plans" class="panel scroll-mt-8">
                <h2 class="section-title">Plans and billing</h2>
                <p class="mt-3 text-sm muted">Open <a class="link-action" href="{{ route('billing.index') }}">Billing</a> from the account menu to see usage and available plans.</p>

                <h3 class="mt-6 text-sm font-semibold heading">What plans control</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Quotas for servers, sites, databases, API tokens, teams, and team members</li>
                    <li>Feature access such as monitoring, remote management, and team collaboration</li>
                    <li>Monthly or yearly pricing when published by an administrator</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">Request or change a plan</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Review the plan cards and your current usage bars.</li>
                    <li>Choose monthly or yearly billing and optionally add a purchase-order note.</li>
                    <li>Click <strong class="heading">Request this plan</strong>. An administrator reviews manual requests.</li>
                    <li>If Stripe is configured, you can also checkout or manage the subscription through the customer portal on the same page.</li>
                </ol>
                <p class="mt-3 text-sm muted">Quota errors when creating resources mean the current plan is full — request a higher plan or remove unused resources.</p>
            </section>

            {{-- Account security --}}
            <section id="password" class="panel scroll-mt-8">
                <h2 class="section-title">Account security</h2>
                <p class="mt-3 text-sm muted">Open the account menu (avatar at the bottom of the sidebar) → <a class="link-action" href="{{ route('account') }}">Account settings</a>.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Password</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>In the <strong class="heading">Password</strong> panel, enter your current password.</li>
                    <li>Enter and confirm the new password.</li>
                    <li>Click <strong class="heading">Update password</strong>. Other sessions remain until you revoke them under Active sessions.</li>
                </ol>

                <h3 class="mt-6 text-sm font-semibold heading">Forgot password</h3>
                <p class="mt-2 text-sm muted">From the login screen, use the password reset link. A reset email is sent when mail is configured.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Google sign-in</h3>
                <p class="mt-2 text-sm muted">When the platform enables Google sign-in, login and register show <strong class="heading">Continue with Google</strong>. Use that button to create a customer account or link to an existing password account with the same verified Google email. If the button is missing, Google sign-in is not enabled for this install. Two-factor authentication still applies after Google sign-in when you have enabled it.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Stronger account security</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Enable two-factor authentication on the same Account settings page and store recovery codes safely.</li>
                    <li>Revoke unused API tokens and unfamiliar browser sessions.</li>
                </ul>
            </section>

            {{-- Support --}}
            <section id="support" class="panel scroll-mt-8">
                <h2 class="section-title">Getting help</h2>
                <p class="mt-3 text-sm muted">If something is blocked or unclear after following these guides:</p>
                <ul class="mt-3 list-inside list-disc space-y-1 text-sm muted">
                    <li>Check server provisioning status and retry a failed stage from the server page.</li>
                    <li>Confirm your plan quotas on Billing before creating more servers or sites.</li>
                    <li>Open deployment or operation logs when a job fails — the output is retained for diagnosis.</li>
                    <li>Verify DNS points at the server IP before SSL, site monitoring, or public traffic.</li>
                </ul>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('contact') }}" class="button-primary">Contact support</a>
                    <a href="{{ route('dashboard') }}" class="button-secondary">Back to dashboard</a>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
