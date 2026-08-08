@if($stripeEnabled)
    <section class="panel mt-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold">Billing portal</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Update payment methods, download invoices, or cancel in Stripe’s customer portal.</p>
            </div>
            @if(auth()->user()->stripe_customer_id)
                <form method="POST" action="{{ route('billing.portal') }}">@csrf
                    <button class="button-secondary">Manage billing in Stripe</button>
                </form>
            @else
                <p class="text-sm muted">Subscribe to a plan above to open the Stripe customer portal.</p>
            @endif
        </div>
    </section>
@endif
<section class="panel mt-8">
    <h2 class="font-semibold">Invoices</h2>
    <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
        @forelse($invoices as $invoice)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                <div>
                    <p>{{ $invoice->number ?? $invoice->provider_invoice_id }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->total / 100, 2) }} / {{ $invoice->status }} / {{ $invoice->created_at->toFormattedDateString() }}</p>
                </div>
                @if($invoice->hosted_invoice_url)
                    <a class="text-cyan-600 dark:text-cyan-300" href="{{ $invoice->hosted_invoice_url }}" rel="noopener noreferrer">View invoice</a>
                @endif
            </div>
        @empty
            <p class="py-4 text-sm text-slate-500 dark:text-slate-400">No provider invoices.</p>
        @endforelse
    </div>
</section>
