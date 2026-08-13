import { router, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';

export default function Features({ flags, empty }: { flags: any[]; empty?: string | null }) {
    const form = useForm({ key: '', name: '', rollout_percentage: 100, enabled: true });
    return (
        <AdminLayout title="Feature flags" description="Global kill switches and percentage rollouts. Plan entitlements still apply underneath.">
            <div className="panel-flush">
                <div className="table-head hidden grid-cols-[minmax(0,1.4fr)_7rem_auto] gap-4 sm:grid">
                    <span>Flag</span>
                    <span>Rollout</span>
                    <span />
                </div>
                {flags.length === 0 && <p className="px-5 py-12 text-center text-sm muted">{empty || 'No feature flags'}</p>}
                {flags.map((flag) => (
                    <FlagRow key={flag.id} flag={flag} />
                ))}
            </div>
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('admin.flags.store')); }} className="panel grid gap-4 sm:grid-cols-3">
                <h2 className="section-title sm:col-span-3">Add flag</h2>
                <label className="field-label">Key<input className="field font-mono text-xs" placeholder="monitoring" value={form.data.key} onChange={(e) => form.setData('key', e.target.value)} /></label>
                <label className="field-label">Name<input className="field" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                <label className="field-label">Rollout %<input className="field" type="number" min="0" max="100" value={form.data.rollout_percentage} onChange={(e) => form.setData('rollout_percentage', Number(e.target.value))} /></label>
                <label className="check-row sm:col-span-3"><input type="checkbox" checked={form.data.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} />Enabled</label>
                <div className="sm:col-span-3"><button className="button-primary">Add flag</button></div>
            </form>
        </AdminLayout>
    );
}

function FlagRow({ flag }: { flag: any }) {
    const save = (enabled: boolean, rollout = flag.rollout_percentage) => {
        router.patch(route('admin.flags.update', flag.id), {
            name: flag.name,
            rollout_percentage: Number(rollout),
            enabled,
        });
    };

    return (
        <div className="data-row grid items-center gap-3 sm:grid-cols-[minmax(0,1.4fr)_7rem_auto] sm:gap-4">
            <div className="min-w-0">
                <p className="font-mono text-sm font-medium heading">{flag.key}</p>
                <p className="mt-0.5 text-sm muted">{flag.name} · {flag.rollout_label || (flag.enabled ? `${flag.rollout_percentage}% of customers` : 'Off for everyone')}</p>
            </div>
            <label className="field-label !text-xs">
                <span className="sm:sr-only">Rollout %</span>
                <input
                    className="field mt-1 tabular-nums"
                    type="number"
                    min="0"
                    max="100"
                    defaultValue={flag.rollout_percentage}
                    onBlur={(e) => {
                        const next = Number(e.target.value);
                        if (next !== Number(flag.rollout_percentage)) {
                            save(Boolean(flag.enabled), next);
                        }
                    }}
                />
            </label>
            <button type="button" className="button-secondary shrink-0 justify-self-start sm:justify-self-end" onClick={() => save(! flag.enabled)}>
                {flag.enabled ? 'Disable' : 'Enable'}
            </button>
        </div>
    );
}
