import { Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Pagination } from '../../Components/Pagination';
import { StatusBadge } from '../../Components/StatusBadge';
import { route } from '../../lib/route';
import { items, when } from '../../lib/ui';

export default function Users({ users, emptySearch }: any) {
    const search = useForm({ search: new URLSearchParams(window.location.search).get('search') || '' });
    const rows = items<any>(users);

    return (
        <AdminLayout title="Users" description="Search accounts, open a profile, and start impersonation from the account page.">
            <form onSubmit={(e) => { e.preventDefault(); router.get(route('admin.users'), { search: search.data.search }); }} className="flex flex-wrap gap-2">
                <input className="field mt-0 max-w-md flex-1" value={search.data.search} onChange={(e) => search.setData('search', e.target.value)} placeholder="Search name or email" />
                <button className="button-secondary">Search</button>
            </form>
            <div className="panel-flush">
                {rows.length === 0 && <p className="px-5 py-12 text-center text-sm muted">{emptySearch || 'No accounts match that search'}</p>}
                {rows.map((user) => {
                    const plan = user.current_subscription?.plan?.name || user.currentSubscription?.plan?.name;
                    const suspended = Boolean(user.suspended_at || user.status_label === 'Suspended');
                    return (
                        <div key={user.id} className="data-row flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link href={user.show_url || route('admin.users.show', user.id)} className="font-semibold heading hover:text-sky-700 dark:hover:text-sky-300">{user.name}</Link>
                                    {suspended && <StatusBadge status="Suspended" />}
                                    {user.role === 'super_admin' && <span className="badge badge-warning">Admin</span>}
                                </div>
                                <p className="mt-1 text-sm muted">{user.email} · {plan || 'No plan'}{user.created_at ? ` · ${when(user.created_at)}` : ''}</p>
                            </div>
                            <Link href={user.show_url || route('admin.users.show', user.id)} className="button-secondary shrink-0">Open</Link>
                        </div>
                    );
                })}
            </div>
            <Pagination links={users.links} />
        </AdminLayout>
    );
}
