@php
    $backupDiskOptions = app(\App\Services\BackupStorage::class)->privateDiskOptions();
    $defaultBackupDisk = app(\App\Services\BackupStorage::class)->defaultDisk();
    $retentionDays = (int) config('remote_management.database_backup_retention_days', 30);
    $canDatabaseBackups = (bool) ($planFeatures['database_backups'] ?? false);
    $canOsBackups = (bool) ($planFeatures['os_backups'] ?? false);
    $osBackupUsed = app(\App\Services\QuotaManager::class)->usage(auth()->user(), 'os_backup_gb');
    $osBackupLimit = app(\App\Services\EntitlementService::class)->limit(auth()->user(), 'os_backup_gb');
    $defaultBackupType = $canDatabaseBackups || ! $server->provider_id ? 'database' : 'snapshot';
@endphp
<div x-cloak x-show="tab==='backups'" class="mt-6 space-y-6" x-data="{ backupType: '{{ $defaultBackupType }}', frequency: 'daily' }">
    @if($server->provider_id)
        <div class="panel flex flex-wrap items-center justify-between gap-3 !py-4">
            <div>
                <p class="text-sm font-medium heading">OS backup capacity</p>
                <p class="mt-0.5 text-xs muted">
                    {{ $osBackupUsed }} GB used
                    · {{ $osBackupLimit < 0 ? 'Unlimited' : $osBackupLimit.' GB' }} total (plan + add-on)
                </p>
            </div>
            <a href="{{ route('billing.index') }}" class="button-secondary !px-3 !py-1.5 text-xs">Buy more GB</a>
        </div>
    @endif
    <div class="grid gap-6 lg:grid-cols-[400px_1fr]">
        <form method="POST" action="{{ route('backup-policies.store', $server) }}" class="panel h-fit">@csrf
            <h2 class="font-semibold">Automated backup policy</h2>
            <label class="mt-4 block text-sm">Name<input class="field" name="name" placeholder="Nightly production"></label>
            <label class="mt-4 block text-sm">Recovery point
                <select class="field" name="type" x-model="backupType">
                    <option value="database">Database backup</option>
                    @if($server->provider_id)
                        <option value="snapshot">OS backup (provider snapshot)</option>
                    @endif
                </select>
            </label>
            <p x-show="backupType==='database' && ! {{ $canDatabaseBackups ? 'true' : 'false' }}" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                Database backups aren’t on your plan. <a class="font-medium underline" href="{{ route('billing.index') }}">Upgrade or subscribe</a> to create policies.
            </p>
            <p x-show="backupType==='snapshot' && ! {{ $canOsBackups ? 'true' : 'false' }}" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                OS backups aren’t on your plan. <a class="font-medium underline" href="{{ route('billing.index') }}">Upgrade or subscribe</a> to create snapshot policies.
            </p>
            <label x-show="backupType==='database'" class="mt-4 block text-sm">Database<select class="field" name="managed_database_id"><option value="">Select database</option>@foreach($server->databases as $database)<option value="{{ $database->id }}">{{ $database->name }}</option>@endforeach</select></label>
            <label x-show="backupType==='database'" class="mt-4 block text-sm">Storage disk<select class="field" name="disk">@foreach($backupDiskOptions as $disk => $label)<option value="{{ $disk }}" @selected($disk === $defaultBackupDisk)>{{ $label }}{{ $disk === $defaultBackupDisk ? ' (default)' : '' }}</option>@endforeach</select></label>
            <label class="mt-4 block text-sm">Frequency<select class="field" name="frequency" x-model="frequency"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
            <div class="grid grid-cols-2 gap-3"><label class="mt-4 block text-sm">Run at<input class="field" type="time" name="run_at" value="02:00"></label><label class="mt-4 block text-sm">Timezone<input class="field" name="timezone" value="{{ auth()->user()->timezone ?? 'UTC' }}"></label></div>
            <label x-show="frequency==='weekly'" class="mt-4 block text-sm">Weekday<select class="field" name="weekday"><option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="0">Sunday</option></select></label>
            <label x-show="frequency==='monthly'" class="mt-4 block text-sm">Day of month<input class="field" type="number" name="day_of_month" min="1" max="28" value="1"></label>
            <label class="mt-4 block text-sm">Recovery points to keep<input class="field" type="number" name="retention_count" min="1" max="100" value="7"></label>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Policy keeps the newest N ready points. Ready SQL exports are also pruned after {{ $retentionDays }} days ({{ $defaultBackupDisk }} disk default).</p>
            <button class="button-primary mt-5">Create policy</button>
        </form>
        <div class="space-y-3">@forelse($server->backupPolicies as $policy)
            @php
                $lastPoint = $policy->type === 'database'
                    ? $policy->databaseBackups->sortByDesc('created_at')->first()
                    : $policy->snapshots->sortByDesc('created_at')->first();
                $policyEntitled = $policy->type === 'database' ? $canDatabaseBackups : $canOsBackups;
            @endphp
            <article class="panel"><div class="flex flex-wrap justify-between gap-4"><div><h3 class="font-medium">{{ $policy->name }}</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $policy->type === 'database' ? ($policy->database?->name ?? 'Database backup') : 'OS backup (provider snapshot)' }} / {{ $policy->frequency }} at {{ $policy->run_at }} {{ $policy->timezone }} / keep {{ $policy->retention_count }}@if($policy->type === 'database') / disk {{ $policy->disk ?: $defaultBackupDisk }}@endif</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $policy->enabled ? 'Next '.$policy->next_run_at?->diffForHumans() : 'Disabled' }}@if($policy->last_run_at) · Last run {{ $policy->last_run_at->diffForHumans() }}@endif</p>@if($lastPoint)<p class="mt-1 text-xs capitalize {{ $lastPoint->status === 'failed' ? 'text-rose-600 dark:text-rose-300' : 'text-slate-500 dark:text-slate-400' }}">Last recovery point: {{ $lastPoint->status }}{{ $lastPoint->created_at ? ' · '.$lastPoint->created_at->diffForHumans() : '' }}</p>@endif</div><span class="text-sm {{ $policy->enabled ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $policy->enabled ? 'Enabled' : 'Paused' }}</span></div>
            @unless($policyEntitled)
                <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">This policy type isn’t on your plan. <a class="underline" href="{{ route('billing.index') }}">Upgrade</a> to run or change it.</p>
            @endunless
            <div class="mt-4 flex flex-wrap gap-3">
                @if($policyEntitled)
                    <form method="POST" action="{{ route('backup-policies.run', $policy) }}">@csrf<button class="button-primary">Run now</button></form>
                    <form method="POST" action="{{ route('backup-policies.toggle', $policy) }}">@csrf @method('PATCH')<button class="button-secondary">{{ $policy->enabled ? 'Pause' : 'Enable' }}</button></form>
                    <form method="POST" action="{{ route('backup-policies.destroy', $policy) }}">@csrf @method('DELETE')<button class="button-secondary text-rose-600 dark:text-rose-300">Remove</button></form>
                @else
                    <a href="{{ route('billing.index') }}" class="button-primary">Upgrade to manage</a>
                @endif
            </div></article>
        @empty<div class="panel text-center text-slate-500 dark:text-slate-400">No automated backup policies.</div>@endforelse</div>
    </div>
    <section class="panel">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-semibold">OS backups (provider snapshots)</h2>
                @if(! $server->provider_id)
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">OS / provider snapshots require a cloud Droplet. Connected custom servers must use database backup policies instead.</p>
                @elseif(! $canOsBackups)
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">OS backups aren’t included in your plan. <a class="font-medium underline" href="{{ route('billing.index') }}">Upgrade or subscribe</a> to create and restore provider snapshots.</p>
                @else
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Full Droplet disk snapshots. Restoring replaces the server disk — type the hostname to authorize it.</p>
                @endif
            </div>
            @if($server->provider_id && $canOsBackups)
                <form method="POST" action="{{ route('snapshots.store', $server) }}" class="flex gap-2">@csrf<input class="field mt-0" name="name" value="{{ $server->hostname }}-manual-{{ now()->format('Ymd') }}"><button class="button-primary">Create snapshot</button></form>
            @elseif($server->provider_id && ! $canOsBackups)
                <a href="{{ route('billing.index') }}" class="button-secondary">Upgrade for OS backups</a>
            @endif
        </div>
        <div class="mt-5 divide-y divide-slate-100 dark:divide-white/5">@forelse($server->snapshots as $snapshot)<div class="py-4"><div class="flex flex-wrap justify-between gap-3"><span>{{ $snapshot->name }} <small class="ml-2 text-slate-500 dark:text-slate-400">{{ $snapshot->status }} @if($snapshot->size_gigabytes)/ {{ $snapshot->size_gigabytes }} GB @endif</small></span>@if($server->provider_id && $canOsBackups)<form method="POST" action="{{ route('snapshots.destroy', $snapshot) }}">@csrf @method('DELETE')<button class="text-sm text-rose-600 dark:text-rose-300">Delete</button></form>@endif</div>@if($snapshot->status === 'ready' && $server->provider_id && $canOsBackups)<form method="POST" action="{{ route('snapshots.restore', $snapshot) }}" class="mt-3 flex max-w-lg gap-2">@csrf<input class="field mt-0" name="confirmation" placeholder="Type {{ $server->hostname }}"><button class="button-secondary text-amber-600 dark:text-amber-300">Restore server</button></form>@endif @if($snapshot->failure_reason)<p class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ $snapshot->failure_reason }}</p>@endif</div>@empty<p class="py-5 text-sm text-slate-500 dark:text-slate-400">{{ $server->provider_id ? 'No OS / provider snapshots.' : 'No provider snapshots for this server.' }}</p>@endforelse</div>
    </section>
    <section class="panel">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-semibold">Database recovery points</h2>
                @unless($canDatabaseBackups)
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Restoring from these points requires Database backups on your plan. <a class="font-medium underline" href="{{ route('billing.index') }}">Upgrade or subscribe</a>.</p>
                @endunless
            </div>
        </div>
        <div class="mt-5 divide-y divide-slate-100 dark:divide-white/5">@forelse($server->databases->flatMap->backups->where('type', 'export')->sortByDesc('created_at') as $backup)<div class="py-4"><div class="flex flex-wrap justify-between gap-3"><span>{{ $backup->database->name }} / {{ $backup->created_at->toDayDateTimeString() }}@if($backup->disk)<small class="ml-2 text-slate-500 dark:text-slate-400">{{ $backup->disk }}</small>@endif</span><span class="text-sm capitalize {{ $backup->status === 'failed' ? 'text-rose-600 dark:text-rose-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $backup->status }} @if($backup->size)/ {{ Number::fileSize($backup->size) }} @endif</span></div>@if($backup->failure_reason)<p class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ $backup->failure_reason }}</p>@endif @if($backup->status === 'ready')<div class="mt-3 flex flex-wrap gap-3"><a class="button-secondary" href="{{ route('database-backups.download', $backup) }}">Download</a>@if($canDatabaseBackups)<form method="POST" action="{{ route('database-backups.restore', $backup) }}" class="flex gap-2">@csrf<input class="field mt-0" name="confirmation" placeholder="Type {{ $backup->database->name }}"><button class="button-secondary text-amber-600 dark:text-amber-300">Restore database</button></form>@else<a href="{{ route('billing.index') }}" class="button-secondary">Upgrade to restore</a>@endif</div>@endif</div>@empty<p class="py-5 text-sm text-slate-500 dark:text-slate-400">No database recovery points.</p>@endforelse</div>
    </section>
</div>
