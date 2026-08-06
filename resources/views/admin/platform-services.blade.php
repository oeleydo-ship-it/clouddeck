@extends('layouts.admin')
@section('admin-title', 'Platform services')
@section('admin-description', 'Live status and start/stop for this control plane’s Redis, Horizon, queue workers, Reverb, and HTTPS/TLS.')
@section('admin')
@php
    $initialJson = \Illuminate\Support\Js::from($initial);
@endphp
<div
    class="space-y-5"
    x-data="platformServices({{ $initialJson }})"
    x-init="boot()"
>
    <section class="panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold heading">Control-plane runtime</h2>
                <p class="mt-1 text-sm muted">
                    These are <strong class="heading">Uplary’s own</strong> processes — not customer-site Supervisor workers.
                    Status refreshes every few seconds.
                </p>
            </div>
            <div class="text-right text-xs muted">
                <p x-text="platformLabel"></p>
                <p class="mt-1">Updated <span x-text="relativePolled"></span></p>
            </div>
        </div>
        <p class="mt-3 text-xs muted" x-show="! horizonRecommended">
            This PHP build looks like Windows or lacks <code>pcntl</code>/<code>posix</code>. Prefer <strong class="heading">Queue workers</strong> over Horizon here; Horizon still works on Linux production hosts.
        </p>
        <p class="mt-3 text-sm" :class="flashOk ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300'" x-show="flash" x-text="flash" x-cloak></p>
    </section>

    <div class="grid gap-4 sm:grid-cols-2">
        <template x-for="key in serviceOrder" :key="key">
            <section class="panel flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold heading" x-text="services[key].name"></h3>
                        <p class="mt-1 text-xs muted" x-text="services[key].detail"></p>
                    </div>
                    <span
                        class="badge shrink-0"
                        :class="badgeClass(services[key].status)"
                    >
                        <span class="badge-dot" :class="dotClass(services[key].status)"></span>
                        <span class="capitalize" x-text="services[key].status"></span>
                    </span>
                </div>

                <p class="text-xs muted" x-text="services[key].note"></p>
                <p class="text-xs text-rose-600 dark:text-rose-300" x-show="services[key].last_error" x-text="services[key].last_error"></p>

                <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-white/5">
                    <a
                        x-show="services[key].link"
                        :href="services[key].link"
                        target="_blank"
                        rel="noopener"
                        class="button-secondary !px-3 !py-1.5 text-xs"
                    >Horizon dashboard</a>
                    <button
                        type="button"
                        class="button-secondary !px-3 !py-1.5 text-xs"
                        :disabled="busy || ! services[key].actions.start"
                        @click="act(key, 'start')"
                    >Start</button>
                    <button
                        type="button"
                        class="button-secondary !px-3 !py-1.5 text-xs"
                        :disabled="busy || ! services[key].actions.stop"
                        @click="act(key, 'stop')"
                    >Stop</button>
                    <button
                        type="button"
                        class="button-secondary !px-3 !py-1.5 text-xs"
                        :disabled="busy || ! services[key].actions.restart"
                        @click="act(key, 'restart')"
                    >Restart</button>
                </div>
            </section>
        </template>
    </div>

    <section class="panel flex flex-col gap-3" x-show="ssl">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="font-semibold heading" x-text="ssl.name || 'SSL / TLS'"></h3>
                <p class="mt-1 text-xs muted" x-text="ssl.detail"></p>
            </div>
            <span class="badge shrink-0" :class="sslBadgeClass(ssl.status)">
                <span class="badge-dot" :class="sslDotClass(ssl.status)"></span>
                <span class="capitalize" x-text="sslStatusLabel(ssl.status)"></span>
            </span>
        </div>

        <dl class="grid gap-2 text-xs sm:grid-cols-2">
            <div>
                <dt class="muted">APP_URL</dt>
                <dd class="heading mt-0.5 break-all" x-text="ssl.app_url || '—'"></dd>
            </div>
            <div>
                <dt class="muted">Domain</dt>
                <dd class="heading mt-0.5" x-text="ssl.domain || '—'"></dd>
            </div>
            <div>
                <dt class="muted">Issuer</dt>
                <dd class="heading mt-0.5" x-text="(ssl.meta && ssl.meta.issuer) || '—'"></dd>
            </div>
            <div>
                <dt class="muted">Expires</dt>
                <dd class="heading mt-0.5" x-text="sslExpiryLabel()"></dd>
            </div>
        </dl>

        <p class="text-xs muted" x-text="ssl.note"></p>
        <p class="text-xs text-rose-600 dark:text-rose-300" x-show="ssl.last_error" x-text="ssl.last_error"></p>

        <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-white/5">
            <a
                x-show="ssl.docs_url"
                :href="ssl.docs_url"
                class="button-secondary !px-3 !py-1.5 text-xs"
            >SSL docs</a>
            <button
                type="button"
                class="button-secondary !px-3 !py-1.5 text-xs"
                :disabled="busy || ! (ssl.actions && ssl.actions.renew)"
                @click="renewSsl()"
            >Renew certificate</button>
        </div>
    </section>
</div>

<script>
function platformServices(initial) {
    return {
        services: initial.services || {},
        serviceOrder: ['redis', 'horizon', 'queue', 'reverb'],
        ssl: initial.ssl || {},
        windows: !!initial.windows,
        pcntl: !!initial.pcntl,
        horizonRecommended: !!initial.horizon_recommended,
        polledAt: initial.polled_at || null,
        busy: false,
        flash: '',
        flashOk: true,
        timer: null,

        get platformLabel() {
            const bits = [this.windows ? 'Windows' : (initial.platform || 'Unix')];
            bits.push(this.pcntl ? 'pcntl ok' : 'no pcntl');
            return bits.join(' · ');
        },

        get relativePolled() {
            if (! this.polledAt) return '—';
            try {
                const then = new Date(this.polledAt).getTime();
                const secs = Math.max(0, Math.round((Date.now() - then) / 1000));
                if (secs < 5) return 'just now';
                return secs + 's ago';
            } catch (e) {
                return '—';
            }
        },

        boot() {
            this.poll();
            this.timer = setInterval(() => this.poll(), 7000);
        },

        badgeClass(status) {
            return {
                'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300': status === 'running',
                'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300': status === 'stopped',
                'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300': status === 'degraded' || status === 'unavailable',
                'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300': status === 'error',
            };
        },

        dotClass(status) {
            return {
                'bg-emerald-500': status === 'running',
                'bg-slate-400': status === 'stopped',
                'bg-amber-500': status === 'degraded' || status === 'unavailable',
                'bg-rose-500': status === 'error',
            };
        },

        sslBadgeClass(status) {
            return {
                'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300': status === 'valid',
                'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300': status === 'expiring_soon' || status === 'not_https',
                'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300': status === 'expired' || status === 'unreachable',
                'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300': ! status,
            };
        },

        sslDotClass(status) {
            return {
                'bg-emerald-500': status === 'valid',
                'bg-amber-500': status === 'expiring_soon' || status === 'not_https',
                'bg-rose-500': status === 'expired' || status === 'unreachable',
                'bg-slate-400': ! status,
            };
        },

        sslStatusLabel(status) {
            if (! status) return '—';
            return String(status).replaceAll('_', ' ');
        },

        sslExpiryLabel() {
            const meta = this.ssl.meta || {};
            if (meta.valid_to) {
                const days = meta.days_remaining;
                const dayBit = (days === null || days === undefined) ? '' : (' · ' + days + 'd left');
                try {
                    return new Date(meta.valid_to).toLocaleString() + dayBit;
                } catch (e) {
                    return meta.valid_to + dayBit;
                }
            }
            return '—';
        },

        async poll() {
            try {
                const res = await fetch(@json(route('admin.platform-services.status')), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (! res.ok) return;
                const data = await res.json();
                this.services = data.services || {};
                this.ssl = data.ssl || {};
                this.windows = !!data.windows;
                this.pcntl = !!data.pcntl;
                this.horizonRecommended = !!data.horizon_recommended;
                this.polledAt = data.polled_at;
            } catch (e) { /* keep last snapshot */ }
        },

        csrf() {
            const el = document.querySelector('meta[name="csrf-token"]');
            return el ? el.getAttribute('content') : '';
        },

        async act(service, action) {
            if (this.busy) return;
            this.busy = true;
            this.flash = '';
            try {
                const url = @json(url('/admin/platform-services')) + '/' + service + '/' + action;
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });
                const data = await res.json();
                this.flashOk = !!data.ok;
                this.flash = data.message || (data.ok ? 'Done.' : 'Action failed.');
                if (data.service) {
                    this.services[service] = data.service;
                }
                await this.poll();
            } catch (e) {
                this.flashOk = false;
                this.flash = 'Request failed.';
            } finally {
                this.busy = false;
            }
        },

        async renewSsl() {
            if (this.busy) return;
            this.busy = true;
            this.flash = '';
            try {
                const res = await fetch(@json(route('admin.platform-services.ssl.renew')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });
                const data = await res.json();
                this.flashOk = !!data.ok;
                this.flash = data.message || (data.ok ? 'Renew finished.' : 'Renew failed.');
                if (data.ssl) {
                    this.ssl = data.ssl;
                }
                await this.poll();
            } catch (e) {
                this.flashOk = false;
                this.flash = 'Request failed.';
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
@endsection
