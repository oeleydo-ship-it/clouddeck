import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';

export default function Payments({ plans, webhookUrl, keySaved, secretSaved, webhookSaved, stripeLabel }: any) {
    const form = useForm({ stripe_key: '', stripe_secret: '', stripe_webhook_secret: '' });
    return (
        <AdminLayout
            title="Payments"
            description="Stripe keys for checkout and the price IDs mapped to each plan."
            actions={<button form="stripe-form" className="button-primary">Save Stripe</button>}
        >
            <form id="stripe-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.stripe')); }} className="panel space-y-4">
                <div>
                    <h2 className="section-title">{stripeLabel || 'Stripe API'}</h2>
                    <p className="field-hint">Webhook endpoint: <code className="font-mono text-xs">{webhookUrl || '/api/billing/stripe/webhook'}</code></p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="field-label sm:col-span-2">Publishable key
                        <input className="field font-mono text-xs" type="password" name="stripe_key" placeholder={keySaved ? 'Saved — leave blank to keep it' : 'pk_live_...'} value={form.data.stripe_key} onChange={(e) => form.setData('stripe_key', e.target.value)} autoComplete="off" />
                    </label>
                    <label className="field-label">Secret key
                        <input className="field font-mono text-xs" type="password" name="stripe_secret" placeholder={secretSaved ? 'Saved — leave blank to keep it' : 'sk_live_...'} value={form.data.stripe_secret} onChange={(e) => form.setData('stripe_secret', e.target.value)} autoComplete="off" />
                    </label>
                    <label className="field-label">Webhook secret
                        <input className="field font-mono text-xs" type="password" name="stripe_webhook_secret" placeholder={webhookSaved ? 'Saved — leave blank to keep it' : 'whsec_...'} value={form.data.stripe_webhook_secret} onChange={(e) => form.setData('stripe_webhook_secret', e.target.value)} autoComplete="off" />
                    </label>
                </div>
            </form>
            <section className="panel space-y-5">
                <div>
                    <h2 className="section-title">Plan Price IDs</h2>
                    <p className="field-hint">Must be Stripe Price IDs (price_…), not product IDs.</p>
                </div>
                {plans.map((plan: any) => (
                    <PlanStripe key={plan.id} plan={plan} />
                ))}
            </section>
        </AdminLayout>
    );
}

function PlanStripe({ plan }: any) {
    const form = useForm({ stripe_monthly_price_id: plan.stripe_monthly_price_id || '', stripe_yearly_price_id: plan.stripe_yearly_price_id || '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.patch(route('admin.plans.stripe', plan.id)); }} className="well grid gap-4 sm:grid-cols-2">
            <p className="sm:col-span-2 text-sm font-semibold heading">{plan.name}</p>
            <label className="field-label">Monthly Stripe Price ID
                <input className="field font-mono text-xs" name="stripe_monthly_price_id" value={form.data.stripe_monthly_price_id} onChange={(e) => form.setData('stripe_monthly_price_id', e.target.value)} placeholder="price_" />
            </label>
            <label className="field-label">Yearly Stripe Price ID
                <input className="field font-mono text-xs" name="stripe_yearly_price_id" value={form.data.stripe_yearly_price_id} onChange={(e) => form.setData('stripe_yearly_price_id', e.target.value)} placeholder="price_" />
            </label>
            <div className="sm:col-span-2">
                <button className="button-secondary">Save</button>
            </div>
        </form>
    );
}
