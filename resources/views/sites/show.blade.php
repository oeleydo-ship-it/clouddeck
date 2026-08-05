@extends('layouts.app')
@section('content')
<div class="app-main" x-data="{ tab: 'overview', keys: @js($site->isWordPress() ? ['overview','themes','plugins','backups','environment','ssl','cron','logs','monitoring'] : ['overview','environment','deploy','ssl','cron','queue','webhook','logs','monitoring']), init() { const h = location.hash.replace('#',''); if (this.keys.includes(h)) { this.tab = h } this.$watch('tab', v => history.replaceState(null, '', '#' + v)) } }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a class="link-action" href="{{ route('sites.index') }}">← Sites</a>
            <div class="mt-2 flex flex-wrap items-center gap-3"><h1 class="page-title !mt-0">{{ $site->domain }}</h1>
                @livewire('site-status-badge', ['site' => $site])
                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ $site->isWordPress() ? 'WordPress' : 'Laravel' }}</span>
                @if($site->isStaging())
                    <span class="badge bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Staging</span>
                    @if($site->productionSite)
                        <a class="text-xs text-cyan-600 dark:text-cyan-300" href="{{ route('sites.show', $site->productionSite) }}">Production: {{ $site->productionSite->domain }}</a>
                    @endif
                @else
                    <span class="badge bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Production</span>
                    @if($site->stagingSite)
                        <a class="text-xs text-cyan-600 dark:text-cyan-300" href="{{ route('sites.show', $site->stagingSite) }}">Staging: {{ $site->stagingSite->domain }}</a>
                    @endif
                @endif
                @php
                    // http until a certificate is actually active: linking to https before then
                    // lands on a browser warning rather than the site.
                    $secure = $site->sslCertificates->contains(fn ($certificate) => $certificate->status === 'active');
                @endphp
                <a href="{{ ($secure ? 'https://' : 'http://').$site->domain }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-white/10 dark:text-slate-300 dark:hover:border-cyan-400/30 dark:hover:text-cyan-300"
                   title="Open {{ $site->domain }} in a new tab">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                    Visit site
                </a>
            </div>
            <p class="mt-2 text-sm muted">{{ $site->server->name }} · PHP {{ $site->php_version }}</p>
        </div>
        @php
            // WordPress reads its credentials from a generated wp-config.php and never has a
            // DB_CONNECTION, so asking for one would leave the button disabled forever.
            $databaseKey = $site->isWordPress() ? 'DB_DATABASE' : 'DB_CONNECTION';
            $hasDatabase = $site->environmentVariables->contains(fn ($variable) => $variable->key === $databaseKey);
            // Files on disk and a finished install are different things: the install runs
            // in the browser after the first deployment and writes the WordPress tables.
            $wordpressInstalled = $site->wordpressIsInstalled();
            $action = match (true) {
                ! $site->isWordPress() => 'Deploy now',
                $wordpressInstalled => 'Reinstall WordPress',
                default => 'Install WordPress',
            };
        @endphp
        <div class="flex gap-3">
            @unless($site->isWordPress())<button @click="tab='deploy'" class="button-secondary">Edit site</button>@endunless
            @if($site->isStaging() && $stagingSitesEnabled)
                <form method="POST" action="{{ route('sites.promote', $site) }}" onsubmit="return confirm('Copy staging branch and settings to production and deploy {{ $site->productionSite?->domain }}?')">
                    @csrf<button class="button-secondary !text-amber-700 dark:!text-amber-300" @disabled($site->status !== 'active' || $site->productionSite?->status !== 'active')>Promote to production</button>
                </form>
            @endif
            <form method="POST" action="{{ route('sites.deploy',$site) }}"
                  @if($wordpressInstalled) onsubmit="return confirm('Replace the WordPress core files with the latest release? Your database, uploads, plugins, and themes are kept.')" @endif>
                @csrf<button class="button-primary" @disabled($site->status !== 'active' || ! $hasDatabase)>{{ $action }}</button>
            </form>
        </div>
    </div>
    @unless($hasDatabase)
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-400/20 dark:bg-amber-400/10">
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Create a database before {{ $site->isWordPress() ? 'installing' : 'deploying' }}</p>
            <p class="mt-1 text-sm text-amber-700 dark:text-amber-200/80">
                @if($site->isWordPress())
                    WordPress cannot run without one, and {{ $branding['name'] }} writes its credentials into <code>wp-config.php</code> when it installs.
                    Create a database on <a class="font-medium underline" href="{{ route('servers.manage',$site->server) }}#databases">{{ $site->server->name }}</a> and attach it to this site, then come back and install.
                @else
                    This site has no <code>DB_CONNECTION</code> in its environment, so Laravel would fall back to SQLite and the deployment would fail during migrations — the provisioned PHP only carries the MySQL and PostgreSQL drivers.
                    Create one on <a class="font-medium underline" href="{{ route('servers.manage',$site->server) }}#databases">{{ $site->server->name }}</a> and attach it to this site; {{ $branding['name'] }} writes the <code>DB_*</code> connection details into the environment for you.
                    If this application genuinely has no database, set <code>DB_CONNECTION</code> yourself on the Environment tab.
                @endif
            </p>
        </div>
    @endunless
    @if($site->isWordPress() && $hasDatabase && $site->last_deployed_at && ! $wordpressInstalled)
        <div class="mt-5 rounded-xl border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-400/20 dark:bg-cyan-400/10">
            <p class="text-sm font-medium text-cyan-800 dark:text-cyan-200">Finish the WordPress install</p>
            <p class="mt-1 text-sm text-cyan-700 dark:text-cyan-200/80">
                The files are deployed and <code>wp-config.php</code> is written. Complete the setup at
                <a class="font-medium underline" href="{{ ($secure ? 'https://' : 'http://').$site->domain }}/wp-admin/install.php" target="_blank" rel="noopener noreferrer">{{ $site->domain }}/wp-admin/install.php</a>
                to create the database tables and your administrator account.
            </p>

            <form method="POST" action="{{ route('sites.wordpress-status',$site) }}" class="mt-3">@csrf
                <button class="button-secondary !px-3 !py-1.5 text-xs">Check again</button>
                @if($site->wordpress_checked_at)<span class="ml-2 text-xs muted">Last checked {{ $site->wordpress_checked_at->diffForHumans() }}</span>@endif
            </form>
        </div>
    @endif
    @if($errors->any())<div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>@endif
    <div class="mt-5"><a href="{{ route('sites.remote',$site) }}" class="button-secondary inline-block">Open PHP, Nginx, files, and console</a></div>
    @can('delete', $site)
        <details id="danger-zone" class="panel mt-5 !border-rose-200 dark:!border-rose-400/20"><summary class="cursor-pointer font-medium text-rose-600 dark:text-rose-300">Danger zone</summary><p class="mt-3 text-sm muted">Permanently removes this site from {{ $branding['name'] }} and deletes its Nginx configuration, PHP-FPM pool, SSL certificate, and files from the server. This cannot be undone.</p><form method="POST" action="{{ route('sites.destroy',$site) }}" class="mt-4 flex flex-wrap gap-3" onsubmit="return confirm('Permanently delete {{ $site->domain }} and all its files on the server?')">@csrf @method('DELETE')<input class="field mt-0" name="confirmation" placeholder="Type {{ $site->domain }} to confirm"><button class="button-secondary !text-rose-600 dark:!text-rose-300">Delete site</button></form></details>
    @endcan
    <div class="mt-8 flex gap-2 overflow-x-auto border-b border-slate-200 dark:border-white/10">@php
        $tabs = $site->isWordPress()
            ? ['overview'=>'Overview','themes'=>'Themes','plugins'=>'Plugins','backups'=>'Backups','environment'=>'Environment','ssl'=>'SSL','cron'=>'Cron','logs'=>'Logs','monitoring'=>'Monitoring']
            : ['overview'=>'Overview','environment'=>'Environment','deploy'=>'Deployment settings','ssl'=>'SSL','cron'=>'Cron','queue'=>'Queue & Reverb','webhook'=>'Webhook','logs'=>'Logs','monitoring'=>'Monitoring'];
    @endphp
    @foreach($tabs as $key=>$label)<button @click="tab='{{ $key }}'" :class="tab==='{{ $key }}' ? 'border-cyan-500 text-slate-900 dark:border-cyan-400 dark:text-white' : 'border-transparent text-slate-500 dark:text-slate-400'" class="border-b-2 px-4 py-3 text-sm font-medium">{{ $label }}</button>@endforeach</div>
    <div x-show="tab==='overview'" class="mt-6"><div class="grid gap-4 sm:grid-cols-3">
            @if($site->isWordPress())
                {{-- Repository and branch are empty for an install downloaded from
                     wordpress.org, so the cards say something true instead. --}}
                <div class="panel"><p class="text-xs uppercase tracking-wide muted">Source</p><p class="mt-2 truncate text-sm heading">wordpress.org</p></div>
                <div class="panel"><p class="text-xs uppercase tracking-wide muted">Installed version</p><p class="mt-2 text-sm heading">{{ $deployments->firstWhere('status', \App\Enums\DeploymentStatus::Successful)?->commit_message ?? 'Not deployed yet' }}</p><p class="mt-1 text-xs {{ $wordpressInstalled ? 'text-emerald-600 dark:text-emerald-300' : 'muted' }}">{{ $wordpressInstalled ? 'Setup complete' : 'Setup not finished' }}</p></div>
            @else
                <div class="panel"><p class="text-xs uppercase tracking-wide muted">Repository</p><p class="mt-2 truncate text-sm heading">{{ $site->repository_url }}</p></div>
                <div class="panel"><p class="text-xs uppercase tracking-wide muted">Branch</p><p class="mt-2 font-mono text-sm heading">{{ $site->branch }}</p></div>
            @endif
            <div class="panel"><p class="text-xs uppercase tracking-wide muted">Last deployed</p><p class="mt-2 text-sm heading">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</p></div>
        </div>
        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 dark:border-white/10 dark:bg-white/[.03] dark:shadow-none"><div class="border-b border-slate-200 px-6 py-4 dark:border-white/10"><h2 class="font-semibold heading">Deployment history</h2></div>@forelse($deployments as $deployment)<div class="data-row grid items-center gap-4 sm:grid-cols-[1fr_150px_120px_auto]"><a href="{{ route('deployments.show',$deployment) }}"><p class="font-mono text-sm heading">{{ $deployment->release ?? Str::limit($deployment->id,14) }}</p><p class="mt-1 text-xs muted">{{ $deployment->trigger }} by {{ $deployment->user?->name ?? 'webhook' }} · {{ $deployment->created_at->diffForHumans() }}</p></a><span class="text-sm font-medium capitalize {{ $deployment->status->value === 'failed' ? 'text-rose-600 dark:text-rose-300' : ($deployment->status->value === 'successful' ? 'text-emerald-600 dark:text-emerald-300' : 'text-cyan-600 dark:text-cyan-300') }}">{{ str_replace('_',' ',$deployment->status->value) }}</span><span class="text-xs muted">{{ $deployment->duration_for_humans ?? '—' }}</span>@if($deployment->release && in_array($deployment->status,[\App\Enums\DeploymentStatus::Successful,\App\Enums\DeploymentStatus::RolledBack],true))<form method="POST" action="{{ route('sites.rollback',[$site,$deployment]) }}">@csrf<button class="button-secondary !px-3 !py-1.5 text-xs !text-amber-600 dark:!text-amber-300">Rollback</button></form>@else<span></span>@endif</div>@empty<div class="px-6 py-10 text-center muted">No deployments yet.</div>@endforelse</section><div class="mt-5">{{ $deployments->links() }}</div>

        @if($site->isProduction() && $stagingSitesEnabled)
            <section id="staging-setup" class="panel mt-6">
                <h2 class="font-semibold heading">Staging environment</h2>
                @if($site->stagingSite)
                    <p class="mt-2 text-sm muted">Staging is live at <a class="text-cyan-600 dark:text-cyan-300" href="{{ route('sites.show', $site->stagingSite) }}">{{ $site->stagingSite->domain }}</a>.</p>
                @else
                    <p class="mt-2 text-sm muted">Create a linked staging site on the same server. Choose a {{ $branding['name'] }} subdomain or your own client domain, then promote when ready.</p>
                    <form method="POST" action="{{ route('sites.staging.store', $site) }}" class="mt-5 space-y-4" x-data="{ source: 'platform' }">@csrf
                        <fieldset>
                            <legend class="text-sm font-medium heading">Staging hostname</legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="flex cursor-pointer gap-3 rounded-xl border p-4" :class="source === 'platform' ? 'border-cyan-400 bg-cyan-50/50 dark:border-cyan-400/40 dark:bg-cyan-400/5' : 'border-slate-200 dark:border-white/10'">
                                    <input type="radio" name="domain_source" value="platform" x-model="source" class="mt-0.5">
                                    <span><span class="block text-sm font-medium heading">{{ $branding['name'] }} subdomain</span><span class="mt-1 block text-xs muted">{slug}.staging.{{ $stagingPlatformDomain }}</span></span>
                                </label>
                                <label class="flex cursor-pointer gap-3 rounded-xl border p-4" :class="source === 'custom' ? 'border-cyan-400 bg-cyan-50/50 dark:border-cyan-400/40 dark:bg-cyan-400/5' : 'border-slate-200 dark:border-white/10'">
                                    <input type="radio" name="domain_source" value="custom" x-model="source" class="mt-0.5">
                                    <span><span class="block text-sm font-medium heading">Client domain</span><span class="mt-1 block text-xs muted">e.g. staging.yourclient.com</span></span>
                                </label>
                            </div>
                        </fieldset>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm heading" x-show="source === 'platform'" x-cloak>Subdomain slug
                                <div class="mt-1 flex items-center gap-2">
                                    <input class="field mt-0" name="staging_slug" value="{{ old('staging_slug', Str::slug(Str::before($site->domain, '.'))) }}" placeholder="myapp">
                                    <span class="shrink-0 text-xs muted">.staging.{{ $stagingPlatformDomain }}</span>
                                </div>
                            </label>
                            <label class="text-sm heading" x-show="source === 'custom'" x-cloak>Client staging domain
                                <input class="field" name="domain" value="{{ old('domain') }}" placeholder="staging.example.com">
                            </label>
                            @unless($site->isWordPress())
                                <label class="text-sm heading">Git branch<input class="field" name="branch" value="{{ old('branch', $site->branch === 'main' ? 'staging' : $site->branch) }}"></label>
                            @endunless
                        </div>
                        <button class="button-primary" @disabled($site->status !== 'active')>Create staging site</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
    @if($site->isWordPress())
        @php $wordpressReady = $site->wordpressIsInstalled(); @endphp
        @foreach(['theme' => 'themes', 'plugin' => 'plugins'] as $target => $tabKey)
            <div x-cloak x-show="tab==='{{ $tabKey }}'" class="mt-6 space-y-6">
                @if($wordpressReady)
                    @include('sites.partials.wp-installed', ['target' => $target, 'plural' => $tabKey])
                    @include('sites.partials.wp-directory', ['target' => $target, 'plural' => $tabKey, 'results' => $target === 'theme' ? $directoryThemes : $directoryPlugins])
                @else
                    @include('sites.partials.wp-locked')
                @endif
            </div>
        @endforeach
        <div x-cloak x-show="tab==='backups'" class="mt-6">
            @if($wordpressReady)@include('sites.partials.wp-backups')@else @include('sites.partials.wp-locked') @endif
        </div>
    @endif
    <div x-cloak x-show="tab==='environment'" class="mt-6"><form method="POST" action="{{ route('sites.environment',$site) }}" class="panel">@csrf @method('PUT')<h2 class="font-semibold heading">Encrypted environment</h2><p class="mt-2 text-sm muted">Values are encrypted at rest and written only to the server's shared release directory.</p><textarea class="field mt-5 min-h-[28rem] font-mono text-xs leading-6" name="environment" spellcheck="false">{{ $environment }}</textarea><button class="button-primary mt-5">Save environment</button></form></div>
    <div x-cloak x-show="tab==='deploy'" class="mt-6"><form method="POST" action="{{ route('sites.update',$site) }}" class="panel">@csrf @method('PATCH')<div class="grid gap-5 sm:grid-cols-2"><label class="text-sm heading sm:col-span-2">Repository URL<input class="field font-mono text-xs" name="repository_url" value="{{ $site->repository_url }}" placeholder="https://github.com/acme/app.git"></label><label class="text-sm heading">Branch<input class="field" name="branch" value="{{ $site->branch }}"></label><label class="text-sm heading">PHP version<select class="field" name="php_version">@foreach(['8.4','8.3','8.2'] as $version)<option @selected($site->php_version===$version)>{{ $version }}</option>@endforeach</select></label><label class="flex gap-2 text-sm heading"><input type="checkbox" name="auto_deploy" value="1" @checked($site->auto_deploy)>Automatic deployments</label><label class="flex gap-2 text-sm heading"><input type="checkbox" name="zero_downtime" value="1" @checked($site->zero_downtime)>Zero-downtime releases</label><label class="text-sm heading sm:col-span-2">Custom post-build script<textarea class="field min-h-44 font-mono text-xs" name="deployment_script">{{ $site->deployment_script }}</textarea></label></div><button class="button-primary mt-5">Save settings</button></form></div>
    <div x-cloak x-show="tab==='ssl'" class="mt-6">
        @php $certificate = $site->sslCertificates->sortByDesc('created_at')->first(); @endphp
        <section class="panel">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h2 class="font-semibold heading">{{ $site->domain }}</h2><p class="mt-1 text-sm muted">{{ $certificate ? ucfirst($certificate->status) : 'No certificate' }}@if($certificate?->expires_at) · expires {{ $certificate->expires_at->toFormattedDateString() }}@endif</p></div>
                @if($certificate?->status === 'active')<span class="text-sm font-medium text-emerald-600 dark:text-emerald-300">Secure</span>@endif
            </div>
            <form method="POST" action="{{ route('ssl.store',$site) }}" class="mt-5 flex flex-wrap items-center gap-4">@csrf
                <label class="flex gap-2 text-sm heading"><input type="checkbox" name="force_https" value="1" @checked($certificate?->force_https ?? true)>Force HTTPS</label>
                <label class="flex gap-2 text-sm heading"><input type="checkbox" name="auto_renew" value="1" @checked($certificate?->auto_renew ?? true)>Auto renew</label>
                <button class="button-primary" @disabled($site->status !== 'active')>{{ $certificate ? 'Renew / update' : 'Issue certificate' }}</button>
            </form>
            @if($certificate?->failure_reason)<p class="mt-3 text-xs text-rose-600 dark:text-rose-300">{{ $certificate->failure_reason }}</p>@endif
            @if($site->status !== 'active')<p class="mt-3 text-xs muted">The site must finish configuring before a certificate can be issued.</p>@endif
        </section>
    </div>
    <div x-cloak x-show="tab==='logs'" class="mt-6">@livewire('log-viewer',['site'=>$site])</div>
    <div x-cloak x-show="tab==='cron'" class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
        <form method="POST" action="{{ route('sites.cron-jobs.store',$site) }}" class="panel h-fit">@csrf
            <h2 class="font-semibold heading">Add cron job</h2>
            <p class="mt-1 text-sm muted">Runs on {{ $site->server->name }} for this site.</p>
            <label class="mt-4 block text-sm heading">Name<input class="field" name="name" placeholder="Laravel scheduler"></label>
            <label class="mt-4 block text-sm heading">Expression<input class="field font-mono" name="expression" value="* * * * *"></label>
            <label class="mt-4 block text-sm heading">Command<input class="field font-mono text-xs" name="command" placeholder="cd /var/www/{{ $site->domain }}/current && php artisan schedule:run"></label>
            <button class="button-primary mt-5">Add cron</button>
        </form>
        <div class="space-y-3">
            @forelse($site->cronJobs as $cron)
                <article class="panel">
                    <div class="flex flex-wrap justify-between gap-4">
                        <div><h3 class="font-medium heading">{{ $cron->name }} <span class="text-xs muted capitalize">· {{ $cron->status }}</span></h3><code class="mt-2 block text-xs muted">{{ $cron->expression }} · {{ $cron->command }}</code></div>
                        <div class="flex gap-3">
                            <form method="POST" action="{{ route('cron-jobs.toggle',$cron) }}">@csrf @method('PATCH')<button class="link-action">{{ $cron->enabled ? 'Disable' : 'Enable' }}</button></form>
                            <form method="POST" action="{{ route('cron-jobs.destroy',$cron) }}">@csrf @method('DELETE')<button class="text-sm text-rose-600 dark:text-rose-300">Delete</button></form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="panel text-center muted">No cron jobs for this site.</div>
            @endforelse
        </div>
    </div>
    <div x-cloak x-show="tab==='queue'" class="mt-6 space-y-6">
        <section class="panel">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="font-semibold heading">Failed jobs</h2><p class="mt-1 text-sm muted">Counts rows in this site's own <code>failed_jobs</code> table.</p></div>
                <div class="flex items-center gap-4">
                    @if($site->queue_checked_at)<p class="text-sm heading">{{ $site->queue_failed_count === null ? 'Unable to check' : $site->queue_failed_count.' failed' }} <span class="muted">· checked {{ $site->queue_checked_at->diffForHumans() }}</span></p>@endif
                    <form method="POST" action="{{ route('sites.queue-health',$site) }}">@csrf<button class="button-secondary" @disabled($site->status !== 'active')>Check now</button></form>
                </div>
            </div>
        </section>
        <section class="panel">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="font-semibold heading">Horizon &amp; Reverb</h2><p class="mt-1 text-sm muted">Detected in the currently deployed release, from <code>composer show</code>.</p></div>
                <form method="POST" action="{{ route('site-packages.check',$site) }}">@csrf<button class="button-secondary text-xs" @disabled($site->status !== 'active')>Refresh detection</button></form>
            </div>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                @php $installed = $site->installed_packages ?? []; $managed = $site->managed_packages ?? []; @endphp
                @foreach(['laravel/horizon' => 'Horizon', 'laravel/reverb' => 'Reverb'] as $package => $label)
                    @php $version = $installed[$package] ?? null; @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <div>
                            <span class="{{ $version ? 'text-emerald-600 dark:text-emerald-300' : 'muted' }}">{{ $label }} {{ $version ? '· '.$version.' installed' : '· not detected' }}</span>
                            @if(in_array($package, $managed, true))<span class="badge ml-2 bg-cyan-50 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">Kept on every deploy</span>@endif
                            @if($package === 'laravel/horizon' && $version)<p class="mt-1 text-xs muted">Dashboard: <a class="text-cyan-600 dark:text-cyan-300" href="https://{{ $site->domain }}/horizon" target="_blank" rel="noopener">https://{{ $site->domain }}/horizon</a></p>@endif
                        </div>
                        <div class="flex gap-3">
                            @if(!in_array($package, $managed, true))
                                <form method="POST" action="{{ route('site-packages.store',$site) }}">@csrf<input type="hidden" name="package" value="{{ $package }}"><button class="text-xs font-medium text-cyan-600 dark:text-cyan-300" @disabled($site->status !== 'active')>{{ $version ? 'Keep on every deploy' : 'Install' }}</button></form>
                            @else
                                <form method="POST" action="{{ route('site-packages.destroy',$site) }}">@csrf @method('DELETE')<input type="hidden" name="package" value="{{ $package }}"><button class="text-xs font-medium text-rose-600 dark:text-rose-300">Stop keeping</button></form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if(isset($installed['laravel/horizon']))
                <form method="POST" action="{{ route('site-horizon-admins.update',$site) }}" class="mt-5 border-t border-slate-100 pt-5 dark:border-white/5">@csrf
                    <label class="block text-sm heading">Horizon dashboard access<span class="mt-1 block text-xs font-normal muted">Emails of your app's own users allowed to view <code>/horizon</code>. Comma or newline separated. Takes effect immediately — no redeploy needed.</span></label>
                    <textarea class="field mt-2 min-h-20 font-mono text-xs" name="emails" placeholder="admin@example.com">{{ implode("\n", $site->horizon_admin_emails ?? []) }}</textarea>
                    <button class="button-secondary mt-3 text-xs">Save access list</button>
                </form>
            @endif
        </section>
        <section class="panel">
            <h2 class="font-semibold heading">Supervisor processes</h2>
            <p class="mt-1 text-sm muted">Runs <code>php artisan horizon</code> or <code>reverb:start</code> under Supervisor once the package above is installed.</p>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                @forelse($site->queueWorkers as $worker)
                    <div class="py-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="heading">{{ $worker->name }} · <span class="capitalize">{{ $worker->type }}</span>{{ $worker->type === 'queue' ? ' · '.$worker->processes.' processes · '.$worker->queue : '' }}{{ $worker->type === 'reverb' ? ' · ws://'.$site->server->public_ip.':'.$worker->port : '' }}</span>
                            <form method="POST" action="{{ route('workers.status',$worker) }}">@csrf<button class="text-xs font-medium text-cyan-600 dark:text-cyan-300">Check status</button></form>
                        </div>
                        @if($worker->runtime_status)<p class="mt-1 text-xs {{ in_array($worker->runtime_status,['RUNNING','STARTING'],true) ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}">Supervisor: {{ $worker->runtime_status }} · checked {{ $worker->runtime_checked_at?->diffForHumans() }}</p>@endif
                    </div>
                @empty
                    <p class="py-5 text-center text-sm muted">No workers configured yet. Add one from this server's <a class="text-cyan-600 dark:text-cyan-300" href="{{ route('servers.manage',$site->server) }}">management page</a>.</p>
                @endforelse
            </div>
        </section>
    </div>
    <div x-cloak x-show="tab==='webhook'" class="mt-6"><div class="panel"><h2 class="font-semibold heading">Automatic deployment webhook</h2><p class="mt-2 text-sm muted">Configure GitHub or Bitbucket with the endpoint and HMAC secret. GitLab may send the secret as <code>X-Gitlab-Token</code>.</p><label class="mt-5 block text-sm heading">Endpoint<code class="mt-2 block break-all rounded-xl bg-slate-100 p-3 text-cyan-700 dark:bg-black/30 dark:text-cyan-200">{{ route('webhooks.site',$site) }}</code></label><label class="mt-4 block text-sm heading">Secret<code class="mt-2 block break-all rounded-xl bg-slate-100 p-3 text-amber-700 dark:bg-black/30 dark:text-amber-200">{{ $site->webhook_secret }}</code></label><p class="mt-4 text-xs muted">Only pushes to <b>{{ $site->branch }}</b> are deployed. Duplicate commit hashes are ignored.</p></div></div>
    <div x-cloak x-show="tab==='monitoring'" class="mt-6 space-y-6">
        <section class="panel">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold heading">Website monitoring</h2>
                    <p class="mt-1 text-sm muted">
                        {{ $site->site_monitoring_enabled ? 'Enabled' : 'Disabled' }}.
                        Probes HTTP availability and DNS against {{ $site->server->public_ip ?? 'the server IP' }} every minute when enabled.
                        @unless($site->isWordPress()) Laravel failed-job checks also run every 15 minutes. @endunless
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if($site->site_monitoring_enabled)
                        <form method="POST" action="{{ route('sites.monitoring.check', $site) }}">@csrf<button class="button-secondary" @disabled($site->status !== 'active')>Check now</button></form>
                        <form method="POST" action="{{ route('sites.monitoring.disable', $site) }}">@csrf @method('DELETE')<button class="button-secondary text-rose-600 dark:text-rose-300">Disable</button></form>
                    @else
                        <form method="POST" action="{{ route('sites.monitoring.enable', $site) }}" class="flex flex-wrap items-end gap-3">@csrf
                            <label class="text-sm heading">Path<input class="field mt-1 !w-36 font-mono text-xs" name="monitor_path" value="{{ $site->monitor_path ?: '/' }}" placeholder="/"></label>
                            <button class="button-primary" @disabled($site->status !== 'active')>Enable monitoring</button>
                        </form>
                    @endif
                </div>
            </div>
            @if($site->status !== 'active')
                <p class="mt-3 text-xs muted">The site must be active before monitoring can run.</p>
            @endif
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-white/10">
                    <p class="text-xs uppercase tracking-wide muted">HTTP status</p>
                    <p class="mt-2 text-sm font-medium {{ $site->monitor_last_status === 'up' ? 'text-emerald-600 dark:text-emerald-300' : ($site->monitor_last_status === 'down' ? 'text-rose-600 dark:text-rose-300' : 'heading') }}">
                        {{ $site->monitor_last_status ? ucfirst($site->monitor_last_status) : 'Not checked' }}
                    </p>
                    <p class="mt-1 text-xs muted">{{ $site->monitor_last_checked_at?->diffForHumans() ?? '—' }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-white/10">
                    <p class="text-xs uppercase tracking-wide muted">Latency</p>
                    <p class="mt-2 text-sm font-medium heading">{{ $site->monitor_last_latency_ms !== null ? $site->monitor_last_latency_ms.' ms' : '—' }}</p>
                    <p class="mt-1 text-xs muted truncate" title="{{ $site->monitorUrl() }}">{{ $site->monitorUrl() }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-white/10">
                    <p class="text-xs uppercase tracking-wide muted">DNS</p>
                    <p class="mt-2 text-sm font-medium {{ $site->dns_last_status === 'ok' ? 'text-emerald-600 dark:text-emerald-300' : ($site->dns_last_status === 'mismatch' ? 'text-amber-600 dark:text-amber-300' : 'heading') }}">
                        {{ $site->dns_last_status ? str_replace('_', ' ', ucfirst($site->dns_last_status)) : 'Not checked' }}
                    </p>
                    <p class="mt-1 text-xs muted">{{ $site->dns_last_checked_at?->diffForHumans() ?? '—' }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-white/10">
                    <p class="text-xs uppercase tracking-wide muted">Consecutive failures</p>
                    <p class="mt-2 text-sm font-medium heading">{{ $site->monitor_consecutive_down }} / {{ $site->monitor_consecutive_failures }}</p>
                    <p class="mt-1 text-xs muted">Cooldown {{ $site->monitor_cooldown_minutes }} min</p>
                </div>
            </div>
            @if($site->monitor_last_error || $site->dns_last_error)
                <div class="mt-4 space-y-1 text-sm text-rose-600 dark:text-rose-300">
                    @if($site->monitor_last_error)<p>HTTP: {{ $site->monitor_last_error }}</p>@endif
                    @if($site->dns_last_error)<p>DNS: {{ $site->dns_last_error }}</p>@endif
                </div>
            @endif
        </section>
        <section class="panel">
            <h2 class="font-semibold heading">Incidents</h2>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                @forelse($site->monitorIncidents as $incident)
                    <div class="py-3">
                        <div class="flex flex-wrap justify-between gap-3">
                            <span class="text-sm heading">{{ $incident->message }}</span>
                            <span class="text-xs uppercase {{ $incident->status === 'open' ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-300' }}">{{ $incident->status }}</span>
                        </div>
                        <p class="mt-1 text-xs muted">{{ str_replace('_', ' ', $incident->type) }} · started {{ $incident->started_at->diffForHumans() }}@if($incident->resolved_at) · resolved {{ $incident->resolved_at->diffForHumans() }}@endif</p>
                    </div>
                @empty
                    <p class="py-5 text-sm muted">No monitoring incidents yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
