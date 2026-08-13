import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { checked, setting } from '../../lib/ui';

type Size = {
    slug: string;
    description?: string | null;
    spec?: string;
    infra_label?: string | null;
    price_monthly?: number;
    suggested?: number;
};

export default function ManagedServers({ settings, ready, tokenSaved, managedSizes, managedMarkupPercent, managedSizePrices }: any) {
    const form = useForm({
        managed_servers_enabled: checked(settings, 'managed_servers_enabled', false),
        managed_cloud_provider: setting(settings, 'managed_cloud_provider', 'digitalocean'),
        managed_cloud_token: '',
    });
    const pricing = useForm({ markup_percent: managedMarkupPercent || 0, prices: managedSizePrices || {} });
    const sizes: Size[] = Array.isArray(managedSizes) ? managedSizes : [];

    return (
        <AdminLayout title="Managed servers" description="Platform-owned cloud credentials and the customer prices billed on top of provider cost.">
            <form onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.managed-servers')); }} className="panel space-y-5">
                <div>
                    <h2 className="section-title">Provider</h2>
                    <p className="field-hint">One token for the whole platform. Customers never see this credential.</p>
                </div>
                <label className="check-row">
                    <input type="checkbox" checked={form.data.managed_servers_enabled} onChange={(e) => form.setData('managed_servers_enabled', e.target.checked)} />
                    <span>
                        Enable managed servers
                        <span className="mt-0.5 block text-xs font-normal muted">Lets entitled customers provision hosts from this catalog instead of bringing their own cloud.</span>
                    </span>
                </label>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="field-label">Provider
                        <select className="field" value={form.data.managed_cloud_provider} onChange={(e) => form.setData('managed_cloud_provider', e.target.value)}>
                            <option value="digitalocean">DigitalOcean</option>
                            <option value="hetzner">Hetzner</option>
                        </select>
                    </label>
                    <label className="field-label">API token
                        <input className="field font-mono text-xs" type="password" name="managed_cloud_token" placeholder={tokenSaved || ready ? 'Saved — leave blank to keep it' : 'Paste a read/write API token'} value={form.data.managed_cloud_token} onChange={(e) => form.setData('managed_cloud_token', e.target.value)} autoComplete="new-password" />
                    </label>
                </div>
                <div className={`flex flex-wrap items-center justify-between gap-3 rounded-[10px] px-3.5 py-3 ${ready ? 'bg-emerald-50 dark:bg-emerald-400/10' : 'bg-[#eceae4]/60 dark:bg-white/[0.04]'}`}>
                    <p className="text-sm heading">
                        <span className={`badge ${ready ? 'badge-success' : 'badge-warning'}`}>
                            <span className={`badge-dot ${ready ? 'bg-emerald-500' : 'bg-amber-500'}`} />
                            {ready ? 'Catalog ready' : 'Not ready — token required'}
                        </span>
                    </p>
                    <button className="button-primary">Save provider</button>
                </div>
            </form>

            <form onSubmit={(e) => { e.preventDefault(); pricing.put(route('admin.settings.managed-servers.pricing')); }} className="panel space-y-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 className="section-title">Pricing</h2>
                        <p className="field-hint">Markup applies to every size without an override. Leave a price blank to use markup.</p>
                    </div>
                    <button className="button-primary">Save pricing</button>
                </div>
                <label className="field-label max-w-xs">Default markup %
                    <input className="field" type="number" min="0" max="1000" step="0.1" value={pricing.data.markup_percent} onChange={(e) => pricing.setData('markup_percent', e.target.value)} />
                    <span className="field-hint">Example: 25 means a $6 infra size bills at $7.50 unless you override it.</span>
                </label>

                {sizes.length === 0 ? (
                    <div className="dashed-cta">
                        <p className="font-medium heading">No catalog yet</p>
                        <p className="text-sm muted">Save an API token above, then return here to price each size.</p>
                    </div>
                ) : (
                    <div className="panel-flush">
                        <div className="table-head hidden grid-cols-[minmax(0,1.4fr)_7rem_8rem] gap-4 sm:grid">
                            <span>Size</span>
                            <span>Infra cost</span>
                            <span>Customer price</span>
                        </div>
                        {sizes.map((size) => {
                            const prices = (pricing.data.prices || {}) as Record<string, string | number>;
                            const override = prices[size.slug];
                            return (
                                <div key={size.slug} className="data-row grid items-center gap-3 sm:grid-cols-[minmax(0,1.4fr)_7rem_8rem] sm:gap-4">
                                    <div className="min-w-0">
                                        <p className="font-mono text-sm font-medium heading">{size.slug}</p>
                                        <p className="mt-0.5 text-xs muted">{size.spec || size.description || 'Provider size'}</p>
                                    </div>
                                    <p className="text-sm tabular-nums muted">{size.infra_label || '—'}</p>
                                    <label className="field-label !text-xs sm:text-right">
                                        <span className="sm:sr-only">Customer price / mo</span>
                                        <input
                                            className="field mt-1 text-right tabular-nums"
                                            inputMode="decimal"
                                            placeholder={size.suggested != null ? String(size.suggested) : '0.00'}
                                            value={override ?? ''}
                                            onChange={(e) => pricing.setData('prices', { ...prices, [size.slug]: e.target.value })}
                                        />
                                    </label>
                                </div>
                            );
                        })}
                    </div>
                )}
            </form>
        </AdminLayout>
    );
}
