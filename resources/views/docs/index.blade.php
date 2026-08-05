@extends('layouts.app')
@section('content')
@php
    $sections = [
        ['id' => 'getting-started', 'label' => 'Getting started'],
        ['id' => 'providers', 'label' => 'Providers & IPs'],
        ['id' => 'ssh-keys', 'label' => 'SSH keys'],
        ['id' => 'provisioning', 'label' => 'Provisioning'],
        ['id' => 'sites', 'label' => 'Adding a site'],
        ['id' => 'deployments', 'label' => 'Deployments'],
        ['id' => 'ssl', 'label' => 'SSL certificates'],
        ['id' => 'databases', 'label' => 'Databases'],
        ['id' => 'workers', 'label' => 'Workers & cron'],
        ['id' => 'monitoring', 'label' => 'Monitoring'],
        ['id' => 'backups', 'label' => 'Backups'],
        ['id' => 'staging', 'label' => 'Staging sites'],
        ['id' => 'remote', 'label' => 'Remote management'],
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
                    <p class="mt-2">Need a nudge while working? Use the floating <strong class="heading">AI guide</strong> (bottom-right) when a superadmin has enabled it.</p>
                </div>
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
                    <li>Bootstrap installs Nginx, PHP 8.4, MySQL, Redis, Supervisor, Node, Composer, UFW, Fail2Ban, Certbot, and swap.</li>
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
                    <li>Select a ready server, enter the domain, and pick a PHP version (8.4, 8.3, or 8.2).</li>
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

                <h3 class="mt-6 text-sm font-semibold heading">Automatic deploy (webhooks)</h3>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Enable <strong class="heading">Automatic deployments</strong> on the Deployment settings tab.</li>
                    <li>Open the <strong class="heading">Webhook</strong> tab and copy the endpoint and secret.</li>
                    <li>GitHub / Bitbucket: configure a push webhook with HMAC signature (<code>X-Hub-Signature-256</code>).</li>
                    <li>GitLab: send the secret as <code>X-Gitlab-Token</code>.</li>
                    <li>Only pushes to the configured branch deploy; duplicate commit hashes are ignored.</li>
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
            </section>

            {{-- Backups --}}
            <section id="backups" class="panel scroll-mt-8">
                <h2 class="section-title">Backups and restores</h2>
                <p class="mt-3 text-sm muted">Schedule recovery points from the server manage page under backups.</p>

                <h3 class="mt-6 text-sm font-semibold heading">Database policies</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Daily, weekly, or monthly SQL exports at a local wall-clock time and timezone.</li>
                    <li>Count-based retention; older exports expire and are removed.</li>
                    <li>Restore requires typing the exact database name as confirmation.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">Server snapshots (DigitalOcean)</h3>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm muted">
                    <li>Create on-demand or scheduled Droplet snapshots via the provider API.</li>
                    <li>Restore replaces the Droplet disk — confirm with the exact hostname.</li>
                    <li>External DNS, object storage, and resources outside the VM are not restored by a snapshot.</li>
                </ul>

                <h3 class="mt-6 text-sm font-semibold heading">WordPress backups</h3>
                <p class="mt-2 text-sm muted">WordPress sites have a dedicated Backups tab for application-level recovery points before plugin or core updates.</p>
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
                <p class="mt-2 text-sm muted">From the server console, restart allowlisted services (Nginx, PHP-FPM, MySQL, Redis, Supervisor), manage PHP extensions, and review operation logs.</p>
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
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                    <li>Open <a class="link-action" href="{{ route('teams.index') }}">Teams</a> to create a workspace.</li>
                    <li>Invite members by email as <strong class="heading">member</strong> or <strong class="heading">admin</strong>.</li>
                    <li>Invitations expire and can be used once; seat counts count against your plan.</li>
                    <li>Switch the active team from the teams page when collaborating across workspaces.</li>
                </ol>

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
