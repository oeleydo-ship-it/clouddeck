@extends('layouts.app')
@section('content')
<div class="app-main !max-w-3xl">
    <p class="page-eyebrow">New application</p>
    <h1 class="page-title">Create a site</h1>
    <p class="mt-2 muted">{{ $branding['name'] }} configures Nginx and prepares the release directories in the background.</p>

    @if($errors->any())
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
            <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($servers->isEmpty())
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
            No ready servers are connected. <a class="underline" href="{{ route('servers.custom') }}">Attach an existing server</a> or <a class="underline" href="{{ route('servers.create') }}">provision a new one</a>, then wait for the bootstrap to finish.
        </div>
    @endif

    <form method="POST" action="{{ route('sites.store') }}" class="panel mt-8" x-data="{ platform: @js(old('platform', 'laravel')) }">@csrf
        <fieldset>
            <legend class="text-sm font-medium heading">Platform</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach([
                    'laravel' => ['Laravel', 'Deployed from your Git repository, with Composer, migrations, and asset builds.'],
                    'wordpress' => ['WordPress', 'Downloaded from wordpress.org and configured for you. No repository needed.'],
                ] as $value => [$label, $description])
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                           :class="platform === '{{ $value }}' ? 'border-cyan-400 bg-cyan-50/50 dark:border-cyan-400/40 dark:bg-cyan-400/5' : 'border-slate-200 dark:border-white/10'">
                        <input type="radio" name="platform" value="{{ $value }}" x-model="platform" class="mt-0.5">
                        <span>
                            <span class="block text-sm font-medium heading">{{ $label }}</span>
                            <span class="mt-1 block text-xs muted">{{ $description }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="mt-6 grid gap-5 sm:grid-cols-2">
            <label class="text-sm heading sm:col-span-2">Server
                <select class="field" name="server_id" required>
                    <option value="">Select a ready server</option>
                    @foreach($servers as $server)<option value="{{ $server->id }}" @selected(old('server_id') === $server->id)>{{ $server->name }} / {{ $server->public_ip }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm heading">Domain<input class="field" name="domain" value="{{ old('domain') }}" placeholder="app.example.com" required></label>
            <label class="text-sm heading">PHP version
                <select class="field" name="php_version">@foreach(['8.4','8.3','8.2'] as $version)<option @selected(old('php_version') === $version)>{{ $version }}</option>@endforeach</select>
            </label>
        </div>

        <template x-if="platform === 'laravel'">
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="text-sm heading sm:col-span-2">Git repository<input class="field" name="repository_url" value="{{ old('repository_url') }}" placeholder="https://github.com/acme/app.git"></label>
                <label class="text-sm heading">Branch<input class="field" name="branch" value="{{ old('branch', 'main') }}"></label>
                <div class="flex items-end gap-5 pb-3">
                    <label class="flex gap-2 text-sm heading"><input type="checkbox" name="auto_deploy" value="1" @checked(old('auto_deploy'))>Auto deploy</label>
                    <label class="flex gap-2 text-sm heading"><input type="checkbox" name="zero_downtime" value="1" @checked(old('zero_downtime', true))>Zero downtime</label>
                </div>
            </div>
        </template>

        <template x-if="platform === 'wordpress'">
            <div class="mt-5 rounded-xl bg-slate-50 p-4 dark:bg-white/5">
                <p class="text-sm heading">WordPress is installed for you</p>
                <p class="mt-1 text-sm muted">
                    Deploying downloads the latest release, writes <code>wp-config.php</code> from this site's database credentials, and keeps
                    <code>wp-content</code> — your uploads, plugins, and themes — outside the release so it survives every future deployment.
                </p>
                <p class="mt-2 text-sm muted">Create a database for the site and attach it before deploying, then finish the famous five-minute install in the browser.</p>
            </div>
        </template>

        <button class="button-primary mt-6" @disabled($servers->isEmpty())>Create site</button>
    </form>
</div>
@endsection
