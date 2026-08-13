import AdminLayout from '../../Layouts/AdminLayout';
import { Pagination } from '../../Components/Pagination';
import { items, when } from '../../lib/ui';

export default function Audit({ auditLogs }: any) {
    const rows = items<any>(auditLogs);
    return (
        <AdminLayout title="Audit" description="Operator actions across settings, users, plans, and impersonation.">
            <div className="panel-flush">
                <div className="table-head hidden grid-cols-[1.2fr_1fr_7rem_8rem] gap-4 sm:grid">
                    <span>Action</span>
                    <span>Actor</span>
                    <span>IP</span>
                    <span>When</span>
                </div>
                {rows.length === 0 && <p className="px-5 py-12 text-center text-sm muted">No audit events yet.</p>}
                {rows.map((log: any) => (
                    <div key={log.id} className="data-row grid gap-1 sm:grid-cols-[1.2fr_1fr_7rem_8rem] sm:items-center sm:gap-4">
                        <p className="font-medium heading">{log.action}</p>
                        <p className="text-sm muted">{log.actor?.email || 'System'}</p>
                        <p className="font-mono text-xs muted">{log.ip_address || '—'}</p>
                        <p className="text-xs muted">{when(log.created_at) || '—'}</p>
                    </div>
                ))}
            </div>
            <Pagination links={auditLogs.links} />
        </AdminLayout>
    );
}
