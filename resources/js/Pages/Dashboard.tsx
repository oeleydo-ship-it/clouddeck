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
    const { auth, managedServersReady, features } = usePage<PageProps>().props;
    const first = (auth.user?.name || '').split(' ')[0];
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    const cards = [
        { label: 'Servers', value: stats.servers, meta: `${stats.active} ready`, href: route('servers.index'), danger: false },
        { label: 'Sites', value: stats.sites, meta: `${stats.deployments} deploys today`, href: route('sites.index'), danger: false },
        { label: 'Failed deploys', value: stats.failed, meta: stats.failed > 0 ? 'Needs attention' : 'All clear', href: route('sites.index'), danger: stats.failed > 0 },
        { label: 'Offline agents', value: stats.offline, meta: stats.offline > 0 ? 'Unreachable' : 'Reporting', href: route('servers.index'), danger: stats.offline > 0 },
    ];

    return (
        <ConsoleLayout crumb="Overview">
            <div className="app-main">
                <header className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="max-w-2xl">
                        <p className="page-eyebrow">Overview</p>
                        <h1 className="page-title">{greeting}, {first}</h1>
                        <p className="page-subtitle">Your fleet at a glance — provision servers, ship sites, and keep an eye on health from one place.</p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Link href={route('sites.create')} className="button-secondary h-12">Add site</Link>
                        <Link href={route('servers.create')} className="button-primary h-12">{provisionLabel || 'Provision server'}</Link>
                        <Link href={route('servers.custom')} className="button-secondary h-12">Add existing server</Link>
                        {managedServersReady && features.managed_servers && <Link href={route('servers.managed')} className="button-secondary h-12">Managed server</Link>}
                    </div>
                </header>
                <Flash />
                <section className="mt-10 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <Link key={card.label} href={card.href} className="stat-card group">
                            <p className="stat-label">{card.label}</p>
                            <div className="mt-4 flex items-baseline gap-2">
                                <span className={`stat-value ${card.danger ? '!text-rose-600 dark:!text-rose-400' : ''}`}>{card.value}</span>
                                <span className="text-xs font-medium muted">{card.meta}</span>
                            </div>
                        </Link>
                    ))}
                </section>
                <div className="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                    <section className="panel !p-0 overflow-hidden">
                        <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-white/5">
                            <div><h2 className="section-title">Your servers</h2><p className="mt-1 text-body-sm muted">Recently provisioned and connected hosts</p></div>
                            <Link href={route('servers.index')} className="text-sm font-semibold text-sky-600 hover:underline dark:text-sky-300">View all</Link>
                        </div>
                        <div className="divide-y divide-slate-100 dark:divide-white/5">
                            {recentServers.length === 0 && <p className="px-6 py-10 text-center muted">No servers yet. Provision one to get started.</p>}
                            {recentServers.map((server) => (
                                <Link key={server.id} href={route('servers.manage', server.id)} className="flex items-center gap-4 px-6 py-4 transition-colors duration-150 hover:bg-[#eceae4]/50 dark:hover:bg-white/[.03]">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium heading">{server.name}</p>
                                        <p className="mt-1 text-xs muted">{server.public_ip || 'No IP yet'} · {server.region}</p>
                                    </div>
                                    <StatusBadge status={enumValue(server.status)} />
                                </Link>
                            ))}
                        </div>
                    </section>
                    <div className="space-y-6">
                        <section className="panel">
                            <h2 className="section-title">Operational health</h2>
                            <div className="mt-4 grid grid-cols-3 gap-3 text-center">
                                <div><p className="text-xs muted">CPU</p><p className="mt-1 text-lg font-semibold heading">{health.cpu == null ? '—' : `${health.cpu}%`}</p></div>
                                <div><p className="text-xs muted">Memory</p><p className="mt-1 text-lg font-semibold heading">{health.memory == null ? '—' : `${health.memory}%`}</p></div>
                                <div><p className="text-xs muted">Uptime</p><p className="mt-1 text-lg font-semibold heading">{health.uptime == null ? '—' : `${health.uptime}%`}</p></div>
                            </div>
                            <p className="mt-3 text-xs muted">{health.samples} samples in the last 24 hours.</p>
                        </section>
                        <section className="panel">
                            <h2 className="section-title">{plan.heading || 'Current plan'}</h2>
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
                        <section className="panel">
                            <h2 className="section-title">Recent deployments</h2>
                            <div className="mt-3 space-y-3">
                                {recentDeployments.length === 0 && <p className="text-sm muted">No deployments yet.</p>}
                                {recentDeployments.map((deployment) => (
                                    <Link key={deployment.id} href={route('deployments.show', deployment.id)} className="flex items-center justify-between gap-3">
                                        <span className="truncate text-sm heading">{deployment.site?.domain}</span>
                                        <StatusBadge status={enumValue(deployment.status)} />
                                    </Link>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </ConsoleLayout>
    );
}
