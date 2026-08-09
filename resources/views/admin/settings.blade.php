@extends('layouts.admin')
@section('admin-title', 'Settings')
@section('admin-description', 'Platform-wide configuration applied to every customer.')
@section('admin')
    @php
        $branding = app(\App\Services\SystemSettings::class)->branding();
        $value = fn (string $key, ?string $fallback = null) => $settings->get($key)?->value ?: $fallback;
    @endphp

    <div class="space-y-6">
        <section class="panel">
            <h2 class="font-semibold heading">General information</h2>
            <p class="mt-1 text-sm muted">Platform name and logo are shown to customers across the console, marketing pages, and outgoing email. Change the name anytime — it updates everywhere branding is used.</p>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-5 max-w-2xl">@csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm heading">Platform name<input class="field" name="platform_name" value="{{ $value('platform_name', config('app.name', 'Uplary')) }}" maxlength="60" placeholder="Uplary"></label>
                    <label class="text-sm heading">Support email<input class="field" type="email" name="support_email" value="{{ $value('support_email') }}" placeholder="support@example.com"></label>
                </div>
                <label class="mt-4 block text-sm heading">Maintenance banner<textarea class="field" name="maintenance_banner" rows="2" placeholder="Shown to every signed-in customer while set.">{{ $value('maintenance_banner') }}</textarea></label>
                <label class="mt-4 flex gap-2 text-sm heading"><input type="checkbox" name="registration_enabled" value="1" @checked($settings->get('registration_enabled')?->value !== '0')>Public registration enabled</label>
                <label class="mt-3 flex gap-2 text-sm heading"><input type="checkbox" name="email_verification_required" value="1" @checked(($settings->get('email_verification_required')?->value ?? (config('clouddeck.email_verification_required') ? '1' : '0')) === '1')>Require email verification</label>
                <p class="mt-2 text-xs muted">Verification needs working mail under <a class="underline" href="{{ route('admin.mail') }}">Admin → SMTP</a>. With it off, new registrations are marked verified immediately and existing unverified accounts are let through.</p>
                <label class="mt-4 flex gap-2 text-sm heading"><input type="checkbox" name="public_site_enabled" value="1" @checked(($settings->get('public_site_enabled')?->value ?? '1') === '1')>Public marketing pages enabled</label>
                <p class="mt-2 text-xs muted">Serves the home, about, features, use cases, blog, and contact pages. Turn this off when the install is only the application — on a subdomain, say — and every visitor lands on the sign-in form instead. Blog posts stay editable here either way.</p>
                <label class="mt-4 flex gap-2 text-sm heading"><input type="checkbox" name="dns_enabled" value="1" @checked(($settings->get('dns_enabled')?->value ?? '1') === '1')>DNS management enabled</label>
                <p class="mt-2 text-xs muted">Shows the DNS section, where a Cloudflare token can be connected and zone records edited. Turn it off when DNS is handled elsewhere: the nav entry disappears and every DNS URL returns a 404, so a kept link cannot reach it. Connections already saved are left untouched and come back if it is turned on again.</p>
                <label class="mt-4 flex gap-2 text-sm heading"><input type="checkbox" name="staging_sites_enabled" value="1" @checked(($settings->get('staging_sites_enabled')?->value ?? '0') === '1')>Staging sites enabled</label>
                <p class="mt-2 text-xs muted">Lets customers create a staging environment linked to a production site, then promote staging settings and deploy to production. When off, create and promote routes return 404.</p>
                <label class="mt-4 flex gap-2 text-sm heading"><input type="checkbox" name="allow_impersonate_admins" value="1" @checked(($settings->get('allow_impersonate_admins')?->value ?? '0') === '1')>Allow impersonating other super admins</label>
                <p class="mt-2 text-xs muted">Requires the <code>users.impersonate_admins</code> gate (super admins only). Leave off unless support truly needs to enter another administrator's account.</p>
                <label class="mt-4 block text-sm heading">Platform staging domain<input class="field" name="staging_platform_domain" value="{{ $value('staging_platform_domain', 'uplary.com') }}" placeholder="uplary.com"></label>
                <p class="mt-2 text-xs muted">Used when a customer chooses a platform subdomain: <code>{slug}.staging.{{ $value('staging_platform_domain', 'uplary.com') }}</code>.</p>
                <label class="mt-4 block text-sm heading">Platform Cloudflare DNS token
                    <input class="field font-mono text-xs" type="password" name="platform_dns_cloudflare_token" value="" placeholder="{{ filled($value('platform_dns_cloudflare_token')) ? '•••••••• (leave blank to keep)' : 'Cloudflare API token' }}" autocomplete="new-password">
                </label>
                <p class="mt-2 text-xs muted">
                    Zone:DNS Edit on <code>{{ $value('staging_platform_domain', 'uplary.com') }}</code>.
                    When set, creating platform staging publishes an A record <code>{slug}.staging</code> → that site’s server public IP (DNS-only, not proxied).
                    DNS for this apex must live on Cloudflare.
                    @if(app(\App\Services\SystemSettings::class)->platformStagingDnsReady())
                        <span class="text-emerald-600 dark:text-emerald-300">Connected.</span>
                    @else
                        <span class="text-amber-700 dark:text-amber-300">Not connected — platform staging hostnames will not auto-point at customer servers.</span>
                    @endif
                </p>
                @error('platform_dns_cloudflare_token')<p class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                <button class="button-primary mt-5">Save general settings</button>
            </form>
        </section>

        <section class="panel">
            <h2 class="font-semibold heading">Logo</h2>
            <p class="mt-1 text-sm muted">Replaces the mark in the header. PNG, JPG, WEBP, or SVG up to 1&nbsp;MB.</p>
            <div class="mt-5 flex flex-wrap items-center gap-6">
                <div class="grid size-16 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5">
                    @if($branding['logo_url'])
                        <img src="{{ $branding['logo_url'] }}" alt="Current logo" class="size-14 rounded-xl object-contain">
                    @else
                        <span class="grid size-14 place-items-center rounded-xl bg-sky-500 text-xl font-bold text-white">{{ Str::upper(Str::substr($branding['name'], 0, 1)) }}</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.settings.logo') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">@csrf
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" required class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:file:bg-white/10 dark:file:text-slate-200">
                    <button class="button-primary">Upload</button>
                </form>
                @if($branding['logo_url'])
                    <form method="POST" action="{{ route('admin.settings.logo.destroy') }}">@csrf @method('DELETE')<button class="button-secondary !text-rose-600 dark:!text-rose-300">Remove</button></form>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.settings.branding') }}" class="mt-5 border-t border-slate-100 pt-5 dark:border-white/5">@csrf @method('PUT')
                <label class="flex gap-2 text-sm heading">
                    <input type="checkbox" name="logo_image_only" value="1" @checked($branding['logo_image_only']) @disabled(! $branding['logo_url'])>
                    Show logo image only
                </label>
                <p class="mt-2 text-xs muted">Hides the platform name beside the logo in console, marketing, and authentication headers. Upload a custom logo first.</p>
                <button class="button-secondary mt-3" @disabled(! $branding['logo_url'])>Save logo display</button>
            </form>
        </section>
    </div>
@endsection
