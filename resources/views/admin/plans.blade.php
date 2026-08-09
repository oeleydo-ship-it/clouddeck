@extends('layouts.admin')
@section('admin-title', 'Plans')
@section('admin-description', 'Pricing, limits, and entitlements offered to customers.')
@section('admin')
    @php
        $limitKeys = [
            'servers' => 'BYOS servers',
            'managed_servers' => 'Managed servers',
            'sites' => 'BYOS sites',
            'managed_sites' => 'Managed sites',
            'databases' => 'Databases',
            'api_tokens' => 'API tokens',
            'teams' => 'Teams',
            'team_members' => 'Team members',
            'os_backup_gb' => 'OS backup storage (GB)',
        ];
        $featureKeys = \App\Services\FeatureManager::catalog();
        // Quotas already show server/site counts above. Hide the matching access toggles from
        // the module badge list so BYOS / Managed are not listed twice as module names.
        $displayFeatureKeys = collect($featureKeys)->except(['managed_servers', 'providers'])->all();
        $amount = fn (int $cents, string $currency) => $cents === 0 ? 'Free' : Str::upper($currency).' '.number_format($cents / 100, ($cents % 100 === 0) ? 0 : 2);
        $limit = fn ($value) => $value === -1 ? 'Unlimited' : (int) $value;
        $quotaGate = [
            'servers' => 'providers',
            'sites' => 'providers',
            'managed_servers' => 'managed_servers',
            'managed_sites' => 'managed_servers',
        ];
    @endphp

    <div x-data="{ editing: null, creating: false }" class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-2xl text-sm muted"><strong class="heading">Quotas</strong> set how many resources a subscriber may create. <strong class="heading">Gated features</strong> turn modules on or off. Saving a plan with a non-zero BYOS or Managed quota automatically turns on the matching access gate so customers are not stuck with “Access off” while a count is advertised.</p>
            <button type="button" @click="creating = ! creating; editing = null" class="button-primary shrink-0" x-text="creating ? 'Cancel' : 'New plan'">New plan</button>
        </div>

        {{-- Create --}}
        <section x-cloak x-show="creating" class="panel">
            <h2 class="font-semibold heading">Create plan</h2>
            <form method="POST" action="{{ route('admin.plans.store') }}" class="mt-5">@csrf
                @include('admin.partials.plan-fields', ['plan' => null, 'limitKeys' => $limitKeys, 'featureKeys' => $featureKeys])
                <button class="button-primary mt-6">Create plan</button>
            </form>
        </section>

        {{-- List --}}
        <div class="grid gap-4 xl:grid-cols-2">
            @forelse($plans as $plan)
                <article class="panel flex flex-col" wire:key="plan-{{ $plan->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold heading">{{ $plan->name }}</h3>
                                @unless($plan->active)<span class="badge bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300">Inactive</span>@endunless
                                @unless($plan->public)<span class="badge bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Private</span>@endunless
                            </div>
                            <p class="mt-1 font-mono text-xs muted">{{ $plan->slug }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xl font-semibold heading">{{ $amount($plan->monthly_price, $plan->currency) }}<span class="text-sm font-normal muted">{{ $plan->monthly_price ? '/mo' : '' }}</span></p>
                            @if($plan->yearly_price)<p class="text-xs muted">{{ $amount($plan->yearly_price, $plan->currency) }}/yr</p>@endif
                        </div>
                    </div>

                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-[0.12em] muted">Quotas</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach(['servers' => 'bg-slate-50 dark:bg-white/[.03]', 'managed_servers' => 'bg-cyan-50/60 dark:bg-cyan-400/[.06]', 'sites' => 'bg-slate-50 dark:bg-white/[.03]', 'managed_sites' => 'bg-cyan-50/60 dark:bg-cyan-400/[.06]'] as $key => $tint)
                            @php
                                $gateKey = $quotaGate[$key] ?? null;
                                $accessOff = $gateKey !== null && ! ($plan->features[$gateKey] ?? false);
                            @endphp
                            <div @class([
                                'flex items-center justify-between gap-2 rounded-xl p-3',
                                $tint => ! $accessOff,
                                'bg-slate-100/80 opacity-60 dark:bg-white/[.04]' => $accessOff,
                            ])>
                                <div class="min-w-0">
                                    <dt class="text-xs font-medium heading">{{ $limitKeys[$key] }}</dt>
                                    @if($accessOff)
                                        <p class="mt-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Access off — quota unused</p>
                                    @endif
                                </div>
                                <dd class="text-sm font-semibold heading">{{ $limit($plan->limits[$key] ?? 0) }}</dd>
                            </div>
                        @endforeach
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                        @foreach($limitKeys as $key => $label)
                            @if(! in_array($key, ['servers', 'managed_servers', 'sites', 'managed_sites'], true))
                                <div>
                                    <dt class="text-xs muted">{{ $label }}</dt>
                                    <dd class="heading">{{ $limit($plan->limits[$key] ?? 0) }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-[0.12em] muted">Gated features</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        {{-- Access gates for BYOS / Managed — short labels, not the quota names. --}}
                        @foreach(['providers' => 'BYOS access', 'managed_servers' => 'Managed access'] as $gateKey => $gateLabel)
                            <span @class([
                                'badge',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $plan->features[$gateKey] ?? false,
                                'bg-slate-100 text-slate-500 line-through dark:bg-white/5 dark:text-slate-500' => ! ($plan->features[$gateKey] ?? false),
                            ])>{{ $gateLabel }}</span>
                        @endforeach
                        @foreach($displayFeatureKeys as $key => $label)
                            <span @class([
                                'badge',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $plan->features[$key] ?? false,
                                'bg-slate-100 text-slate-500 line-through dark:bg-white/5 dark:text-slate-500' => ! ($plan->features[$key] ?? false),
                            ])>{{ $label }}</span>
                        @endforeach
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-white/5">
                        <p class="text-sm muted">{{ $plan->subscriptions_count }} {{ Str::plural('subscription', $plan->subscriptions_count) }}</p>
                        <div class="flex gap-2">
                            <button type="button" @click="editing = editing === '{{ $plan->id }}' ? null : '{{ $plan->id }}'; creating = false" class="button-secondary !px-3 !py-1.5 text-xs" x-text="editing === '{{ $plan->id }}' ? 'Close' : 'Edit'">Edit</button>
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete the {{ $plan->name }} plan?')">@csrf @method('DELETE')
                                <button class="button-secondary !px-3 !py-1.5 text-xs !text-rose-600 dark:!text-rose-300" @disabled($plan->subscriptions_count > 0) title="{{ $plan->subscriptions_count > 0 ? 'Move its subscribers to another plan first' : 'Delete this plan' }}">Delete</button>
                            </form>
                        </div>
                    </div>

                    <div x-cloak x-show="editing === '{{ $plan->id }}'" class="mt-5 border-t border-slate-100 pt-5 dark:border-white/5">
                        <form method="POST" action="{{ route('admin.plans.update', $plan) }}">@csrf @method('PATCH')
                            @include('admin.partials.plan-fields', ['plan' => $plan, 'limitKeys' => $limitKeys, 'featureKeys' => $featureKeys])
                            <button class="button-primary mt-6">Save {{ $plan->name }}</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="panel text-center xl:col-span-2">
                    <p class="font-medium heading">No plans yet</p>
                    <p class="mt-1 text-sm muted">Customers cannot sign up without one to land on.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
