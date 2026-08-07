@extends('layouts.app')
@section('content')
@php
    $serverTabKeys = ['monitoring','databases','backups','cron','workers','services'];
    $initialServerTab = in_array(request('tab'), $serverTabKeys, true) ? request('tab') : 'monitoring';
@endphp
<div class="app-main" x-data="managedTabs({ tab: @js($initialServerTab), keys: @js($serverTabKeys) })" @submit.capture="ensureTab($event)">
    <div><a href="/dashboard" class="link-action">← Dashboard</a><h1 class="page-title">{{ $server->name }}</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">@if($server->public_ip)<button type="button" class="font-mono hover:text-cyan-600 dark:hover:text-cyan-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400 rounded" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($server->public_ip)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })" :title="copied ? 'Copied!' : 'Copy IP address'" aria-label="Copy IP address"><span x-text="copied ? 'Copied' : @js($server->public_ip)">{{ $server->public_ip }}</span></button> · @endif{{ $server->region }} · <span class="capitalize">{{ $server->status->value }}</span></p></div>
    @if($errors->any())<div class="mt-5 rounded-xl border border-rose-200 dark:border-rose-400/20 bg-rose-50 dark:bg-rose-400/10 p-4 text-sm text-rose-700 dark:text-rose-200">{{ $errors->first() }}</div>@endif
    @if($server->status === \App\Enums\ServerStatus::Failed && $server->provider_id && $server->public_ip)<div class="mt-5 rounded-xl border border-rose-200 dark:border-rose-400/20 bg-rose-50 dark:bg-rose-400/10 p-4"><p class="text-sm text-rose-700 dark:text-rose-200">Bootstrap failed: {{ $server->failure_reason }}</p><form method="POST" action="{{ route('servers.retry-provisioning',$server) }}" class="mt-3">@csrf<button class="button-primary">Retry server bootstrap</button></form></div>@endif
    @can('transfer', $server)
        <details class="panel mt-5"><summary class="cursor-pointer font-medium">Workspace ownership</summary><p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $server->team ? 'Shared with '.$server->team->name : 'Personal workspace' }}. Transferring changes who can view and operate this server.</p><form method="POST" action="{{ route('servers.team.update',$server) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">@csrf @method('PATCH')<select class="field mt-0" name="team_id">@if($server->user_id===auth()->id())<option value="">Personal workspace</option>@endif @foreach($transferTeams as $team)<option value="{{ $team->id }}" @selected($server->team_id===$team->id)>{{ $team->name }}</option>@endforeach</select><input class="field mt-0" name="confirmation" placeholder="Type {{ $server->hostname }} to confirm"><button class="button-secondary">Transfer</button></form></details>
    @endcan
    @can('delete', $server)
        <details id="danger-zone" class="panel mt-5 border-rose-200 dark:border-rose-400/20"><summary class="cursor-pointer font-medium text-rose-600 dark:text-rose-300">Danger zone</summary><p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Permanently removes this server from {{ $branding['name'] }}{{ $server->provider_id ? ' and destroys its Droplet at the provider' : '' }}. Attached sites must be deleted first. This cannot be undone.</p><form method="POST" action="{{ route('servers.destroy',$server) }}" class="mt-4 flex flex-wrap gap-3" onsubmit="return confirm('Permanently delete {{ $server->hostname }}{{ $server->provider_id ? ' and destroy its Droplet' : '' }}?')">@csrf @method('DELETE')<input class="field mt-0" name="confirmation" placeholder="Type {{ $server->hostname }} to confirm"><button class="button-secondary text-rose-600 dark:text-rose-300">Delete server</button></form></details>
    @endcan
    @if(session('database_password'))<div class="mt-5 rounded-xl border border-amber-200 dark:border-amber-400/20 bg-amber-50 dark:bg-amber-400/10 p-4"><p class="text-sm text-amber-700 dark:text-amber-200">Copy the database password now. It will not be shown again.</p><code class="mt-2 block break-all">{{ session('database_password') }}</code></div>@endif
    <div class="mt-8 flex gap-2 overflow-x-auto border-b border-slate-200 dark:border-white/10">@foreach(['monitoring'=>'Monitoring','databases'=>'Databases','backups'=>'Backups','cron'=>'Cron','workers'=>'Workers','services'=>'Services'] as $key=>$label)<button @click="tab='{{ $key }}'" :class="tab==='{{ $key }}'?'border-cyan-400 text-slate-900 dark:text-white':'border-transparent text-slate-500 dark:text-slate-400'" class="border-b-2 px-4 py-3 text-sm">{{ $label }}</button>@endforeach</div>

    <div x-show="tab==='databases'" class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]"><form method="POST" action="{{ route('databases.store',$server) }}" class="panel h-fit">@csrf<h2 class="font-semibold">Create database</h2><label class="mt-4 block text-sm">Engine<select class="field" name="engine"><option value="mysql">MySQL / MariaDB</option><option value="postgresql">PostgreSQL</option></select></label><label class="mt-4 block text-sm">Database name<input class="field" name="name" placeholder="application"></label><label class="mt-4 block text-sm">Username<input class="field" name="username" placeholder="application_user"></label><label class="mt-4 block text-sm">Attach to site<select class="field" name="site_id"><option value="">None</option>@foreach($server->sites as $site)<option value="{{ $site->id }}">{{ $site->domain }}</option>@endforeach</select></label><button class="button-primary mt-5">Create database</button></form><div class="space-y-3">@forelse($server->databases as $database)<article class="panel flex items-center justify-between gap-4"><div><h3 class="font-medium">{{ $database->name }}</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $database->engine }} · {{ $database->username }} · <span class="capitalize">{{ $database->status }}</span></p>@if($database->failure_reason)<p class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ $database->failure_reason }}</p>@endif</div><form method="POST" action="{{ route('databases.destroy',$database) }}">@csrf @method('DELETE')<button class="text-sm text-rose-600 dark:text-rose-300">Delete</button></form></article>@empty<div class="panel text-center text-slate-500 dark:text-slate-400">No managed databases.</div>@endforelse</div></div>

    <div x-show="tab==='databases'" class="mt-6 panel">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold">phpMyAdmin</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if($server->phpmyadmin_enabled)
                        Available at <a class="text-cyan-600 dark:text-cyan-300" href="http://{{ $server->public_ip }}:{{ $server->phpmyadmin_port }}" target="_blank" rel="noopener">http://{{ $server->public_ip }}:{{ $server->phpmyadmin_port }}</a> — log in with any database username and password created above.
                    @else
                        Not installed. Installing exposes a login screen at your server's public IP; log in with a database username and password created above.
                    @endif
                </p>
            </div>
            <div class="flex gap-3">
                @if($server->phpmyadmin_enabled)
                    <form method="POST" action="{{ route('phpmyadmin.destroy',$server) }}" onsubmit="return confirm('Remove phpMyAdmin from this server?')">@csrf @method('DELETE')<button class="button-secondary text-rose-600 dark:text-rose-300">Remove</button></form>
                @else
                    <form method="POST" action="{{ route('phpmyadmin.store',$server) }}" class="flex items-center gap-2">@csrf<input class="field mt-0 !w-28" type="number" name="port" min="1024" max="65535" value="{{ app(\App\Services\ServerPortRegistry::class)->allocate($server, \App\Services\ServerPortRegistry::PHPMYADMIN_DEFAULT) }}" title="Port (8080 is reserved for Laravel Reverb)"><button class="button-primary" @disabled($server->status !== \App\Enums\ServerStatus::Ready)>Install phpMyAdmin</button></form>
                @endif
            </div>
        </div>
    </div>


    @php
        $laravelCronSites = $server->sites->reject(fn ($site) => $site->isWordPress())->values();
    @endphp
    <div x-cloak x-show="tab==='cron'" class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
        <form method="POST" action="{{ route('cron-jobs.store',$server) }}" class="panel h-fit"
              x-data="{
                  preset: 'custom',
                  name: '',
                  expression: '* * * * *',
                  command: '',
                  apply(key, values) {
                      this.preset = key;
                      this.name = values.name;
                      this.expression = values.expression;
                      this.command = values.command;
                  }
              }">
            @csrf
            <h2 class="font-semibold">Add server cron job</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Runs as root, unattached to a site. Prefer each site's Cron tab for Laravel schedulers.</p>
            <div class="mt-4" data-cron-presets>
                <p class="text-sm">Preset</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button"
                            @click="apply('custom', { name: '', expression: '* * * * *', command: '' })"
                            :class="preset === 'custom' ? 'border-cyan-400 bg-cyan-50/50 text-slate-900 dark:border-cyan-400/40 dark:bg-cyan-400/5 dark:text-white' : 'border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300'"
                            class="rounded border px-2.5 py-1 text-xs font-medium transition">Custom</button>
                    @foreach($laravelCronSites as $cronSite)
                        @php $schedulerCommand = 'cd /var/www/'.$cronSite->domain.'/current && php artisan schedule:run'; @endphp
                        <button type="button"
                                data-cron-command="{{ $schedulerCommand }}"
                                @click="apply(@js($cronSite->id), { name: 'Laravel scheduler', expression: '* * * * *', command: $el.dataset.cronCommand })"
                                :class="preset === @js($cronSite->id) ? 'border-cyan-400 bg-cyan-50/50 text-slate-900 dark:border-cyan-400/40 dark:bg-cyan-400/5 dark:text-white' : 'border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300'"
                                class="rounded border px-2.5 py-1 text-xs font-medium transition"
                                title="{{ $schedulerCommand }}">Laravel · {{ $cronSite->domain }}</button>
                    @endforeach
                </div>
                @if($laravelCronSites->isEmpty())
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Add a Laravel site to auto-fill <code>schedule:run</code> with <code>/var/www/{domain}/current</code>.</p>
                @endif
            </div>
            <label class="mt-4 block text-sm">Name<input class="field" name="name" x-model="name" placeholder="Laravel scheduler"></label>
            <label class="mt-4 block text-sm">Expression<input class="field font-mono" name="expression" x-model="expression" placeholder="* * * * *"></label>
            <label class="mt-4 block text-sm">Command<input class="field font-mono text-xs" name="command" x-model="command" placeholder="cd /var/www/your-site.test/current && php artisan schedule:run"></label>
            <button class="button-primary mt-5">Add cron</button>
        </form>
        <div class="space-y-3">
            @forelse($server->cronJobs->whereNull('site_id') as $cron)
                <article class="panel">
                    <div class="flex justify-between gap-4">
                        <div>
                            <h3>{{ $cron->name }}</h3>
                            <code class="mt-2 block text-xs text-slate-500 dark:text-slate-400">{{ $cron->expression }} · {{ $cron->command }}</code>
                        </div>
                        <div class="flex gap-3">
                            <form method="POST" action="{{ route('cron-jobs.toggle',$cron) }}">@csrf @method('PATCH')<button class="link-action">{{ $cron->enabled?'Disable':'Enable' }}</button></form>
                            <form method="POST" action="{{ route('cron-jobs.destroy',$cron) }}">@csrf @method('DELETE')<button class="text-sm text-rose-600 dark:text-rose-300">Delete</button></form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="panel text-center text-slate-500 dark:text-slate-400">No server-level cron jobs.</div>
            @endforelse
        </div>
    </div>

    <div x-cloak x-show="tab==='workers'" class="mt-6"><div class="grid gap-6 lg:grid-cols-2">@foreach($server->sites as $site)<section class="panel"><h2 class="font-semibold">{{ $site->domain }}</h2>
        <div class="mt-3 flex flex-wrap items-center gap-3"><span class="text-xs text-slate-500 dark:text-slate-400">Install the package into the currently deployed release:</span>
            <form method="POST" action="{{ route('site-packages.store',$site) }}">@csrf<input type="hidden" name="package" value="laravel/horizon"><button class="button-secondary text-xs" @disabled($site->status !== 'active')>Install Horizon</button></form>
            <form method="POST" action="{{ route('site-packages.store',$site) }}">@csrf<input type="hidden" name="package" value="laravel/reverb"><button class="button-secondary text-xs" @disabled($site->status !== 'active')>Install Reverb</button></form>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Runs against the live release only — add the package to this repository's <code>composer.json</code> so it isn't lost on the next deployment. Output appears on the site's <a class="text-cyan-600 dark:text-cyan-300" href="{{ route('sites.remote',['site'=>$site,'tab'=>'terminal']) }}">terminal tab</a>.</p>
        <form method="POST" action="{{ route('workers.store',$site) }}" class="mt-4 grid gap-3 sm:grid-cols-2" x-data="{ type: 'queue' }">@csrf
            <input class="field sm:col-span-2" name="name" placeholder="default-worker">
            <select class="field sm:col-span-2" name="type" x-model="type">
                <option value="queue">Queue worker (queue:work)</option>
                <option value="horizon">Horizon</option>
                <option value="reverb">Reverb (WebSockets)</option>
            </select>
            <template x-if="type==='queue'"><input class="field" name="queue" value="default" placeholder="Queue name"></template>
            <template x-if="type==='queue'"><input class="field" name="connection" value="redis" placeholder="Connection"></template>
            <template x-if="type==='queue'"><input class="field" type="number" name="processes" value="1" min="1" max="20" placeholder="Processes"></template>
            <template x-if="type==='reverb'"><input class="field sm:col-span-2" type="number" name="port" value="6001" min="1024" max="65535" placeholder="WebSocket port"></template>
            <p x-show="type!=='queue'" class="text-xs text-slate-500 dark:text-slate-400 sm:col-span-2">Runs <code x-text="type==='horizon' ? 'php artisan horizon' : 'php artisan reverb:start'"></code> under Supervisor. Requires <code x-text="type==='horizon' ? 'laravel/horizon' : 'laravel/reverb'"></code> already installed above (or in your repository).</p>
            <button class="button-primary sm:col-span-2">Add worker</button>
        </form>
        <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">@foreach($site->queueWorkers as $worker)<div class="py-3 text-sm"><div class="flex items-center justify-between gap-3"><span>{{ $worker->name }} · <span class="capitalize">{{ $worker->type }}</span>{{ $worker->type === 'queue' ? ' · '.$worker->processes.' processes · '.$worker->queue : '' }}{{ $worker->type === 'reverb' ? ' · port '.$worker->port : '' }}</span><div class="flex items-center gap-3"><form method="POST" action="{{ route('workers.status',$worker) }}">@csrf<button class="text-xs text-cyan-600 dark:text-cyan-300">Check status</button></form><form method="POST" action="{{ route('workers.destroy',$worker) }}">@csrf @method('DELETE')<button class="text-rose-600 dark:text-rose-300">Remove</button></form></div></div>@if($worker->runtime_status)<p class="mt-1 text-xs {{ in_array($worker->runtime_status,['RUNNING','STARTING'],true) ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}">Supervisor: {{ $worker->runtime_status }} · checked {{ $worker->runtime_checked_at?->diffForHumans() }}</p>@endif</div>@endforeach</div>
    </section>@endforeach</div></div>

    @include('servers.partials.backups')

    <div x-cloak x-show="tab==='services'" class="mt-6">
        <div class="panel"><h2 class="font-semibold">Service controls</h2><div class="mt-5 flex flex-wrap gap-3">@foreach(['nginx:test'=>'Test Nginx','nginx:reload'=>'Reload Nginx','nginx:restart'=>'Restart Nginx','php:reload'=>'Reload PHP','php:restart'=>'Restart PHP','supervisor:restart'=>'Restart workers','redis:restart'=>'Restart Redis','mysql:restart'=>'Restart MySQL'] as $type=>$label)<form method="POST" action="{{ route('server-operations.store',$server) }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><button class="button-secondary">{{ $label }}</button></form>@endforeach</div></div>
        <div class="mt-6 panel">
            <h2 class="font-semibold">Maintenance</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Queued on the operations worker. Output appears in the history below when the job finishes. Hardening is additive and does not reset Firewall console rules.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('server-operations.store',$server) }}" onsubmit="return confirm('Run software hardening on {{ $server->hostname }}? SSH password auth will be disabled if a key is in use.')">@csrf<input type="hidden" name="type" value="system:harden"><button class="button-secondary">Software hardening</button></form>
                <form method="POST" action="{{ route('server-operations.store',$server) }}" onsubmit="return confirm('Install available Ubuntu package updates on {{ $server->hostname }}? Services may briefly restart.')">@csrf<input type="hidden" name="type" value="system:update"><button class="button-primary">Update Ubuntu packages</button></form>
            </div>
            <details class="mt-5 rounded-xl border border-amber-200 dark:border-amber-400/20 bg-amber-50 dark:bg-amber-400/10 p-4">
                <summary class="cursor-pointer font-medium text-amber-800 dark:text-amber-200">Major release upgrade</summary>
                <p class="mt-3 text-sm text-amber-900/80 dark:text-amber-100/80">Runs <code class="text-xs">do-release-upgrade</code> noninteractively. This can break PHP, Nginx, or MySQL packages and usually requires a reboot. Prefer package updates above unless you intentionally need a new Ubuntu LTS.</p>
                <form method="POST" action="{{ route('server-operations.store',$server) }}" class="mt-4 flex flex-wrap gap-3" onsubmit="return confirm('Start a major Ubuntu release upgrade on {{ $server->hostname }}? This is disruptive.')">@csrf<input type="hidden" name="type" value="system:release-upgrade"><input class="field mt-0" name="confirmation" placeholder="Type {{ $server->hostname }} to confirm"><button class="button-secondary text-amber-700 dark:text-amber-300">Upgrade Ubuntu release</button></form>
            </details>
        </div>
        <div class="mt-6 panel"><h2 class="font-semibold">PHP extensions</h2><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Installs the extension for every PHP-FPM version on this server and reloads it, so a mismatched site PHP version can't cause it to silently not apply.</p><form method="POST" action="{{ route('php-extensions.store',$server) }}" class="mt-4 flex flex-wrap items-center gap-3">@csrf<select class="field mt-0" name="extension">@foreach(['intl'=>'intl (i18n / Krayin, e-commerce apps)','gd'=>'gd (image processing)','imagick'=>'imagick (ImageMagick bindings)','soap'=>'soap','xsl'=>'xsl (XSLT transforms)','ldap'=>'ldap','imap'=>'imap','sqlite3'=>'sqlite3','gmp'=>'gmp (arbitrary precision math)','bz2'=>'bz2','dba'=>'dba'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><button class="button-primary">Install extension</button></form></div>
        <div class="mt-5 space-y-3">@foreach($server->operations as $operation)<article class="panel"><div class="flex justify-between"><span>{{ $operation->type }}</span><span class="text-sm capitalize">{{ $operation->status }}</span></div>@if($operation->output)<pre class="mt-3 max-h-48 overflow-auto whitespace-pre-wrap rounded-xl bg-slate-100 dark:bg-black/40 p-3 text-xs">{{ $operation->output }}</pre>@endif</article>@endforeach</div>
    </div>
    {{-- Default tab: no x-cloak so content stays visible if Alpine fails to boot. --}}
    <div x-show="tab==='monitoring'" class="mt-6 space-y-6">
        @php $latestMetric = $server->metrics->sortByDesc('recorded_at')->first(); @endphp
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([['CPU',$latestMetric?->cpu_percent],['Memory',$latestMetric?->memory_percent],['Disk',$latestMetric?->disk_percent],['Load',$latestMetric?->load_average]] as [$label,$value])
                <div class="panel p-5"><p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p><p class="page-title">{{ $value ?? '-' }}{{ $value !== null && $label !== 'Load' ? '%' : '' }}</p></div>
            @endforeach
        </div>
        @php
            $chartMetrics = $server->metrics->sortBy('recorded_at')->values();
            $chartPoints = function (string $field) use ($chartMetrics): string {
                $count = max($chartMetrics->count() - 1, 1);
                return $chartMetrics->map(fn ($metric, $index) => number_format($index * 600 / $count, 2, '.', '').','.number_format(150 - min((float) $metric->{$field}, 100) * 1.4, 2, '.', ''))->implode(' ');
            };
        @endphp
        <section class="panel"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold">Resource history</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The latest {{ $chartMetrics->count() }} one-minute samples</p></div><div class="flex gap-4 text-xs"><span class="text-cyan-600 dark:text-cyan-300">CPU</span><span class="text-violet-600 dark:text-violet-300">Memory</span><span class="text-amber-600 dark:text-amber-300">Disk</span></div></div>{{-- A polyline through a single point draws nothing, which reads as a broken chart while
     the agent is in fact reporting. One sample gets dots and says so; the line appears as
     soon as there are two points to draw a line between. --}}
@if($chartMetrics->count() > 1)<svg class="mt-5 h-52 w-full" viewBox="0 0 600 160" role="img" aria-label="CPU, memory, and disk usage history"><path d="M0 10H600 M0 80H600 M0 150H600" stroke="currentColor" class="text-slate-200 dark:text-white/10"/><polyline points="{{ $chartPoints('cpu_percent') }}" fill="none" stroke="rgb(103 232 249)" stroke-width="3"/><polyline points="{{ $chartPoints('memory_percent') }}" fill="none" stroke="rgb(196 181 253)" stroke-width="3"/><polyline points="{{ $chartPoints('disk_percent') }}" fill="none" stroke="rgb(252 211 77)" stroke-width="3"/></svg>@elseif($chartMetrics->count() === 1)<svg class="mt-5 h-52 w-full" viewBox="0 0 600 160" role="img" aria-label="First CPU, memory, and disk sample"><path d="M0 10H600 M0 80H600 M0 150H600" stroke="currentColor" class="text-slate-200 dark:text-white/10"/>@foreach(['cpu_percent'=>'rgb(103 232 249)','memory_percent'=>'rgb(196 181 253)','disk_percent'=>'rgb(252 211 77)'] as $field=>$colour)<circle cx="300" cy="{{ number_format(150 - min((float) $chartMetrics->first()->{$field}, 100) * 1.4, 2, '.', '') }}" r="5" fill="{{ $colour }}"/>@endforeach</svg><p class="mt-3 text-sm text-slate-500 dark:text-slate-400">One sample so far. The agent reports every minute, so the trend fills in shortly.</p>@else<p class="mt-5 text-sm text-slate-500 dark:text-slate-400">Waiting for the first signed sample.</p>@endif</section>
        <section class="panel">
            <div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="font-semibold">Metric agent</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $server->monitoring_enabled ? 'Enabled' : 'Disabled' }} / Last seen {{ $server->last_seen_at?->diffForHumans() ?? 'never' }}</p></div><div class="flex gap-3"><a class="button-secondary" href="{{ route('monitoring.agent',$server) }}">Download agent</a><form method="POST" action="{{ route('monitoring.rotate',$server) }}">@csrf<button class="button-primary">{{ $server->monitoring_enabled ? 'Rotate secret' : 'Enable monitoring' }}</button></form>@if($server->monitoring_enabled)<form method="POST" action="{{ route('monitoring.disable',$server) }}">@csrf @method('DELETE')<button class="button-secondary text-rose-600 dark:text-rose-300">Disable</button></form>@endif</div></div>
            @if(session('monitoring_secret'))<div class="mt-5 rounded-xl border border-amber-200 dark:border-amber-400/20 bg-amber-50 dark:bg-amber-400/10 p-4"><p class="text-sm text-amber-700 dark:text-amber-200">Copy this configuration now. The secret will not be shown again.</p><pre class="mt-3 overflow-auto whitespace-pre-wrap text-xs">CLOUDDECK_URL={{ url('/') }}
CLOUDDECK_SERVER_ID={{ $server->id }}
CLOUDDECK_MONITORING_SECRET={{ session('monitoring_secret') }}</pre><p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Save as /etc/clouddeck-monitor.conf with mode 600, install the agent as /usr/local/bin/clouddeck-monitor, and run it every minute from root's cron.</p></div>@endif
            @if($latestMetric?->services)<div class="mt-5 flex flex-wrap gap-2">@foreach($latestMetric->services as $service=>$running)<span class="rounded-full px-3 py-1 text-xs {{ $running ? 'bg-emerald-50 dark:bg-emerald-400/10 text-emerald-600 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-400/10 text-rose-600 dark:text-rose-300' }}">{{ str_replace('_',' ',$service) }} {{ $running ? 'up' : 'down' }}</span>@endforeach</div>@endif
        </section>
        @if($server->monitoring_enabled)
        <section class="panel">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Auto-heal</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $server->auto_heal_enabled ? 'Enabled' : 'Disabled' }}.
                        When enabled, Nginx, PHP-FPM, MySQL, Redis, and Supervisor are restarted after {{ $server->auto_heal_consecutive_samples }} consecutive down samples, with a {{ $server->auto_heal_cooldown_minutes }}-minute cooldown per service.
                    </p>
                </div>
                <div class="flex gap-3">
                    @if($server->auto_heal_enabled)
                        <form method="POST" action="{{ route('auto-heal.disable', $server) }}">@csrf @method('DELETE')<button class="button-secondary text-rose-600 dark:text-rose-300">Disable auto-heal</button></form>
                    @else
                        <form method="POST" action="{{ route('auto-heal.enable', $server) }}">@csrf<button class="button-primary">Enable auto-heal</button></form>
                    @endif
                </div>
            </div>
            @if($server->auto_heal_enabled && $server->auto_heal_last_actions)
                <div class="mt-5 divide-y divide-slate-100 dark:divide-white/5">
                    @foreach($server->auto_heal_last_actions as $service => $queuedAt)
                        <div class="flex justify-between gap-3 py-3 text-sm">
                            <span class="capitalize">{{ str_replace('_', ' ', $service) }}</span>
                            <span class="text-slate-500 dark:text-slate-400">Last queued {{ \Illuminate\Support\Carbon::parse($queuedAt)->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
        @endif
        <div class="grid gap-6 lg:grid-cols-[380px_1fr]">
            <form method="POST" action="{{ route('alert-rules.store',$server) }}" class="panel h-fit">@csrf<h2 class="font-semibold">Create alert rule</h2><label class="mt-4 block text-sm">Name<input class="field" name="name" placeholder="High memory"></label><label class="mt-4 block text-sm">Metric<select class="field" name="metric"><option value="cpu_percent">CPU percent</option><option value="memory_percent">Memory percent</option><option value="disk_percent">Disk percent</option><option value="load_average">Load average</option><option value="server_offline">Offline minutes</option></select></label><div class="grid grid-cols-2 gap-3"><label class="mt-4 block text-sm">Operator<select class="field" name="operator"><option value="gte">At least</option><option value="gt">Greater than</option><option value="lte">At most</option><option value="lt">Less than</option></select></label><label class="mt-4 block text-sm">Threshold<input class="field" type="number" step="0.01" name="threshold" value="90"></label></div><div class="grid grid-cols-2 gap-3"><label class="mt-4 block text-sm">Samples<input class="field" type="number" name="consecutive_samples" min="1" max="12" value="3"></label><label class="mt-4 block text-sm">Cooldown<input class="field" type="number" name="cooldown_minutes" min="5" value="30"></label></div><label class="mt-4 block text-sm">Severity<select class="field" name="severity"><option value="warning">Warning</option><option value="critical">Critical</option><option value="info">Info</option></select></label><button class="button-primary mt-5">Create rule</button></form>
            <div class="space-y-3">@forelse($server->alertRules as $rule)<article class="panel flex items-center justify-between gap-4"><div><h3 class="font-medium">{{ $rule->name }}</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $rule->metric }} {{ $rule->operator }} {{ $rule->threshold }} / {{ $rule->consecutive_samples }} samples / {{ $rule->severity }}</p></div><form method="POST" action="{{ route('alert-rules.destroy',$rule) }}">@csrf @method('DELETE')<button class="text-sm text-rose-600 dark:text-rose-300">Delete</button></form></article>@empty<div class="panel text-center text-slate-500 dark:text-slate-400">No alert rules.</div>@endforelse</div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="panel">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold">Recent incidents</h2>
                    <a href="{{ route('notifications.index', ['tab' => 'incidents', 'server' => $server->id]) }}" class="link-action text-sm">View all incidents</a>
                </div>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                    @forelse($server->alertIncidents as $incident)
                        <div class="py-3">
                            <div class="flex justify-between gap-3"><span>{{ $incident->message }}</span><span class="text-xs uppercase {{ $incident->status === 'open' ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-300' }}">{{ $incident->status }}</span></div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $incident->metric }} {{ $incident->value }} / threshold {{ $incident->threshold }} / {{ $incident->started_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <p class="py-5 text-sm text-slate-500 dark:text-slate-400">No incidents on this server.</p>
                    @endforelse
                </div>
            </section>
            <section class="panel">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold heading">Email notifications</h2>
                    <a href="{{ route('notifications.index', ['tab' => 'email']) }}" class="link-action text-sm">Manage notifications</a>
                </div>
                <p class="mt-2 text-sm muted">Recipients are account-wide. Add mailboxes and choose which events they receive from the Notifications page.</p>
            </section>
        </div>
    </div>
    <div x-show="tab==='databases'" class="mt-6 space-y-3">@foreach($server->databases as $database)<div class="panel"><div class="flex flex-wrap items-center justify-between gap-4"><h3 class="font-medium">Import and export {{ $database->name }}</h3><form method="POST" action="{{ route('databases.export',$database) }}">@csrf<button class="button-secondary" @disabled($database->status!=='ready')>Create SQL export</button></form></div><form method="POST" enctype="multipart/form-data" action="{{ route('databases.import',$database) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf<label class="grow text-sm">SQL file, up to 10 MB<input type="file" name="sql" accept=".sql,.txt" class="field"></label><button class="button-primary">Import</button></form><div class="mt-4 flex flex-wrap gap-3">@foreach($database->backups->where('type','export') as $backup)@if($backup->status==='ready')<a class="link-action" href="{{ route('database-backups.download',$backup) }}">Download {{ $backup->created_at->format('M j H:i') }} ({{ Number::fileSize($backup->size) }})</a>@else<span class="text-sm capitalize text-slate-500 dark:text-slate-400">Export {{ $backup->status }}</span>@endif @endforeach</div></div>@endforeach</div>
</div>
@endsection
