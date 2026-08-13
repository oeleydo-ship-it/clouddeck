import { FormEvent, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';

const LIMITS: [string, string][] = [
    ['servers', 'BYOS servers'],
    ['managed_servers', 'Managed servers'],
    ['sites', 'Sites'],
    ['managed_sites', 'Managed sites'],
    ['databases', 'Databases'],
    ['api_tokens', 'API tokens'],
    ['teams', 'Teams'],
    ['team_members', 'Team members'],
    ['os_backup_gb', 'OS backup GB'],
];

const emptyPlan = {
    name: '',
    slug: '',
    monthly_price: '0.00' as string | number,
    yearly_price: '' as string | number,
    currency: 'USD',
    sort_order: 10,
    active: true,
    public: true,
    servers: 1,
    managed_servers: 0,
    sites: 5,
    managed_sites: 0,
    databases: 5,
    api_tokens: 5,
    teams: 1,
    team_members: 3,
    os_backup_gb: 0,
    features: {} as Record<string, boolean>,
};

function fromPlan(plan: any) {
    const limits = plan.limits || {};
    return {
        name: plan.name || '',
        slug: plan.slug || '',
        monthly_price: ((plan.monthly_price || 0) / 100).toFixed(2),
        yearly_price: plan.yearly_price ? ((plan.yearly_price || 0) / 100).toFixed(2) : '',
        currency: plan.currency || 'USD',
        sort_order: plan.sort_order ?? 10,
        active: Boolean(plan.active),
        public: Boolean(plan.public),
        servers: limits.servers ?? 0,
        managed_servers: limits.managed_servers ?? 0,
        sites: limits.sites ?? 0,
        managed_sites: limits.managed_sites ?? 0,
        databases: limits.databases ?? 0,
        api_tokens: limits.api_tokens ?? 0,
        teams: limits.teams ?? 0,
        team_members: limits.team_members ?? 0,
        os_backup_gb: limits.os_backup_gb ?? 0,
        features: { ...(plan.features || {}) } as Record<string, boolean>,
    };
}

export default function Plans({ plans, featureCatalog }: { plans: any[]; featureCatalog?: Record<string, string> }) {
    const catalog = featureCatalog || {};
    const [editingId, setEditingId] = useState<string | number | null>(null);
    const form = useForm(emptyPlan);

    const load = (plan: any) => {
        setEditingId(plan.id);
        form.setData(fromPlan(plan));
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    };

    const cancel = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
        form.setData(emptyPlan);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => {
            const payload: Record<string, unknown> = { ...data };
            Object.keys(catalog).forEach((key) => {
                payload[`feature_${key}`] = data.features[key] ? '1' : '0';
            });
            delete payload.features;
            payload.monthly_price = Math.round(Number(data.monthly_price || 0) * 100);
            payload.yearly_price = data.yearly_price === '' || data.yearly_price == null
                ? 0
                : Math.round(Number(data.yearly_price) * 100);
            return payload;
        });
        const options = { onSuccess: () => cancel() };
        if (editingId) {
            form.patch(route('admin.plans.update', editingId), options);
        } else {
            form.post(route('admin.plans.store'), options);
        }
    };

    return (
        <AdminLayout title="Plans" description="Customer plans, quotas, and module access. Prices are entered as customers see them (29 not 2900).">
            <div className="panel-flush">
                {plans.length === 0 && <p className="px-5 py-10 text-center text-sm muted">No plans yet.</p>}
                {plans.map((plan) => (
                    <div key={plan.id} className="data-row flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-semibold heading">{plan.name}</p>
                                <span className="badge badge-neutral">{plan.slug}</span>
                                {! plan.active && <span className="badge badge-warning">Inactive</span>}
                                {! plan.public && <span className="badge badge-neutral">Private</span>}
                            </div>
                            <p className="mt-1 text-sm muted">
                                {plan.monthly_price_label || `${plan.currency} ${((plan.monthly_price || 0) / 100).toFixed(2)}`}
                                {plan.yearly_price_label ? ` · ${plan.yearly_price_label} / yr` : ''}
                                {' · '}{plan.unlimited || (plan.limits?.sites === -1 ? 'Unlimited' : `${plan.limits?.sites ?? '—'} sites`)}
                                {' · '}{plan.subscriptions_label || `${plan.subscriptions_count || 0} subscriptions`}
                            </p>
                            {(plan.feature_labels || []).length > 0 && (
                                <p className="mt-1 text-xs muted">{(plan.feature_labels || []).join(' · ')}</p>
                            )}
                        </div>
                        <div className="flex shrink-0 flex-wrap gap-2">
                            <button type="button" className="button-secondary" onClick={() => load(plan)}>Edit</button>
                            <button type="button" className="button-secondary !text-rose-600" onClick={() => router.delete(route('admin.plans.destroy', plan.id))}>Delete</button>
                        </div>
                    </div>
                ))}
            </div>

            <form onSubmit={submit} className="panel space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="section-title">{editingId ? 'Edit plan' : 'Create plan'}</h2>
                    {editingId && <button type="button" className="button-ghost" onClick={cancel}>Cancel</button>}
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="field-label">Name<input className="field" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /></label>
                    <label className="field-label">Slug<input className="field font-mono text-xs" name="slug" value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} placeholder="auto from name" /></label>
                    <label className="field-label">Monthly price (USD)
                        <input className="field" type="number" min="0" step="0.01" name="monthly_price" value={form.data.monthly_price} onChange={(e) => form.setData('monthly_price', e.target.value)} />
                    </label>
                    <label className="field-label">Yearly price (optional)
                        <input className="field" type="number" min="0" step="0.01" name="yearly_price" value={form.data.yearly_price} onChange={(e) => form.setData('yearly_price', e.target.value)} placeholder="Leave blank for none" />
                    </label>
                    <label className="field-label">Currency<input className="field" name="currency" maxLength={3} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} /></label>
                    <label className="field-label">Sort order<input className="field" type="number" name="sort_order" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} /></label>
                </div>
                <div className="flex flex-wrap gap-3">
                    <label className="check-row"><input type="checkbox" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} />Active</label>
                    <label className="check-row"><input type="checkbox" checked={form.data.public} onChange={(e) => form.setData('public', e.target.checked)} />Public</label>
                </div>
                <div>
                    <h3 className="text-sm font-semibold heading">Quotas</h3>
                    <p className="field-hint">Use -1 for unlimited.</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        {LIMITS.map(([key, label]) => (
                            <label key={key} className="field-label">{label}
                                <input className="field" type="number" name={key} value={(form.data as any)[key]} onChange={(e) => form.setData(key as any, Number(e.target.value))} />
                            </label>
                        ))}
                    </div>
                </div>
                {Object.keys(catalog).length > 0 && (
                    <div>
                        <h3 className="text-sm font-semibold heading">Modules</h3>
                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                            {Object.entries(catalog).map(([key, label]) => (
                                <label key={key} className="check-row">
                                    <input type="checkbox" checked={!! form.data.features[key]} onChange={(e) => form.setData('features', { ...form.data.features, [key]: e.target.checked })} />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </div>
                )}
                <button className="button-primary">{editingId ? 'Save plan' : 'Create'}</button>
            </form>
        </AdminLayout>
    );
}
