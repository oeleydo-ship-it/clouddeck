{{-- Shared by the create form and each plan's edit form, so the two can never drift apart. --}}
@php
    $currency = $plan?->currency ?? 'USD';
    // Minor units back to what was typed: 2900 reads as 29, 2999 stays 29.99.
    $money = function (?int $cents): string {
        $trimmed = rtrim(rtrim(number_format(($cents ?? 0) / 100, 2, '.', ''), '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    };
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
    <legend class="text-sm font-medium heading">Limits</legend>
    <p class="mt-1 text-xs muted">Use <code>-1</code> for unlimited.</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-3">
        @foreach($limitKeys as $key => $label)
            <label class="text-xs muted">{{ $label }}<input class="field" type="number" min="-1" name="{{ $key }}" value="{{ old($key, $plan?->limits[$key] ?? 1) }}" required></label>
        @endforeach
    </div>
</fieldset>

<fieldset class="mt-5">
    <legend class="text-sm font-medium heading">Feature entitlements</legend>
    <p class="mt-1 text-xs muted">Optional modules. Server and site counts are set under Limits above — not here.</p>
    <div class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
        @foreach($featureKeys as $key => $label)
            <label class="flex items-center gap-2 heading"><input type="checkbox" name="feature_{{ $key }}" value="1" @checked(old('feature_'.$key, $plan?->features[$key] ?? false))>{{ $label }}</label>
        @endforeach
    </div>
</fieldset>

<div class="mt-5 flex flex-wrap items-end gap-x-6 gap-y-3 text-sm">
    <label class="flex items-center gap-2 heading"><input type="checkbox" name="active" value="1" @checked(old('active', $plan?->active ?? true))>Active</label>
    <label class="flex items-center gap-2 heading"><input type="checkbox" name="public" value="1" @checked(old('public', $plan?->public ?? true))>Listed publicly</label>
    <label class="text-xs muted">Sort order<input class="field !w-24" type="number" min="0" max="1000" name="sort_order" value="{{ old('sort_order', $plan?->sort_order ?? 50) }}"></label>
</div>
