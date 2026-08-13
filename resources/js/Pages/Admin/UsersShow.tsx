import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Pagination } from '../../Components/Pagination';
import { StatusBadge } from '../../Components/StatusBadge';
import { route } from '../../lib/route';
import { items, when } from '../../lib/ui';

export default function UsersShow({ user, plans, impersonationHistory, historyAdmins, activeImpersonation, activity, canImpersonate, canImpersonateAdmins, suspendValue, actionUrls }: any) {
    const impersonate = useForm({ support_mode: 'full' });
    const subscription = useForm({ plan_id: plans[0]?.id || '', period_days: 30 });
    const role = useForm({ role: user.role || 'customer' });
    const suspend = useForm({ suspend: suspendValue, reason: user.suspended_at ? 'Restore account' : '' });
    const suspended = Boolean(user.suspended_at);
    const busy = Boolean(activeImpersonation);
    const impersonateBlocked = suspended || busy || (user.role === 'super_admin' && ! canImpersonateAdmins);
    const planName = user.current_subscription?.plan?.name || user.currentSubscription?.plan?.name;

    return (
        <AdminLayout
            title={user.name}
            description="Account management, support access, and impersonation history."
            actions={<Link href={route('admin.users')} className="button-secondary">All users</Link>}
        >
            <div className="panel flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm muted">{user.email} · {planName || 'No active plans'}</p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {suspended && <StatusBadge status="Suspended" />}
                        <span className="badge badge-neutral capitalize">{user.role?.replace('_', ' ') || 'customer'}</span>
                    </div>
                </div>
                {activeImpersonation && <p className="text-sm text-amber-700 dark:text-amber-300">Active session by {activeImpersonation.admin?.name}</p>}
            </div>

            {canImpersonate && (
                <form onSubmit={(e) => { e.preventDefault(); impersonate.post(actionUrls?.impersonate || route('admin.users.impersonate', user.id)); }} className="panel space-y-4">
                    <div>
                        <h2 className="section-title">Impersonate user</h2>
                        <p className="field-hint">Opens the customer console as this account. Read only blocks mutations.</p>
                    </div>
                    <label className="field-label max-w-xs">Mode
                        <select className="field" name="support_mode" value={impersonate.data.support_mode} onChange={(e) => impersonate.setData('support_mode', e.target.value)}>
                            <option value="full">Full</option>
                            <option value="read_only">Read only</option>
                        </select>
                    </label>
                    <button className="button-primary" disabled={impersonateBlocked}>Start impersonation</button>
                </form>
            )}

            <div className="grid gap-4 lg:grid-cols-3">
                <form onSubmit={(e) => { e.preventDefault(); subscription.post(actionUrls?.subscription || route('admin.users.subscription', user.id)); }} className="panel space-y-3">
                    <h2 className="section-title">Subscription</h2>
                    <label className="field-label">Plan
                        <select className="field" name="plan_id" value={subscription.data.plan_id} onChange={(e) => subscription.setData('plan_id', e.target.value)}>
                            {plans.length === 0 && <option value="" disabled>No active plans</option>}
                            {plans.map((plan: any) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}
                        </select>
                    </label>
                    <label className="field-label">Period (days)
                        <input className="field" type="number" name="period_days" value={subscription.data.period_days} onChange={(e) => subscription.setData('period_days', Number(e.target.value))} />
                    </label>
                    <button className="button-secondary" disabled={plans.length === 0}>Assign</button>
                </form>
                <form onSubmit={(e) => { e.preventDefault(); role.patch(actionUrls?.role || route('admin.users.role', user.id)); }} className="panel space-y-3">
                    <h2 className="section-title">Role</h2>
                    <label className="field-label">Account role
                        <select className="field" name="role" value={role.data.role} onChange={(e) => role.setData('role', e.target.value)}>
                            <option value="customer">Customer</option>
                            <option value="super_admin">Super admin</option>
                        </select>
                    </label>
                    <button className="button-secondary">Update role</button>
                </form>
                <form onSubmit={(e) => { e.preventDefault(); suspend.patch(actionUrls?.suspend || route('admin.users.suspend', user.id)); }} className="panel space-y-3">
                    <h2 className="section-title">{suspended ? 'Restore access' : 'Suspend access'}</h2>
                    <input type="hidden" name="suspend" value={suspendValue} />
                    <label className="field-label">Reason
                        <input className="field" name="reason" placeholder="Reason" value={suspend.data.reason} onChange={(e) => suspend.setData('reason', e.target.value)} />
                    </label>
                    <button className={`button-secondary ${suspended ? '' : '!text-rose-600'}`}>{suspended ? 'Restore' : 'Suspend'}</button>
                </form>
            </div>

            <section className="panel-flush">
                <div className="border-b border-slate-100 px-5 py-4 dark:border-white/5">
                    <h2 className="section-title">Activity</h2>
                </div>
                {(activity || []).length === 0 && <p className="px-5 py-8 text-center text-sm muted">No recent activity.</p>}
                {(activity || []).map((row: any) => (
                    <div key={row.id} className="data-row flex flex-wrap justify-between gap-2 text-sm">
                        <span className="heading">{row.action}</span>
                        <span className="muted">{row.ip_address}{row.created_at ? ` · ${when(row.created_at)}` : ''}</span>
                    </div>
                ))}
            </section>

            <section className="panel-flush">
                <div className="border-b border-slate-100 px-5 py-4 dark:border-white/5">
                    <h2 className="section-title">Impersonation history</h2>
                    {(historyAdmins || []).length > 0 && <p className="mt-1 text-xs muted">{historyAdmins.length} administrators</p>}
                </div>
                {items(impersonationHistory).length === 0 && <p className="px-5 py-8 text-center text-sm muted">No impersonation sessions.</p>}
                {items(impersonationHistory).map((row: any) => (
                    <div key={row.id} className="data-row flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span className="heading">{row.admin?.name}</span>
                        <span className="muted">{row.status} · {row.support_mode === 'read_only' ? 'Read only' : (row.support_mode === 'full' ? 'Full' : row.support_mode)}{row.created_at ? ` · ${when(row.created_at)}` : ''}</span>
                    </div>
                ))}
            </section>
            <Pagination links={impersonationHistory?.links} />
        </AdminLayout>
    );
}
