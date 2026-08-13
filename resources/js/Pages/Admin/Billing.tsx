import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { when } from '../../lib/ui';

export default function Billing({ billingRequests }: { billingRequests: any[] }) {
    return (
        <AdminLayout title="Billing review" description="Manual plan requests waiting for approval.">
            <div className="space-y-4">
                {billingRequests.length === 0 && (
                    <div className="panel-flush">
                        <p className="px-5 py-12 text-center text-sm muted">No pending requests.</p>
                    </div>
                )}
                {billingRequests.map((row) => (
                    <BillingRow key={row.id} row={row} />
                ))}
            </div>
        </AdminLayout>
    );
}

function BillingRow({ row }: { row: any }) {
    const form = useForm({
        decision: 'approve',
        period_days: row.billing_cycle === 'yearly' ? 365 : 30,
        admin_note: '',
    });

    const submit = (decision: 'approve' | 'reject') => {
        form.transform((data) => ({ ...data, decision }));
        form.patch(route('admin.billing-requests.update', row.id));
    };

    return (
        <div className="panel space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium heading">{row.user?.email}</p>
                    <p className="mt-0.5 text-sm muted">
                        {row.plan?.name} · {row.billing_cycle}
                        {row.created_at ? ` · ${when(row.created_at)}` : ''}
                    </p>
                </div>
                <span className="badge badge-warning capitalize">{row.status || 'pending'}</span>
            </div>
            <div className="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
                <label className="field-label">Period (days)
                    <input className="field" type="number" min="1" max="3660" name="period_days" value={form.data.period_days} onChange={(e) => form.setData('period_days', Number(e.target.value))} />
                </label>
                <label className="field-label">Admin note
                    <input className="field" name="admin_note" value={form.data.admin_note} onChange={(e) => form.setData('admin_note', e.target.value)} placeholder="Optional note" />
                </label>
            </div>
            <div className="flex flex-wrap gap-2">
                <button type="button" className="button-primary" disabled={form.processing} onClick={() => submit('approve')}>Approve</button>
                <button type="button" className="button-secondary" disabled={form.processing} onClick={() => submit('reject')}>Reject</button>
            </div>
        </div>
    );
}
