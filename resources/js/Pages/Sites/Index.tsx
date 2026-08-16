import { Link } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { Pagination } from '../../Components/Pagination';
import { StatusBadge } from '../../Components/StatusBadge';
import { route } from '../../lib/route';
import { items } from '../../lib/ui';

type Site = {
    id: string;
    domain: string;
    status: string;
    platform?: string;
    php_version?: string | null;
    branch?: string | null;
    repository_url?: string | null;
    environment?: string;
    last_deployed_at?: string | null;
    server?: { name: string } | null;
    staging_site?: { id: string; domain?: string } | null;
    latest_deployment?: { status?: string; created_at?: string } | null;
};

function platformLabel(platform?: string): string {
    if (platform === 'wordpress') return 'WordPress';
    if (platform === 'react') return 'React';
    return 'Laravel';
}

function usesPhp(platform?: string): boolean {
    return platform !== 'react';
}

function deployedLabel(site: Site): string {
    const raw = site.last_deployed_at || site.latest_deployment?.created_at;
    if (! raw) return 'Never deployed';
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return 'Never deployed';
    return `Last deploy ${date.toLocaleString()}`;
}

export default function Index({ sites, stagingSitesEnabled, summary }: { sites: any; stagingSitesEnabled: boolean; summary: { total: number; active: number; deployments: number; failed: number } }) {
    const rows = items<Site>(sites);
    const empty = rows.length === 0;

    return (
        <ConsoleLayout crumb="Sites">
            <div className="app-main">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="min-w-0">
                        <p className="page-eyebrow">Applications</p>
                        <h1 className="page-title">Sites</h1>
                        <p className="page-subtitle">Every application deployed across your fleet, with its branch, runtime, and health.</p>
                    </div>
                    <div className="flex flex-wrap gap-2 sm:gap-3">
                        <Link href={route('sites.create')} className="button-primary">Add site</Link>
                    </div>
                </header>
                <Flash />
                <div className="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {([
                        ['Total sites', summary.total, false],
                        ['Active', summary.active, false],
                        ['Deploys today', summary.deployments, false],
                        ['Failed deploys', summary.failed, summary.failed > 0],
                    ] as const).map(([label, value, danger]) => (
                        <div key={label} className="stat-card h-full">
                            <p className="stat-label">{label}</p>
                            <p className={`stat-value mt-2 ${danger ? '!text-rose-600 dark:!text-rose-400' : ''}`}>{value}</p>
                        </div>
                    ))}
                </div>
                <div className="panel-flush mt-4">
                    {empty && (
                        <div className="px-6 py-16 text-center">
                            <p className="font-medium heading">Deploy your first application</p>
                            <p className="mt-1 text-sm muted">Connect a repository, or install WordPress on a ready server.</p>
                            <div className="mt-5 flex flex-wrap justify-center gap-3">
                                <Link href={route('sites.create')} className="button-primary">Add site</Link>
                            </div>
                        </div>
                    )}
                    {! empty && (
                        <div className="overflow-x-auto">
                            <table className="sites-index-table w-full text-left">
                                <thead>
                                    <tr>
                                        <th scope="col">Site</th>
                                        <th scope="col">Server / runtime</th>
                                        <th scope="col">Source / deploy</th>
                                        <th scope="col" className="sites-index-actions-head">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((site) => {
                                        const isStaging = site.environment === 'staging';
                                        const platform = platformLabel(site.platform);

                                        return (
                                            <tr key={site.id}>
                                                <td className="sites-index-site">
                                                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                        <Link href={route('sites.show', site.id)} className="truncate font-semibold heading hover:text-sky-700 dark:hover:text-sky-300">{site.domain}</Link>
                                                        <StatusBadge status={site.status} />
                                                        <span className={`badge ${isStaging ? 'badge-warning' : 'badge-success'}`}>{isStaging ? 'Staging' : 'Production'}</span>
                                                        <span className="badge badge-neutral">{platform}</span>
                                                    </div>
                                                </td>
                                                <td className="sites-index-runtime">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        {site.server?.name ? <span className="truncate text-xs muted">{site.server.name}</span> : <span className="text-xs muted">—</span>}
                                                        {site.branch && <span className="badge badge-neutral font-mono">{site.branch}</span>}
                                                        {usesPhp(site.platform) && site.php_version && <span className="badge badge-neutral">PHP {site.php_version}</span>}
                                                    </div>
                                                </td>
                                                <td className="sites-index-source">
                                                    {site.repository_url
                                                        ? <p className="truncate font-mono text-xs muted" title={site.repository_url}>{site.repository_url}</p>
                                                        : <p className="text-xs muted">—</p>}
                                                    <p className="mt-1 text-xs muted">{deployedLabel(site)}</p>
                                                </td>
                                                <td className="sites-index-actions">
                                                    <div className="flex flex-wrap items-center justify-end gap-2">
                                                        <Link href={route('sites.show', site.id)} className="button-secondary">Open</Link>
                                                        {stagingSitesEnabled && ! isStaging && site.status === 'active' && (
                                                            site.staging_site
                                                                ? <Link href={route('sites.show', site.staging_site.id)} className="button-secondary">Open staging</Link>
                                                                : <Link href={`${route('sites.show', site.id)}?tab=overview#staging-setup`} className="button-secondary">Create staging</Link>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
                <Pagination links={sites.links} />
            </div>
        </ConsoleLayout>
    );
}
