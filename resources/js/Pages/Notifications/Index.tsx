import { Link, router, useForm, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { StatusBadge } from '../../Components/StatusBadge';
import { TabBar, TabPanel, Tabs } from '../../Components/Tabs';
import { PageProps } from '../../types';
import { route } from '../../lib/route';
import { items } from '../../lib/ui';

type Incident = {
    id: string;
    source: string;
    message?: string;
    title?: string;
    status: string;
    severity: string;
    detail?: string;
    started_at_human?: string | null;
    resolved_at_human?: string | null;
    server?: { id: string; name: string; public_ip?: string | null } | null;
    site?: { id: string; domain: string } | null;
    href?: string | null;
    security?: {
        id: string;
        summary?: string | null;
        evidence?: unknown;
        source_ip?: string | null;
        firewall_rule_id?: string | null;
    } | null;
};

export default function Index({
    incidents,
    servers,
    filters,
    notificationChannels,
    notificationEvents,
    notificationTab,
    tabs: tabLabels,
    emptyIncidents,
    emptyRecipients,
}: any) {
    const tabs = tabLabels || { incidents: 'Incidents', email: 'Email recipients' };
    const tabState = Tabs({ tabs, initial: notificationTab || 'incidents' });
    const { auth, csrf_token } = usePage<PageProps>().props;
    const rows = items<Incident>(incidents);
    const recipients = items<any>(notificationChannels);
    const serverList = items<any>(servers);
    const events = Object.entries(notificationEvents || {}) as [string, string][];
    const channel = useForm({ name: '', address: '', events: [] as string[], _tab: 'email' });

    const applyFilters = (patch: Record<string, string>) => {
        const next = {
            tab: 'incidents',
            status: filters?.status || 'open',
            type: filters?.type || 'all',
            severity: filters?.severity || 'all',
            server: filters?.server || '',
            ...patch,
        };
        router.get(route('notifications.index'), next, { preserveState: true, replace: true });
    };

    return (
        <ConsoleLayout crumb="Notifications">
            <div className="app-main">
                <header>
                    <p className="page-eyebrow">Monitoring</p>
                    <h1 className="page-title">Notifications</h1>
                    <p className="page-subtitle">Incidents across your servers and sites, plus email recipients for operational events. With no recipients configured, everything goes to your account address.</p>
                </header>
                <Flash />
                <TabBar tabs={tabs} tab={tabState.tab} setTab={tabState.setTab} />
                <TabPanel when="incidents" tab={tabState.tab}>
                    <form
                        className="panel flex flex-wrap items-end gap-4"
                        onSubmit={(e) => e.preventDefault()}
                    >
                        <input type="hidden" name="tab" value="incidents" />
                        <label className="min-w-[10rem] text-sm heading">Status
                            <select className="field" value={filters?.status || 'open'} onChange={(e) => applyFilters({ status: e.target.value })}>
                                <option value="open">Open</option>
                                <option value="acknowledged">Acknowledged</option>
                                <option value="resolved">Resolved</option>
                                <option value="all">All</option>
                            </select>
                        </label>
                        <label className="min-w-[10rem] text-sm heading">Type
                            <select className="field" value={filters?.type || 'all'} onChange={(e) => applyFilters({ type: e.target.value })}>
                                <option value="all">All</option>
                                <option value="security">Security</option>
                                <option value="server">Server metric</option>
                                <option value="site">Site monitor</option>
                            </select>
                        </label>
                        <label className="min-w-[10rem] text-sm heading">Severity
                            <select className="field" value={filters?.severity || 'all'} onChange={(e) => applyFilters({ severity: e.target.value })}>
                                <option value="all">All</option>
                                <option value="critical">Critical</option>
                                <option value="warning">Warning</option>
                                <option value="info">Info</option>
                            </select>
                        </label>
                        <label className="min-w-[16rem] grow text-sm heading">Server
                            <select className="field" value={filters?.server || ''} onChange={(e) => applyFilters({ server: e.target.value })}>
                                <option value="">All servers</option>
                                {serverList.map((server: any) => (
                                    <option key={server.id} value={server.id}>{server.name}{server.public_ip ? ` — ${server.public_ip}` : ''}</option>
                                ))}
                            </select>
                        </label>
                    </form>
                    <div className="mt-6 space-y-3">
                        {rows.length === 0 && (
                            <div className="dashed-cta">
                                <p className="text-card font-semibold heading">{emptyIncidents || 'No open incidents'}</p>
                                <p className="text-sm muted">When a server alert rule or site check fails, it will show up here.</p>
                            </div>
                        )}
                        {rows.map((incident) => {
                            const open = incident.status !== 'resolved';
                            const body = (
                                <>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="truncate text-card font-semibold heading">{incident.message || incident.title}</h2>
                                        <StatusBadge status={incident.status} />
                                        <StatusBadge status={incident.severity} />
                                        <span className="badge badge-neutral capitalize">{incident.source}</span>
                                    </div>
                                    <p className="mt-2 text-sm muted">
                                        {incident.detail}
                                        {incident.started_at_human ? ` · started ${incident.started_at_human}` : ''}
                                        {incident.resolved_at_human ? ` · resolved ${incident.resolved_at_human}` : ''}
                                    </p>
                                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs muted">
                                        {incident.server?.name && <span>{incident.server.name}</span>}
                                        {incident.site?.domain && <span>{incident.site.domain}</span>}
                                    </div>
                                </>
                            );

                            return (
                                <article key={`${incident.source}-${incident.id}`} className="panel flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0 grow">
                                        {incident.href ? <Link href={incident.href} className="block">{body}</Link> : <div>{body}</div>}
                                        {incident.security && (
                                            <div className="mt-3 space-y-3">
                                                <details>
                                                    <summary className="cursor-pointer text-xs font-medium text-sky-700 dark:text-sky-300">Sanitized evidence</summary>
                                                    {incident.security.summary && <p className="mt-2 text-sm muted">{incident.security.summary}</p>}
                                                    {incident.security.evidence != null && (
                                                        <pre className="log-pane mt-2 max-h-48 overflow-auto text-xs">{JSON.stringify(incident.security.evidence, null, 2)}</pre>
                                                    )}
                                                </details>
                                                <div className="flex flex-wrap gap-2">
                                                    {(['acknowledged', 'resolved', 'open'] as const).filter((status) => incident.status !== status).map((status) => (
                                                        <button
                                                            key={status}
                                                            type="button"
                                                            className="button-secondary !px-3 !py-1.5 text-xs"
                                                            onClick={() => router.patch(route('security.incidents.status', incident.security!.id), { status }, { preserveScroll: true })}
                                                        >
                                                            {status === 'acknowledged' ? 'Acknowledge' : status === 'resolved' ? 'Resolve' : 'Reopen'}
                                                        </button>
                                                    ))}
                                                    {incident.security.source_ip && ! incident.security.firewall_rule_id && (
                                                        <button
                                                            type="button"
                                                            className="button-secondary !px-3 !py-1.5 text-xs text-rose-600"
                                                            onClick={() => {
                                                                if (confirm('Block this public IP on the affected server?')) {
                                                                    router.post(route('security.incidents.block', incident.security!.id), { confirm: '1' }, { preserveScroll: true });
                                                                }
                                                            }}
                                                        >
                                                            Block IP
                                                        </button>
                                                    )}
                                                    {incident.security.firewall_rule_id && (
                                                        <button
                                                            type="button"
                                                            className="button-secondary !px-3 !py-1.5 text-xs"
                                                            onClick={() => {
                                                                if (confirm('Remove the incident-managed firewall block?')) {
                                                                    router.delete(route('security.incidents.unblock', incident.security!.id), { data: { confirm: '1' }, preserveScroll: true });
                                                                }
                                                            }}
                                                        >
                                                            Unblock IP
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    {incident.href && (
                                        <Link href={incident.href} className="button-secondary shrink-0 !px-3 !py-1.5 text-xs whitespace-nowrap">
                                            {incident.source === 'site' ? 'Open site' : 'Open server'}
                                        </Link>
                                    )}
                                    <span className="sr-only">{open ? 'open' : 'resolved'}</span>
                                </article>
                            );
                        })}
                    </div>
                </TabPanel>
                <TabPanel when="email" tab={tabState.tab}>
                    <div className="grid gap-6 lg:grid-cols-[380px_1fr]">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                channel.post(route('notification-channels.store'), { preserveScroll: true, onSuccess: () => channel.reset() });
                            }}
                            className="panel h-fit"
                        >
                            <input type="hidden" name="_token" value={csrf_token} />
                            <h2 className="section-title">Add recipient</h2>
                            <p className="mt-2 text-xs muted">Recipients are account-wide — not tied to a single server. Leave every event box clear to receive all of them.</p>
                            <label className="mt-5 block text-sm heading">Name
                                <input className="field" value={channel.data.name} onChange={(e) => channel.setData('name', e.target.value)} placeholder="Operations team" required />
                            </label>
                            <label className="mt-4 block text-sm heading">Email
                                <input className="field" type="email" value={channel.data.address} onChange={(e) => channel.setData('address', e.target.value)} placeholder={auth.user?.email || 'Leave blank to use your account address'} />
                            </label>
                            <fieldset className="mt-4">
                                <legend className="text-sm font-medium heading">Notify about</legend>
                                <p className="mt-1 text-xs muted">Leave every box clear to receive all of them.</p>
                                <div className="mt-2 grid gap-2">
                                    {events.map(([key, label]) => (
                                        <label key={key} className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={(channel.data.events || []).includes(key)}
                                                onChange={(e) => channel.setData('events', e.target.checked
                                                    ? [...(channel.data.events || []), key]
                                                    : (channel.data.events || []).filter((event: string) => event !== key))}
                                            />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            <button className="button-primary mt-5">Add recipient</button>
                        </form>
                        <section className="panel">
                            <h2 className="font-semibold heading">Email recipients</h2>
                            <div className="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                                {recipients.length === 0 && (
                                    <div className="py-8 text-center">
                                        <p className="text-sm font-medium heading">{emptyRecipients || 'No recipients yet'}</p>
                                        <p className="mt-1 text-sm muted">Alerts currently go to {auth.user?.email}. Add a recipient to send elsewhere or narrow which events you hear about.</p>
                                    </div>
                                )}
                                {recipients.map((row: any) => (
                                    <div key={row.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                        <div className="min-w-0">
                                            <p className="truncate heading">{row.name} <span className="muted">/ {row.address || auth.user?.email}</span></p>
                                            <p className="mt-0.5 text-xs muted">{(row.event_labels || []).length ? row.event_labels.join(', ') : (row.events || []).length ? (row.events as string[]).map((event) => notificationEvents?.[event] || event).join(', ') : 'All events'}</p>
                                        </div>
                                        <button className="link-danger" onClick={() => router.delete(route('notification-channels.destroy', row.id), { preserveScroll: true })}>Remove</button>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                </TabPanel>
            </div>
        </ConsoleLayout>
    );
}
