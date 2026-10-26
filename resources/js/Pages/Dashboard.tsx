import { Link, usePage } from '@inertiajs/react';
import ConsoleLayout from '../Layouts/ConsoleLayout';
import { Flash } from '../Components/Flash';
import { StatusBadge } from '../Components/StatusBadge';
import { PageProps } from '../types';
import { route } from '../lib/route';
import { enumValue, money } from '../lib/ui';

type Server = { id: string; name: string; public_ip?: string | null; status: string; region?: string; cloud_account?: { name?: string } | null };
type Deployment = { id: string; status: string; created_at?: string; site?: { domain: string; id: string } | null };
type PlanPanel = {
    plan?: { name: string; monthly_price: number; currency: string } | null;
    subscription?: { current_period_ends_at?: string } | null;
    usage: Record<string, { used: number; limit: number; label?: string; at_limit?: boolean }>;
    upgrade?: { name: string } | null;
    heading?: string;
    upgrade_label?: string | null;
    no_upgrade?: string | null;
    limit_reached?: string;
};

export default function Dashboard({ stats, recentServers, recentDeployments, health, plan, provisionLabel }: {
    stats: { servers: number; active: number; sites: number; deployments: number; failed: number; offline: number };
    recentServers: Server[];
    recentDeployments: Deployment[];
    health: { cpu: number | null; memory: number | null; uptime: number | null; samples: number };
    plan: PlanPanel;
    provisionLabel?: string;
}) {
    const { managedServersReady, features } = usePage<PageProps>().props;
    const cards = [
        { label: 'Total servers', value: stats.servers, meta: `${stats.active} online`, href: route('servers.index'), danger: false, icon: 'M4 6h16v4H4zM4 14h16v4H4z' },
        { label: 'Hosted sites', value: stats.sites, meta: `${stats.deployments} deploys today`, href: route('sites.index'), danger: false, icon: 'M4 5h16v14H4zM4 9h16' },
        { label: 'Failed deploys', value: stats.failed, meta: stats.failed > 0 ? 'Needs attention' : 'All clear', href: route('sites.index'), danger: stats.failed > 0, icon: 'M12 9v4m0 4h.01M10.3 3.8 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z' },
        { label: 'Offline agents', value: stats.offline, meta: stats.offline > 0 ? 'Unreachable' : 'All reporting', href: route('servers.index'), danger: stats.offline > 0, icon: 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
    ];

    return (
        <ConsoleLayout crumb="Overview">
            <div className="app-main">
                <header className="dashboard-heading">
                    <div>
                        <h1 className="page-title !mt-0">Infrastructure Overview</h1>
                        <p className="page-subtitle">Real-time monitoring across all environments</p>
                    </div>
                    <div className="dashboard-actions">
                        <Link href={route('sites.create')} className="button-secondary">Add site</Link>
                        <Link href={route('servers.create')} className="button-primary">{provisionLabel || 'Provision server'}</Link>
                        <Link href={route('servers.custom')} className="button-secondary">Connect server</Link>
                        {managedServersReady && features.managed_servers && <Link href={route('servers.managed')} className="button-secondary">Managed server</Link>}
                    </div>
                </header>
                <Flash />
                <section className="dashboard-stats">
                    {cards.map((card) => (
                        <Link key={card.label} href={card.href} className="stat-card group">
                            <div className="flex items-start justify-between gap-3">
                                <div><p className="stat-label">{card.label}</p><p className={`stat-value mt-2 ${card.danger ? '!text-rose-600 dark:!text-rose-400' : ''}`}>{card.value}</p></div>
                                <span className={`dashboard-stat-icon ${card.danger ? 'dashboard-stat-icon-danger' : ''}`}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d={card.icon} /></svg></span>
                            </div>
                            <p className="mt-3 text-xs muted">{card.meta}</p>
                        </Link>
                    ))}
                </section>
                <div className="dashboard-grid">
                    <section className="panel dashboard-fleet">
                        <div className="dashboard-panel-head">
                            <div><h2 className="section-title">Server Fleet</h2><p className="mt-1 text-xs muted">Recently provisioned and connected hosts</p></div>
                            <Link href={route('servers.index')} className="text-sm font-semibold text-sky-600 hover:underline dark:text-sky-300">View all</Link>
                        </div>
                        <div className="server-fleet-grid">
                            {recentServers.length === 0 && <p className="col-span-full px-6 py-10 text-center muted">No servers yet. Provision one to get started.</p>}
                            {recentServers.map((server) => (
                                <Link key={server.id} href={route('servers.manage', server.id)} className="server-fleet-card">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex min-w-0 items-center gap-2.5"><span className="server-glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="4" y="4" width="16" height="6" rx="1"/><rect x="4" y="14" width="16" height="6" rx="1"/><path d="M8 7h.01M8 17h.01"/></svg></span><div className="min-w-0"><p className="truncate font-semibold heading">{server.name}</p><p className="mt-0.5 truncate text-xs muted">{server.public_ip || 'No IP assigned'}</p></div></div>
                                        <StatusBadge status={enumValue(server.status)} />
                                    </div>
                                    <div className="mt-4 flex items-center justify-between text-xs muted"><span>{server.region || 'Global'}</span><span>{server.cloud_account?.name || 'Connected'}</span></div>
                                </Link>
                            ))}
                        </div>
                    </section>
                    <section className="panel dashboard-health">
                        <div className="dashboard-panel-head"><div><h2 className="section-title">Key Metrics</h2><p className="mt-1 text-xs muted">Performance indicators · {health.samples} samples</p></div></div>
                        {[['CPU usage', health.cpu], ['Memory usage', health.memory], ['Uptime', health.uptime]].map(([label, value]) => (
                            <div key={label as string} className="metric-row"><div className="flex justify-between gap-3"><span>{label}</span><strong>{value == null ? '—' : `${value}%`}</strong></div><div className="metric-track"><span style={{ width: `${Math.max(0, Math.min(100, Number(value || 0)))}%` }} /></div></div>
                        ))}
                    </section>
                    <section className="panel dashboard-deployments">
                        <div className="dashboard-panel-head"><div><h2 className="section-title">Deployment Pipeline</h2><p className="mt-1 text-xs muted">Latest deployments across your sites</p></div></div>
                        <div className="deployment-list">
                            {recentDeployments.length === 0 && <p className="py-8 text-center text-sm muted">No deployments yet.</p>}
                            {recentDeployments.map((deployment) => (
                                <Link key={deployment.id} href={route('deployments.show', deployment.id)} className="deployment-row">
                                    <span className={`deployment-dot deployment-dot-${enumValue(deployment.status)}`} />
                                    <span className="min-w-0 flex-1"><span className="block truncate font-semibold heading">{deployment.site?.domain}</span><span className="mt-0.5 block text-xs muted">Deployment {deployment.id.slice(0, 8)}</span></span>
                                    <StatusBadge status={enumValue(deployment.status)} />
                                </Link>
                            ))}
                        </div>
                    </section>
                    <section className="panel dashboard-plan">
                        <div className="dashboard-panel-head"><div><h2 className="section-title">{plan.heading || 'Current Plan'}</h2><p className="mt-1 text-xs muted">Usage and account limits</p></div></div>
                            <p className="mt-2 text-sm heading">{plan.plan?.name ?? 'No plan'}{plan.plan ? ` · ${plan.plan.monthly_price === 0 ? 'Free' : money(plan.plan.monthly_price, plan.plan.currency)}/mo` : ''}</p>
                            <div className="mt-4 space-y-2 text-sm">
                                {Object.entries(plan.usage || {}).map(([key, row]) => (
                                    <p key={key} className="flex justify-between"><span className="capitalize muted">{key}</span><span className="heading">{row.label || `${row.used} / ${row.limit < 0 ? '∞' : row.limit}`}{row.at_limit || (row.limit >= 0 && row.used >= row.limit) ? ` · ${plan.limit_reached || 'Limit reached'}` : ''}</span></p>
                                ))}
                            </div>
                            {plan.upgrade
                                ? <Link href="/billing" className="button-secondary mt-4 inline-flex">{plan.upgrade_label || `Upgrade to ${plan.upgrade.name}`}</Link>
                                : <p className="mt-4 text-sm muted">{plan.no_upgrade || 'nothing to upgrade to'}</p>}
                            <Link href="/billing" className="button-secondary mt-3 inline-flex">Manage billing</Link>
                    </section>
                </div>
            </div>
        </ConsoleLayout>
    );
}
