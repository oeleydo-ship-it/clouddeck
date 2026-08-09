@extends('layouts.admin')

@section('admin-title', 'Storage')
@section('admin-description', 'S3-compatible object storage for off-server database and site backups.')

@section('admin')
@php
    $provider = old('object_storage_provider', $objectStorage['provider'] ?? 'custom');
    $keySaved = filled($objectStorage['key'] ?? null);
    $secretSaved = filled($objectStorage['secret'] ?? null);
    $configured = (bool) ($objectStorage['configured'] ?? false);
@endphp

<div class="space-y-6">
    <section class="panel" x-data="{
        provider: @js($provider),
        region: @js(old('object_storage_region', $objectStorage['region'] ?? '')),
        endpoint: @js(old('object_storage_endpoint', $objectStorage['endpoint'] ?? '')),
        pathStyle: @js((bool) old('object_storage_path_style', $objectStorage['path_style'] ?? false)),
        presets: {
            digitalocean: { hint: 'Use the Spaces region (e.g. nyc3). Endpoint becomes https://{region}.digitaloceanspaces.com', endpoint: (r) => r ? `https://${r}.digitaloceanspaces.com` : '', pathStyle: false },
            hetzner: { hint: 'Use the location (e.g. fsn1, nbg1, hel1). Endpoint becomes https://{region}.your-objectstorage.com', endpoint: (r) => r ? `https://${r}.your-objectstorage.com` : '', pathStyle: false },
            wasabi: { hint: 'Use the Wasabi region (e.g. us-east-1, eu-central-1). Endpoint becomes https://s3.{region}.wasabisys.com', endpoint: (r) => r ? `https://s3.${r}.wasabisys.com` : '', pathStyle: false },
            custom: { hint: 'Any S3-compatible API. Paste the provider endpoint URL yourself.', endpoint: () => null, pathStyle: null },
        },
        applyPreset() {
            const preset = this.presets[this.provider];
            if (!preset) return;
            if (this.provider !== 'custom') {
                this.endpoint = preset.endpoint(this.region);
                this.pathStyle = preset.pathStyle;
            }
        }
    }">
        <h2 class="font-semibold heading">Object storage (S3-compatible)</h2>
        <p class="mt-1 text-sm muted">Store database exports and full site archives off this application server. Works with DigitalOcean Spaces, Hetzner Object Storage, Wasabi, and other S3 APIs. Credentials are encrypted and override <code>AWS_*</code> in <code>.env</code> when set. Leave access key / secret blank to keep stored values.</p>

        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 rounded-xl bg-slate-100 p-4 text-sm dark:bg-black/20">
            <span class="{{ $configured ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' }}">Bucket {{ $configured ? 'ready' : 'incomplete' }}</span>
            <span class="{{ $keySaved ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' }}">Access key {{ $keySaved ? 'configured' : 'missing' }}</span>
            <span class="{{ $secretSaved ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' }}">Secret {{ $secretSaved ? 'configured' : 'missing' }}</span>
        </div>

        <form method="POST" action="{{ route('admin.settings.object-storage') }}" class="mt-5 max-w-2xl space-y-4">@csrf @method('PUT')
            <label class="block text-sm heading">Provider
                <select class="field" name="object_storage_provider" x-model="provider" @change="applyPreset()">
                    <option value="digitalocean">DigitalOcean Spaces</option>
                    <option value="hetzner">Hetzner Object Storage</option>
                    <option value="wasabi">Wasabi</option>
                    <option value="custom">Custom S3-compatible</option>
                </select>
            </label>
            <p class="text-xs muted" x-text="presets[provider]?.hint"></p>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm heading">Access key
                    <input class="field font-mono text-xs" type="password" name="object_storage_key" autocomplete="new-password" placeholder="{{ $keySaved ? 'Saved — leave blank to keep it' : 'Access key ID' }}">
                </label>
                <label class="block text-sm heading">Secret key
                    <input class="field font-mono text-xs" type="password" name="object_storage_secret" autocomplete="new-password" placeholder="{{ $secretSaved ? 'Saved — leave blank to keep it' : 'Secret access key' }}">
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm heading">Region / location
                    <input class="field font-mono text-xs" name="object_storage_region" x-model="region" @change="applyPreset()" placeholder="nyc3 / fsn1 / us-east-1">
                </label>
                <label class="block text-sm heading">Bucket
                    <input class="field font-mono text-xs" name="object_storage_bucket" value="{{ old('object_storage_bucket', $objectStorage['bucket'] ?? '') }}" placeholder="uplary-backups">
                </label>
            </div>

            <label class="block text-sm heading">Endpoint URL
                <input class="field font-mono text-xs" name="object_storage_endpoint" x-model="endpoint" placeholder="https://nyc3.digitaloceanspaces.com">
            </label>
            <label class="block text-sm heading">Public base URL <span class="font-normal muted">(optional)</span>
                <input class="field font-mono text-xs" name="object_storage_url" value="{{ old('object_storage_url', $objectStorage['url'] ?? '') }}" placeholder="https://uplary-backups.nyc3.digitaloceanspaces.com">
            </label>

            <label class="flex items-center gap-2 text-sm heading">
                <input type="checkbox" name="object_storage_path_style" value="1" x-model="pathStyle">
                Use path-style endpoint
            </label>
            <p class="text-xs muted">Leave off for Spaces, Wasabi, and Hetzner (virtual-hosted style). Path-style can break large uploads on those providers.</p>

            <label class="block text-sm heading">Default backup disk
                <select class="field" name="database_backup_disk">
                    <option value="local" @selected(old('database_backup_disk', $databaseBackupDisk) === 'local')>Local (this server — storage/app/private)</option>
                    <option value="s3" @selected(old('database_backup_disk', $databaseBackupDisk) === 's3')>Object storage (S3-compatible)</option>
                </select>
            </label>
            <p class="text-xs muted">New database and full site backups use this disk. Existing recovery points keep the disk they were written to.</p>
            @error('database_backup_disk')<p class="text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror

            <div class="flex flex-wrap gap-3 pt-1">
                <button class="button-primary">Save storage settings</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.settings.object-storage.test') }}" class="mt-4">@csrf
            <button class="button-secondary" @disabled(! $configured)>Test connection</button>
            <p class="mt-2 text-xs muted">Writes and deletes a tiny probe object under <code>uplary-storage-tests/</code>.</p>
        </form>
    </section>

    <section class="panel">
        <h2 class="font-semibold heading">Provider endpoint cheat sheet</h2>
        <ul class="mt-3 list-inside list-disc space-y-2 text-sm muted">
            <li><strong class="heading">DigitalOcean Spaces</strong> — region like <code>nyc3</code>; endpoint <code>https://nyc3.digitaloceanspaces.com</code>; keep the bucket private.</li>
            <li><strong class="heading">Hetzner Object Storage</strong> — location like <code>fsn1</code>; endpoint <code>https://fsn1.your-objectstorage.com</code>.</li>
            <li><strong class="heading">Wasabi</strong> — region like <code>us-east-1</code>; endpoint <code>https://s3.us-east-1.wasabisys.com</code>.</li>
        </ul>
    </section>
</div>
@endsection
