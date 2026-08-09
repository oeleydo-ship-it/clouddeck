@php
    $settings = $settings ?? collect();
    $keySaved = filled($settings->get('stripe_key')?->value) || filled(config('services.stripe.key'));
    $secretSaved = filled($settings->get('stripe_secret')?->value) || filled(config('services.stripe.secret'));
    $webhookSaved = filled($settings->get('stripe_webhook_secret')?->value) || filled(config('services.stripe.webhook_secret'));
    $webhookUrl = url('/api/billing/stripe/webhook');
@endphp

<div class="space-y-6">
    <section class="panel">
        <h2 class="font-semibold heading">Stripe API &amp; webhook</h2>
        <p class="mt-1 text-sm muted">Credentials are stored encrypted in system settings and override <code>.env</code> when set. Leave a secret field blank to keep the stored value.</p>

        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 rounded-xl bg-slate-100 p-4 text-sm dark:bg-black/20">
            <span class="{{ $keySaved ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' }}">Publishable key {{ $keySaved ? 'configured' : 'missing' }}</span>
            <span class="{{ $secretSaved ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' }}">Secret key {{ $secretSaved ? 'configured' : 'missing' }}</span>
            <span class="{{ $webhookSaved ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' }}">Webhook secret {{ $webhookSaved ? 'configured' : 'missing' }}</span>
        </div>

        <form method="POST" action="{{ route('admin.settings.stripe') }}" class="mt-5 max-w-2xl space-y-4">@csrf @method('PUT')
            <label class="block text-sm heading">Publishable key
                <input class="field font-mono text-xs" type="password" name="stripe_key" autocomplete="new-password" placeholder="{{ $keySaved ? 'Saved — leave blank to keep it' : 'pk_live_...' }}">
            </label>
            <label class="block text-sm heading">Secret key
                <input class="field font-mono text-xs" type="password" name="stripe_secret" autocomplete="new-password" placeholder="{{ $secretSaved ? 'Saved — leave blank to keep it' : 'sk_live_...' }}">
            </label>
            <label class="block text-sm heading">Webhook signing secret
                <input class="field font-mono text-xs" type="password" name="stripe_webhook_secret" autocomplete="new-password" placeholder="{{ $webhookSaved ? 'Saved — leave blank to keep it' : 'whsec_...' }}">
            </label>
            <button class="button-primary mt-2">Save Stripe credentials</button>
        </form>

        <div class="mt-6 max-w-2xl space-y-2 border-t border-slate-200 pt-5 dark:border-white/10">
            <h3 class="text-sm font-semibold heading">Webhook endpoint</h3>
            <p class="text-sm muted">In the Stripe Dashboard → Developers → Webhooks, add this URL and subscribe to subscription and invoice events (see docs).</p>
            <div class="flex flex-wrap items-center gap-2">
                <code class="block flex-1 break-all rounded-lg bg-slate-100 px-3 py-2 font-mono text-xs dark:bg-black/30">{{ $webhookUrl }}</code>
            </div>
            <p class="text-xs muted">Method: <code>POST</code>. Copy the signing secret Stripe shows after creating the endpoint into the field above.</p>
        </div>
    </section>

    <section class="panel">
        <h2 class="font-semibold heading">OS backup storage add-on</h2>
        <p class="mt-1 text-sm muted">Customers can buy extra provider-snapshot capacity (GB / month) on Billing. Plans also set an included <code>os_backup_gb</code> quota under Admin → Plans.</p>
        <form method="POST" action="{{ route('admin.settings.os-backup-pricing') }}" class="mt-5 max-w-md space-y-4">@csrf @method('PUT')
            <label class="block text-sm heading">Price per GB / month (USD)
                <input class="field" type="number" step="0.01" min="0.50" name="os_backup_gb_price" value="{{ number_format(app(\App\Services\SystemSettings::class)->osBackupGbPriceCents() / 100, 2, '.', '') }}" required>
            </label>
            <p class="text-xs muted">Minimum $0.50. Checkout creates a recurring Stripe subscription with quantity = GB purchased.</p>
            <button class="button-primary">Save OS backup pricing</button>
        </form>
    </section>

    <section class="panel">
        <h2 class="font-semibold heading">Plan price mapping</h2>
        <p class="mt-2 text-sm muted">Add recurring Stripe Price IDs so hosted checkout can sell each plan. Plans without a mapped price stay on the manual billing-request flow.</p>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach($plans as $plan)
            <form method="POST" action="{{ route('admin.plans.stripe', $plan) }}" class="panel">
                @csrf @method('PATCH')
                <div class="flex justify-between">
                    <h3 class="font-semibold">{{ $plan->name }}</h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $plan->currency }}</span>
                </div>
                <label class="mt-4 block text-sm">Monthly Stripe Price ID<input class="field font-mono" name="stripe_monthly_price_id" value="{{ $plan->stripe_monthly_price_id }}" placeholder="price_... (interval: month)"></label>
                <label class="mt-4 block text-sm">Yearly Stripe Price ID<input class="field font-mono" name="stripe_yearly_price_id" value="{{ $plan->stripe_yearly_price_id }}" placeholder="price_... (interval: year)"></label>
                <p class="mt-2 text-xs muted">Use different Price IDs. Mapping a monthly Price into the yearly field makes Checkout stay monthly.</p>
                <button class="button-secondary mt-4">Save Stripe mapping</button>
            </form>
        @endforeach
    </div>
</div>
