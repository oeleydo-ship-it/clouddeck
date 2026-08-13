import { Link } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { when } from '../../lib/ui';

export default function Overview({ stats, auditLogs }: any) {
    const cards = [
        ['Users', stats.users, route('admin.users')],
        ['Suspended', stats.suspended, route('admin.users')],
        ['Subscriptions', stats.subscriptions, route('admin.plans')],
        ['Billing requests', stats.billing_requests, route('admin.billing')],
    ] as const;

    return (
        <AdminLayout
            title="Overview"
            description="SaaS control center"
            actions={<Link href={route('admin.users')} className="button-primary">Users</Link>}
        >
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {cards.map(([label, value, href]) => (
                    <Link key={label} href={href} className="stat-card block">
                        <p className="stat-label">{label}</p>
                        <p className="stat-value mt-2">{value}</p>
                    </Link>
                ))}
            </div>
            <section className="panel-flush">
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/5">
                    <h2 className="section-title">Recent audit</h2>
                    <Link href={route('admin.audit')} className="link-action">View all</Link>
                </div>
                {(auditLogs || []).length === 0 && <p className="px-5 py-10 text-center text-sm muted">No audit events yet.</p>}
                {(auditLogs || []).map((log: any) => (
                    <div key={log.id} className="data-row flex flex-wrap items-center justify-between gap-2">
                        <p className="font-medium heading">{log.action}</p>
                        <p className="text-sm muted">{log.actor?.email || 'System'}{log.created_at ? ` · ${when(log.created_at)}` : ''}</p>
                    </div>
                ))}
            </section>
        </AdminLayout>
    );
}
