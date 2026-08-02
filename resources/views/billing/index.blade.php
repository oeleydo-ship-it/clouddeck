@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-5 py-10">
    <div><p class="text-sm text-cyan-600 dark:text-cyan-300">Subscription</p><h1 class="mt-1 text-3xl font-semibold">Plans and usage</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Current plan: {{ $plan?->name ?? 'Unmetered legacy account' }} @if($subscription?->current_period_ends_at)/ renews or ends {{ $subscription->current_period_ends_at->toFormattedDateString() }}@endif</p></div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($usage as $resource=>$value)<div class="panel p-5"><div class="flex justify-between text-sm"><span class="capitalize text-slate-500 dark:text-slate-400">{{ str_replace('_',' ',$resource) }}</span><span>{{ $value['used'] }} / {{ $value['limit'] < 0 ? 'Unlimited' : $value['limit'] }}</span></div>@if($value['limit']>0)<div class="mt-3 h-2 rounded bg-slate-100 dark:bg-white/10"><div class="h-full rounded bg-cyan-400" style="width:{{ min(100,$value['used']*100/$value['limit']) }}%"></div></div>@endif</div>@endforeach</div>
    @php
        $limitLabels = ['servers' => 'Servers', 'sites' => 'Sites', 'databases' => 'Databases', 'api_tokens' => 'API tokens', 'teams' => 'Teams', 'team_members' => 'Team members'];
        $featureLabels = ['monitoring' => 'Monitoring and alerts', 'remote_management' => 'Remote management', 'teams' => 'Team collaboration'];
        $price = fn ($cents, $currency) => $cents === 0 ? 'Free' : Str::upper($currency).' '.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    @endphp
    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        @forelse($plans as $available)
            @php $current = $plan?->id === $available->id; @endphp
            <article @class(['panel flex flex-col', 'ring-2 ring-cyan-500 dark:ring-cyan-400' => $current])>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold heading">{{ $available->name }}</h2>
                        <p class="mt-2 text-3xl font-semibold heading">{{ $price($available->monthly_price, $available->currency) }}@if($available->monthly_price)<small class="text-sm font-normal muted">/month</small>@endif</p>
                        @if($available->yearly_price)<p class="mt-1 text-sm muted">or {{ $price($available->yearly_price, $available->currency) }} billed yearly</p>@endif
                    </div>
                    @if($current)<span class="badge bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Current</span>@endif
                </div>

                <dl class="mt-6 grid grow grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    @foreach($limitLabels as $key => $label)
                        @if(array_key_exists($key, $available->limits ?? []))
                            <div>
                                <dt class="text-xs muted">{{ $label }}</dt>
                                <dd class="heading">{{ ($available->limits[$key] ?? 0) < 0 ? 'Unlimited' : $available->limits[$key] }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                {{-- What the plan grants, and what it withholds. A list of only the included
                     features reads as though everything else is included too. --}}
                <ul class="mt-5 space-y-1.5 border-t border-slate-100 pt-4 text-sm dark:border-white/5">
                    @foreach($featureLabels as $key => $label)
                        @php $included = (bool) ($available->features[$key] ?? false); @endphp
                        <li class="flex items-center gap-2 {{ $included ? 'heading' : 'muted line-through' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0 {{ $included ? 'text-emerald-500' : 'text-slate-300 dark:text-slate-600' }}">
                                @if($included)<path d="M20 6 9 17l-5-5"/>@else<path d="M18 6 6 18M6 6l12 12"/>@endif
                            </svg>
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>

                @if($current)
                    <p class="mt-6 text-center text-sm muted">This is your current plan.</p>
                @else
                    <form method="POST" action="{{ route('billing.request') }}" class="mt-6 space-y-3">@csrf
                        <input type="hidden" name="plan_id" value="{{ $available->id }}">
                        <select class="field mt-0" name="billing_cycle" aria-label="Billing cycle for {{ $available->name }}">
                            <option value="monthly">Monthly billing</option>
                            @if($available->yearly_price)<option value="yearly">Yearly billing</option>@endif
                        </select>
                        <textarea class="field mt-0" name="customer_note" rows="2" placeholder="Billing or purchase-order notes (optional)"></textarea>
                        <button class="button-primary w-full">Request this plan</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="panel text-center lg:col-span-3">
                <p class="font-medium heading">No plans are available yet</p>
                <p class="mt-1 text-sm muted">An administrator has not published one. Nothing can be subscribed to until they do.</p>
            </div>
        @endforelse
    </div>

    <section class="panel mt-8"><h2 class="font-semibold">Plan requests</h2><div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">@forelse($requests as $change)<div class="flex justify-between py-3 text-sm"><span>{{ $change->plan->name }} / {{ $change->billing_cycle }}</span><span class="capitalize">{{ $change->status }}</span></div>@empty<p class="py-4 text-sm text-slate-500 dark:text-slate-400">No plan change requests.</p>@endforelse</div></section>
    @include('billing.partials.stripe')
</div>
@endsection
