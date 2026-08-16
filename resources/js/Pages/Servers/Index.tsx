import { Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { MenuPopover } from '../../Components/MenuPopover';
import { Pagination } from '../../Components/Pagination';
import { StatusBadge } from '../../Components/StatusBadge';
import { PageProps } from '../../types';
import { useLiveReload } from '../../lib/live';
import { route } from '../../lib/route';
import { enumValue, items } from '../../lib/ui';

function ServerRowMenu({ serverId, open, onToggle, onClose }: { serverId: string; open: boolean; onToggle: () => void; onClose: () => void }) {
    const buttonRef = useRef<HTMLButtonElement>(null);

    return (
        <>
            <button type="button" ref={buttonRef} className="icon-button" aria-label="More actions" aria-haspopup="menu" aria-expanded={open} onClick={onToggle}>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="size-4"><circle cx="5" cy="12" r="1.6" /><circle cx="12" cy="12" r="1.6" /><circle cx="19" cy="12" r="1.6" /></svg>
            </button>
            <MenuPopover open={open} anchor={buttonRef} onClose={onClose} widthClass="!w-40">
                <button type="button" role="menuitem" className="menu-item" onClick={() => { onClose(); router.reload({ only: ['servers', 'summary'] }); }}>Refresh</button>
                <Link href={`${route('servers.manage', serverId)}#danger-zone`} role="menuitem" className="menu-item !text-rose-600 hover:!bg-rose-50 dark:!text-rose-300 dark:hover:!bg-rose-400/10" onClick={onClose}>Delete</Link>
            </MenuPopover>
        </>
    );
}

export default function Index({ servers, summary, empty, provisionLabel, managedLabel, managedHref }: { servers: any; summary: { total: number; uptime: number | null; cpu: number | null; alerts: number }; empty?: string | null; provisionLabel?: string; managedLabel?: string; managedHref?: string }) {
    const { managedServersReady, features } = usePage<PageProps>().props;
    const rows = items<any>(servers);
    const active = rows.some((server) => ! ['ready', 'failed', 'active'].includes(enumValue(server.status)));
    const [menuId, setMenuId] = useState<string | null>(null);
    const [copiedId, setCopiedId] = useState<string | null>(null);

    useLiveReload({
        active,
        channels: rows.map((server) => `servers.${server.id}`),
        events: ['.provisioning-updated'],
        only: ['servers', 'summary'],
        interval: 5000,
    });

    const copyIp = (id: string, ip: string) => {
        navigator.clipboard.writeText(ip).then(() => {
            setCopiedId(id);
            window.setTimeout(() => setCopiedId((current) => current === id ? null : current), 1600);
        }).catch(() => {});
    };

    return (
        <ConsoleLayout crumb="Servers">
            <div className="app-main">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="min-w-0">
                        <p className="page-eyebrow">Infrastructure</p>
                        <h1 className="page-title">Servers</h1>
                        <p className="page-subtitle">Provision, attach, and operate every host in your fleet.</p>
                    </div>
                    <div className="flex flex-wrap gap-2 sm:gap-3">
                        <Link href={route('servers.custom')} className="button-secondary">Add existing server</Link>
                        <Link href={route('servers.create')} className="button-primary">{provisionLabel || 'Provision server'}</Link>
                        {managedServersReady && features.managed_servers && <Link href={managedHref || route('servers.managed')} className="button-secondary">{managedLabel || 'Managed server'}</Link>}
                    </div>
                </header>
                <Flash />
                <div className="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {[['Total', summary.total], ['Uptime', summary.uptime == null ? '—' : `${summary.uptime}%`], ['CPU', summary.cpu == null ? '—' : `${summary.cpu}%`], ['Alerts', summary.alerts]].map(([label, value]) => (
                        <div key={String(label)} className="stat-card h-full"><p className="stat-label">{label}</p><p className="stat-value mt-2">{value}</p></div>
                    ))}
                </div>
                <div className="panel-flush mt-4">
                    {rows.length === 0 && (
                        <div className="px-6 py-16 text-center">
                            <p className="font-medium heading">{empty || 'No servers yet'}</p>
                            <p className="mt-1 text-sm muted">Provision a new host, or import a Droplet you already run.</p>
                            <div className="mt-5 flex flex-wrap justify-center gap-3">
                                <Link href={route('servers.create')} className="button-primary">Provision server</Link>
                                <Link href={route('servers.custom')} className="button-secondary">Add existing server</Link>
                            </div>
                        </div>
                    )}
                    {rows.map((server: any) => {
                        const status = enumValue(server.status);
                        const provisioning = ! ['ready', 'failed', 'active'].includes(status);
                        const metric = server.latest_metric || server.latestMetric;
                        const phpVersions = [...new Set((server.sites || []).map((site: any) => site.php_version).filter(Boolean))];
                        const siteCount = (server.sites || []).length;
                        const resources = [
                            ['CPU', metric?.cpu_percent],
                            ['RAM', metric?.memory_percent],
                            ['Disk', metric?.disk_percent],
                        ];
                        const ip = server.public_ip || '';

                        return (
                            <div key={server.id} className="data-row">
                                <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link href={route('servers.manage', server.id)} className="truncate font-semibold heading hover:text-sky-700 dark:hover:text-sky-300">{server.name}</Link>
                                            <StatusBadge status={status} />
                                            {server.team && <span className="badge badge-neutral">{server.team.name}</span>}
                                        </div>
                                        <div className="mt-1 flex min-w-0 flex-wrap items-center gap-1.5">
                                            <span className="truncate font-mono text-xs muted">{ip || server.hostname}</span>
                                            {ip && (
                                                <button type="button" className="icon-button !size-6" aria-label="Copy IP address" title="Copy IP address" onClick={() => copyIp(server.id, ip)}>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-3"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
                                                </button>
                                            )}
                                            {copiedId === server.id && <span className="text-xs muted">Copied</span>}
                                        </div>
                                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                            {(server.region || server.size) && <span className="text-xs muted">{[server.region, server.size].filter(Boolean).join(' · ')}</span>}
                                            <span className="badge badge-neutral">{siteCount} site{siteCount === 1 ? '' : 's'}</span>
                                            {phpVersions.length === 1 && <span className="badge badge-neutral">PHP {String(phpVersions[0])}</span>}
                                        </div>
                                        {provisioning && (
                                            <div className="mt-3 max-w-sm">
                                                <div className="flex items-center justify-between gap-3 text-xs muted">
                                                    <span className="truncate">{server.current_step || 'Provisioning'}</span>
                                                    <span className="shrink-0 tabular-nums">{server.progress || 0}%</span>
                                                </div>
                                                <div className="meter mt-1.5"><span className="meter-fill" style={{ width: `${Math.min(100, Math.max(0, Number(server.progress) || 0))}%` }} /></div>
                                            </div>
                                        )}
                                        {status === 'failed' && server.failure_reason && <p className="mt-2 truncate text-xs text-rose-600 dark:text-rose-300" title={server.failure_reason}>{server.failure_reason}</p>}
                                    </div>
                                    <div className="grid w-full grid-cols-3 gap-3 sm:max-w-xs xl:w-64 xl:shrink-0">
                                        {resources.map(([label, value]) => (
                                            <div key={String(label)}>
                                                <div className="flex items-center justify-between gap-1 text-[11px]">
                                                    <span className="font-medium muted">{label}</span>
                                                    <span className={`tabular-nums ${value == null ? 'muted' : 'font-semibold heading'}`}>{value == null ? '—' : `${value}%`}</span>
                                                </div>
                                                <div className="meter mt-1"><span className={`meter-fill ${Number(value) >= 90 ? '!bg-rose-500' : ''}`} style={{ width: `${Math.min(100, Math.max(0, Number(value) || 0))}%` }} /></div>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex shrink-0 items-center justify-end gap-2">
                                        <Link href={route('servers.manage', server.id)} className="button-secondary">Manage</Link>
                                        <ServerRowMenu
                                            serverId={server.id}
                                            open={menuId === server.id}
                                            onToggle={() => setMenuId(menuId === server.id ? null : server.id)}
                                            onClose={() => setMenuId(null)}
                                        />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
                <Pagination links={servers.links} />
            </div>
        </ConsoleLayout>
    );
}
