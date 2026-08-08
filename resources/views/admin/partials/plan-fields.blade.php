{{-- Shared by the create form and each plan's edit form, so the two can never drift apart. --}}
@php
    $currency = $plan?->currency ?? 'USD';
    // Minor units back to what was typed: 2900 reads as 29, 2999 stays 29.99.
    $money = function (?int $cents): string {
        $trimmed = rtrim(rtrim(number_format(($cents ?? 0) / 100, 2, '.', ''), '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    };
    // Access gates first, then modules — keeps quotas vs features obvious while editing.
    $accessFeatureKeys = ['providers', 'managed_servers'];
    $moduleFeatureKeys = collect($featureKeys)->except($accessFeatureKeys)->all();
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <label class="text-sm heading">Name<input class="field" name="name" value="{{ old('name', $plan?->name) }}" required maxlength="100" placeholder="Pro"></label>
    <label class="text-sm heading">Slug<input class="field font-mono text-xs" name="slug" value="{{ old('slug', $plan?->slug) }}" placeholder="Generated from the name"></label>
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-3">
    <label class="text-sm heading">Monthly price<input class="field" type="number" step="0.01" min="0" name="monthly_price" value="{{ old('monthly_price', $money($plan?->monthly_price)) }}" required></label>
    <label class="text-sm heading">Yearly price<input class="field" type="number" step="0.01" min="0" name="yearly_price" value="{{ old('yearly_price', $money($plan?->yearly_price)) }}" required></label>
    <label class="text-sm heading">Currency<input class="field uppercase" name="currency" value="{{ old('currency', $currency) }}" maxlength="3" required></label>
</div>
<p class="mt-2 text-xs muted">Enter prices as customers see them — <code>29</code> or <code>29.99</code>, not cents. Zero makes the plan free.</p>

<fieldset class="mt-5">
    <legend class="text-sm font-medium heading">Quotas (how many)</legend>
    <p class="mt-1 text-xs muted">Numeric limits. Use <code>-1</code> for unlimited. These are not feature toggles.</p>

    <div class="mt-3 grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-white/10 sm:grid-cols-2">
        <label class="text-xs heading">BYOS servers<span class="mt-0.5 block font-normal muted">How many servers on the customer’s own cloud / IP</span><input class="field mt-1" type="number" min="-1" name="servers" value="{{ old('servers', $plan?->limits['servers'] ?? 1) }}" required></label>
        <label class="text-xs heading">Managed servers<span class="mt-0.5 block font-normal muted">How many platform-hosted VPS on this plan</span><input class="field mt-1" type="number" min="-1" name="managed_servers" value="{{ old('managed_servers', $plan?->limits['managed_servers'] ?? 0) }}" required></label>
        <label class="text-xs heading">BYOS sites<span class="mt-0.5 block font-normal muted">Sites hosted on BYOS / custom servers</span><input class="field mt-1" type="number" min="-1" name="sites" value="{{ old('sites', $plan?->limits['sites'] ?? 1) }}" required></label>
        <label class="text-xs heading">Managed sites<span class="mt-0.5 block font-normal muted">Sites hosted on platform-managed servers</span><input class="field mt-1" type="number" min="-1" name="managed_sites" value="{{ old('managed_sites', $plan?->limits['managed_sites'] ?? 0) }}" required></label>
    </div>

    <div class="mt-3 grid gap-3 sm:grid-cols-3">
        @foreach($limitKeys as $key => $label)
            @if(! in_array($key, ['servers', 'managed_servers', 'sites', 'managed_sites'], true))
                <label class="text-xs muted">{{ $label }}<input class="field" type="number" min="-1" name="{{ $key }}" value="{{ old($key, $plan?->limits[$key] ?? 1) }}" required></label>
            @endif
        @endforeach
    </div>
</fieldset>

<fieldset class="mt-5">
    <legend class="text-sm font-medium heading">Gated features (on / off)</legend>
    <p class="mt-1 text-xs muted">Turn modules on or off. Server and site <em>counts</em> stay under Quotas — do not confuse access toggles with limits.</p>

    <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.12em] muted">Hosting access</p>
    <div class="mt-2 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        @foreach($accessFeatureKeys as $key)
            @if(isset($featureKeys[$key]))
                <label class="flex items-start gap-2 heading">
                    <input class="mt-1" type="checkbox" name="feature_{{ $key }}" value="1" @checked(old('feature_'.$key, $plan?->features[$key] ?? false))>
                    <span>
                        <span class="block">{{ $featureKeys[$key] }}</span>
                        <span class="mt-0.5 block text-xs font-normal muted">
                            @if($key === 'providers')
                                Allows Providers, BYOS provision, and attach-by-IP. Pair with the BYOS server / site quotas above.
                            @else
                                Allows Managed server provision. Pair with the Managed server / site quotas above.
                            @endif
                        </span>
                    </span>
                </label>
            @endif
        @endforeach
    </div>

    <p class="mt-5 text-[11px] font-semibold uppercase tracking-[0.12em] muted">Console modules</p>
    <div class="mt-2 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
        @foreach($moduleFeatureKeys as $key => $label)
            <label class="flex items-center gap-2 heading"><input type="checkbox" name="feature_{{ $key }}" value="1" @checked(old('feature_'.$key, $plan?->features[$key] ?? false))>{{ $label }}</label>
        @endforeach
    </div>
</fieldset>

<div class="mt-5 flex flex-wrap items-end gap-x-6 gap-y-3 text-sm">
    <label class="flex items-center gap-2 heading"><input type="checkbox" name="active" value="1" @checked(old('active', $plan?->active ?? true))>Active</label>
    <label class="flex items-center gap-2 heading"><input type="checkbox" name="public" value="1" @checked(old('public', $plan?->public ?? true))>Listed publicly</label>
    <label class="text-xs muted">Sort order<input class="field !w-24" type="number" min="0" max="1000" name="sort_order" value="{{ old('sort_order', $plan?->sort_order ?? 50) }}"></label>
</div>
