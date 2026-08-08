@extends('layouts.admin')
@section('admin-title', 'Managed servers')
@section('admin-description', 'Let customers provision a VPS on your cloud account, and set what each configuration costs them.')
@section('admin')
    <div class="space-y-6">
        <section class="panel">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold heading">Platform connection</h2>
                    <p class="mt-1 text-sm muted">Let customers provision VPS on <em>your</em> cloud account without connecting their own provider (BYOS). Priced separately on plans via the <strong>Managed servers</strong> feature and limit.</p>
                </div>
                <span @class([
                    'badge shrink-0',
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $ready,
                    'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-500' => ! $ready,
                ])>{{ $ready ? 'Ready' : 'Not configured' }}</span>
            </div>
            @php $managedTokenSaved = filled($settings->get('managed_cloud_token')?->value); @endphp
            <form method="POST" action="{{ route('admin.settings.managed-servers') }}" class="mt-5 max-w-2xl space-y-4">@csrf @method('PUT')
                <label class="flex gap-2 text-sm heading"><input type="checkbox" name="managed_servers_enabled" value="1" @checked(($settings->get('managed_servers_enabled')?->value ?? '0') === '1')>Enable managed servers</label>
                <p class="text-xs muted">When off, customer managed-provision routes return 404. Existing managed hosts keep running.</p>
                <label class="text-sm heading">Cloud provider
                    <select class="field" name="managed_cloud_provider">
                        <option value="digitalocean" @selected(($settings->get('managed_cloud_provider')?->value ?? 'digitalocean') === 'digitalocean')>DigitalOcean</option>
                    </select>
                </label>
                <label class="text-sm heading">Platform API token
                    <input class="field font-mono text-xs" type="password" name="managed_cloud_token" autocomplete="new-password" placeholder="{{ $managedTokenSaved ? 'Saved — leave blank to keep it' : 'dop_v1_…' }}">
                </label>
                <p class="text-xs muted">Stored encrypted. Uplary uses this token to create and destroy Droplets for entitled customers. Customers never see it. Also enable <strong>Managed servers</strong> on each plan under Admin → Plans.</p>
                <button class="button-primary">Save managed server settings</button>
            </form>
        </section>

        <section class="panel">
            <h2 class="font-semibold heading">Markup pricing</h2>
            <p class="mt-1 text-sm muted">Each configuration (1&nbsp;GB, 4&nbsp;GB, 8&nbsp;GB, …) can carry its own price — the infra cost alone is rarely what you want to charge.</p>

            @if(! $ready)
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">Enable managed servers and save a valid platform API token above first.</p>
            @elseif(empty($managedSizes))
                <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">Could not load sizes from the platform cloud account. Check that the API token is valid.</p>
            @else
                <form method="POST" action="{{ route('admin.settings.managed-servers.pricing') }}" class="mt-5 space-y-5">@csrf @method('PUT')
                    <label class="block max-w-xs text-sm heading">Default markup %
                        <input class="field" type="number" step="0.1" min="0" max="1000" name="markup_percent" value="{{ old('markup_percent', $managedMarkupPercent) }}">
                        <span class="mt-1 block text-xs font-normal muted">Applied over infra cost for any size without a price override below.</span>
                    </label>

                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/[.03] dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-2.5">Configuration</th>
                                    <th class="px-4 py-2.5">Infra cost</th>
                                    <th class="px-4 py-2.5">With default markup</th>
                                    <th class="px-4 py-2.5">Customer price override</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                @foreach($managedSizes as $size)
                                    @php
                                        $slug = $size['slug'];
                                        $infra = (float) ($size['price_monthly'] ?? 0);
                                        $withMarkup = round($infra * (1 + $managedMarkupPercent / 100), 2);
                                        $override = $managedSizePrices[$slug] ?? null;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-2.5 heading">{{ $size['vcpus'] }} vCPU · {{ round($size['memory']/1024,1) }} GB RAM · {{ $size['disk'] ?? '—' }} GB disk<br><span class="font-mono text-xs muted">{{ $slug }}</span></td>
                                        <td class="px-4 py-2.5 muted">${{ number_format($infra, 2) }}/mo</td>
                                        <td class="px-4 py-2.5 muted">${{ number_format($withMarkup, 2) }}/mo</td>
                                        <td class="px-4 py-2.5">
                                            <input class="field !py-1.5" type="number" step="0.01" min="0" name="prices[{{ $slug }}]" value="{{ old('prices.'.$slug, $override) }}" placeholder="${{ number_format($withMarkup, 2) }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs muted">Leave a row blank to bill it at infra cost + the default markup instead of a fixed price.</p>
                    <button class="button-primary">Save pricing</button>
                </form>
            @endif
        </section>

        <section class="panel">
            <h2 class="font-semibold heading">How this works</h2>
            <ol class="mt-4 space-y-3 text-sm">
                <li class="flex gap-3"><span class="stat-icon !size-6 shrink-0 bg-cyan-50 text-xs font-semibold text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">1</span><span class="muted">Enable managed servers above and save a platform DigitalOcean token. This is <em>your</em> cloud account — customers never see or need one.</span></li>
                <li class="flex gap-3"><span class="stat-icon !size-6 shrink-0 bg-cyan-50 text-xs font-semibold text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">2</span><span class="muted">Set a default markup % and, optionally, an exact price for each configuration above.</span></li>
                <li class="flex gap-3"><span class="stat-icon !size-6 shrink-0 bg-cyan-50 text-xs font-semibold text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">3</span><span class="muted">Turn on the <strong>Managed servers</strong> feature and set a limit on any plan under <a class="underline" href="{{ route('admin.plans') }}">Admin → Plans</a> — it is billed separately from BYOS server limits.</span></li>
                <li class="flex gap-3"><span class="stat-icon !size-6 shrink-0 bg-cyan-50 text-xs font-semibold text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">4</span><span class="muted">Entitled customers see <strong>Managed server</strong> on the Servers page and deploy without a cloud account connection.</span></li>
            </ol>
        </section>
    </div>
@endsection
