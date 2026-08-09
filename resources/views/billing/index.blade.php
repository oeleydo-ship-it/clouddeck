@extends('layouts.app')
@section('content')
<div class="app-main">
    <div>
        <p class="page-eyebrow">Subscription</p>
        <h1 class="page-title">Plans and usage</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            Current plan:
            <span class="font-semibold heading">{{ $plan?->name ?? 'Unmetered legacy account' }}</span>
            @if($subscription?->current_period_ends_at)
                · {{ $subscription->cancel_at_period_end ? 'ends' : 'renews' }} {{ $subscription->current_period_ends_at->toFormattedDateString() }}
            @endif
        </p>
    </div>
    @error('billing')
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $message }}</div>
    @enderror
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($usage as $resource=>$value)
                    <div class="panel p-5">
                        <div class="flex justify-between text-sm">
                            <span class="capitalize text-slate-500 dark:text-slate-400">{{ str_replace('_',' ',$resource) }}</span>
                            <span>{{ $value['used'] }} / {{ $value['limit'] < 0 ? 'Unlimited' : $value['limit'] }}{{ $resource === 'os_backup_gb' ? ' GB' : '' }}</span>
                        </div>
                        @if($value['limit']>0)
                            <div class="mt-3 h-2 rounded bg-slate-100 dark:bg-white/10"><div class="h-full rounded bg-cyan-400" style="width:{{ min(100,$value['used']*100/$value['limit']) }}%"></div></div>
                        @endif
                    </div>
                @endforeach
            </div>
    @php
        $managedServersEnabled = $managedServersEnabled ?? false;
        $limitLabels = $managedServersEnabled
            ? ['servers' => 'BYOS servers', 'managed_servers' => 'Managed servers', 'sites' => 'BYOS sites', 'managed_sites' => 'Managed sites', 'databases' => 'Databases', 'api_tokens' => 'API tokens', 'teams' => 'Teams', 'team_members' => 'Team members', 'os_backup_gb' => 'OS backup storage (GB)']
            : ['servers' => 'Servers', 'sites' => 'Sites', 'databases' => 'Databases', 'api_tokens' => 'API tokens', 'teams' => 'Teams', 'team_members' => 'Team members', 'os_backup_gb' => 'OS backup storage (GB)'];
        $serverNotes = [
            'servers' => 'Provision on your own cloud account. No infra cost from us.',
            'managed_servers' => 'We create and host the VPS for you — no cloud account needed.',
            'sites' => $managedServersEnabled ? 'Sites hosted on your BYOS / custom servers.' : 'Sites you can host on your servers.',
            'managed_sites' => 'Sites hosted on platform-managed servers.',
        ];
        // managed_servers already renders above as a quota card; skip it here so it is not
        // listed a second time as a plain checkbox.
        $featureLabels = collect(\App\Services\FeatureManager::catalog())->except('managed_servers')->all();
        $price = fn ($cents, $currency) => $cents === 0 ? 'Free' : Str::upper($currency).' '.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
        $highlightedLimits = $managedServersEnabled
            ? ['servers' => 'bg-slate-50 dark:bg-white/[.03]', 'managed_servers' => 'bg-cyan-50/60 dark:bg-cyan-400/[.06]', 'sites' => 'bg-slate-50 dark:bg-white/[.03]', 'managed_sites' => 'bg-cyan-50/60 dark:bg-cyan-400/[.06]']
            : ['servers' => 'bg-slate-50 dark:bg-white/[.03]', 'sites' => 'bg-slate-50 dark:bg-white/[.03]'];
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

                <div class="mt-6 grid gap-2 sm:grid-cols-2">
                    @foreach($highlightedLimits as $key => $tint)
                        @if(array_key_exists($key, $available->limits ?? []))
                            <div class="rounded-xl {{ $tint }} p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-xs font-medium heading">{{ $limitLabels[$key] }}</dt>
                                    <dd class="text-sm font-semibold heading">{{ ($available->limits[$key] ?? 0) < 0 ? 'Unlimited' : $available->limits[$key] }}</dd>
                                </div>
                                <p class="mt-1 text-[11px] leading-snug muted">{{ $serverNotes[$key] }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>

                <dl class="mt-4 grid grow grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    @foreach($limitLabels as $key => $label)
                        @if(! array_key_exists($key, $highlightedLimits) && array_key_exists($key, $available->limits ?? []))
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
                    @php
                        $canCheckout = ($stripeEnabled ?? false)
                            && ($available->stripe_monthly_price_id || $available->stripe_yearly_price_id);
                    @endphp
                    @if($canCheckout)
                        <form method="POST" action="{{ route('billing.checkout') }}" class="mt-6 space-y-3">@csrf
                            <input type="hidden" name="plan_id" value="{{ $available->id }}">
                            <select class="field mt-0" name="billing_cycle" aria-label="Billing cycle for {{ $available->name }}">
                                @if($available->stripe_monthly_price_id)
                                    <option value="monthly">{{ $price($available->monthly_price, $available->currency) }} / month</option>
                                @endif
                                @if($available->stripe_yearly_price_id)
                                    <option value="yearly">{{ $price($available->yearly_price, $available->currency) }} / year</option>
                                @endif
                            </select>
                            <button class="button-primary w-full">Pay &amp; subscribe</button>
                            <p class="text-center text-xs muted">Secure checkout hosted by Stripe — no approval wait.</p>
                        </form>
                    @else
                        <form method="POST" action="{{ route('billing.request') }}" class="mt-6 space-y-3">@csrf
                            <input type="hidden" name="plan_id" value="{{ $available->id }}">
                            <select class="field mt-0" name="billing_cycle" aria-label="Billing cycle for {{ $available->name }}">
                                <option value="monthly">Monthly billing</option>
                                @if($available->yearly_price)<option value="yearly">Yearly billing</option>@endif
                            </select>
                            <textarea class="field mt-0" name="customer_note" rows="2" placeholder="Billing or purchase-order notes (optional)"></textarea>
                            <button class="button-primary w-full">Request this plan</button>
                            <p class="text-center text-xs muted">Online checkout is not mapped for this plan yet — an administrator will review your request.</p>
                        </form>
                    @endif
                @endif
            </article>
        @empty
            <div class="panel text-center lg:col-span-3">
                <p class="font-medium heading">No plans are available yet</p>
                <p class="mt-1 text-sm muted">An administrator has not published one. Nothing can be subscribed to until they do.</p>
            </div>
        @endforelse
    </div>

    <section class="panel mt-8">
        <h2 class="font-semibold heading">OS backup storage</h2>
        <p class="mt-1 text-sm muted">
            Provider snapshot capacity is limited by your plan
            (
            @if(($usage['os_backup_gb']['plan_limit'] ?? 0) < 0)
                unlimited
            @else
                {{ $usage['os_backup_gb']['plan_limit'] ?? 0 }} GB included
            @endif
            )
            @if(($osBackupAddonGb ?? 0) > 0)
                plus <strong class="heading">{{ $osBackupAddonGb }} GB</strong> add-on
            @endif.
            Using <strong class="heading">{{ $usage['os_backup_gb']['used'] ?? 0 }}</strong>
            of
            <strong class="heading">{{ ($usage['os_backup_gb']['limit'] ?? 0) < 0 ? 'Unlimited' : ($usage['os_backup_gb']['limit'] ?? 0).' GB' }}</strong>.
        </p>
        @if($stripeEnabled ?? false)
            @if($osBackupAddonActive ?? false)
                <p class="mt-4 text-sm muted">Manage or cancel your GB add-on in the Stripe customer portal.</p>
                <form method="POST" action="{{ route('billing.portal') }}" class="mt-3">@csrf
                    <button class="button-secondary">Open billing portal</button>
                </form>
            @else
                <form method="POST" action="{{ route('billing.os-backup') }}" class="mt-5 flex flex-wrap items-end gap-3">@csrf
                    <label class="text-sm heading">Extra GB / month
                        <input class="field mt-1 !w-32" type="number" name="gigabytes" min="1" max="10000" value="50" required>
                    </label>
                    <button class="button-primary">Buy with Stripe</button>
                    <p class="basis-full text-xs muted">{{ number_format(($osBackupGbPriceCents ?? 50) / 100, 2) }} USD per GB / month. Stacks on top of plan-included capacity.</p>
                </form>
            @endif
        @else
            <p class="mt-4 text-sm muted">Stripe is not configured — ask an administrator to enable checkout for OS backup add-ons.</p>
        @endif
    </section>

    @if($requests->isNotEmpty())
        <section class="panel mt-8">
            <h2 class="font-semibold">Plan requests</h2>
            <p class="mt-1 text-sm muted">Manual requests for plans that are not mapped to Stripe checkout.</p>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                @foreach($requests as $change)
                    <div class="flex justify-between py-3 text-sm">
                        <span>{{ $change->plan->name }} / {{ $change->billing_cycle }}</span>
                        <span class="capitalize">{{ $change->status }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
    @include('billing.partials.stripe')
</div>
@endsection
