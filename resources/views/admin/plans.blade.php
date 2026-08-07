@extends('layouts.admin')
@section('admin-title', 'Plans')
@section('admin-description', 'Pricing, limits, and entitlements offered to customers.')
@section('admin')
    @php
        $limitKeys = ['servers' => 'Servers', 'sites' => 'Sites', 'databases' => 'Databases', 'api_tokens' => 'API tokens', 'teams' => 'Teams', 'team_members' => 'Team members'];
        $featureKeys = \App\Services\FeatureManager::catalog();
        $amount = fn (int $cents, string $currency) => $cents === 0 ? 'Free' : Str::upper($currency).' '.number_format($cents / 100, ($cents % 100 === 0) ? 0 : 2);
        $limit = fn ($value) => $value === -1 ? 'Unlimited' : (int) $value;
    @endphp

    <div x-data="{ editing: null, creating: false }" class="space-y-6">
        <div class="flex justify-end">
            <button type="button" @click="creating = ! creating; editing = null" class="button-primary" x-text="creating ? 'Cancel' : 'New plan'">New plan</button>
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

                    <dl class="mt-5 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                        @foreach($limitKeys as $key => $label)
                            <div>
                                <dt class="text-xs muted">{{ $label }}</dt>
                                <dd class="heading">{{ $limit($plan->limits[$key] ?? 0) }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($featureKeys as $key => $label)
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
