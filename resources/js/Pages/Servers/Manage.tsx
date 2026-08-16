import { Link, router, useForm, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { MetricChart } from '../../Components/MetricChart';
import { StatusBadge } from '../../Components/StatusBadge';
import { TabBar, TabPanel, Tabs } from '../../Components/Tabs';
import { PageProps } from '../../types';
import { useLiveReload } from '../../lib/live';
import { route } from '../../lib/route';
import { enumValue } from '../../lib/ui';

const tabs = { monitoring: 'Monitoring', databases: 'Databases', backups: 'Backups', cron: 'Cron', workers: 'Workers', services: 'Services' };
const phpExtensions = ['intl', 'gd', 'soap', 'xsl', 'ldap', 'imap', 'sqlite3', 'gmp', 'imagick', 'bz2', 'dba'];

export default function Manage({ server, backupDiskOptions, phpMyAdminPort, transferTeams, cronPresets, notificationLinks, copyIpLabel, operations, backupCopy = {} }: any) {
    const { branding, flash, features } = usePage<PageProps>().props;
    const tabState = Tabs({ tabs, initial: 'monitoring' });
    const status = enumValue(server.status);
    const ready = status === 'ready';
    const metrics = [...(server.metrics || [])];
    const phpmyadminUrl = server.phpmyadmin_enabled && server.public_ip
        ? `http://${server.public_ip}:${server.phpmyadmin_port}`
        : (server.phpmyadmin_url || server.phpMyAdminUrl);
    const latestMetric = metrics[0];

    useLiveReload({
        active: !ready,
        channels: [`servers.${server.id}`],
        events: ['.provisioning-updated'],
        only: ['server'],
        interval: 4000,
    });

    return (
        <ConsoleLayout crumb={server.name}>
            <div className="app-main">
                <div>
                    <Link href="/dashboard" className="link-action">← Dashboard</Link>
                    <h1 className="page-title">{server.name}</h1>
                    <p className="mt-2 text-sm text-slate-500">
                        {server.public_ip && <button type="button" className="font-mono hover:text-sky-700 dark:hover:text-sky-300" aria-label={copyIpLabel || 'Copy IP address'} title={copyIpLabel || 'Copy IP address'} onClick={() => navigator.clipboard.writeText(server.public_ip)}>{server.public_ip}</button>}
                        {server.public_ip && ' · '}{server.region} · <span className="capitalize">{status.replace('_', ' ')}</span>
                    </p>
                    <StatusBadge status={status} />
                </div>
                <Flash />
                {flash.database_password && <div className="flash-warning mt-5"><p>Copy the database password now. It will not be shown again.</p><code className="code-block">{flash.database_password}</code></div>}
                {flash.monitoring_secret && (
                    <div className="flash-warning mt-5">
                        <p>Copy this configuration now. The secret will not be shown again.</p>
                        <pre className="log-pane mt-3 max-h-40 whitespace-pre-wrap">{`CLOUDDECK_URL=${window.location.origin}\nCLOUDDECK_SERVER_ID=${server.id}\nCLOUDDECK_MONITORING_SECRET=${flash.monitoring_secret}`}</pre>
                    </div>
                )}
                {status === 'awaiting_payment' && (
                    <div className="flash-warning mt-5">
                        <p>This managed server is waiting for payment of <strong>${Number(server.metadata?.customer_price_monthly || 0).toFixed(2)}/mo</strong>. Provisioning starts after Stripe confirms the charge.</p>
                        <form onSubmit={(e) => { e.preventDefault(); router.post(route('servers.managed.checkout', server.id)); }} className="mt-3">
                            <button className="button-primary">{server.metadata?.stripe_checkout_session_id ? 'Check payment / complete checkout' : 'Complete payment'}</button>
                        </form>
                    </div>
                )}
                {status === 'failed' && server.provider_id && server.public_ip && (
                    <div className="flash-danger mt-5">
                        <p>Bootstrap failed: {server.failure_reason}</p>
                        <form onSubmit={(e) => { e.preventDefault(); router.post(route('servers.retry-provisioning', server.id)); }} className="mt-3"><button className="button-primary">Retry server bootstrap</button></form>
                    </div>
                )}
                {!ready ? (
                    <IncompleteServerPanel server={server} status={status} branding={branding} />
                ) : (
                <>
                <details className="panel mt-5"><summary className="cursor-pointer font-medium">Workspace ownership</summary>
                    <p className="mt-3 text-sm muted">{server.team ? `Shared with ${server.team.name}` : 'Personal workspace'}. Transferring changes who can view and operate this server.</p>
                    <TransferForm server={server} teams={transferTeams} />
                </details>
                <details id="danger-zone" className="panel panel-danger mt-5"><summary className="cursor-pointer font-medium text-rose-600 dark:text-rose-300">Danger zone</summary>
                    <p className="mt-3 text-sm muted">Permanently removes this server from {branding.name}{server.provider_id ? ' and destroys its Droplet at the provider' : ''}. Attached sites must be deleted first.</p>
                    <form onSubmit={(e) => { e.preventDefault(); const confirmation = (e.currentTarget.elements.namedItem('confirmation') as HTMLInputElement).value; if (confirm(`Permanently delete ${server.hostname}?`)) router.delete(route('servers.destroy', server.id), { data: { confirmation } }); }} className="mt-4 flex flex-wrap gap-3">
                        <input className="field mt-0" name="confirmation" placeholder={`Type ${server.hostname} to confirm`} /><button className="button-secondary !text-rose-600">Delete server</button>
                    </form>
                </details>
                <TabBar tabs={tabs} tab={tabState.tab} setTab={tabState.setTab} />

                <TabPanel when="monitoring" tab={tabState.tab}>
                    <section className="panel">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div><h2 className="font-semibold">Resource history</h2><p className="mt-1 text-sm muted">CPU, memory, and disk from the metric agent</p></div>
                            <div className="flex gap-4 text-xs"><span className="text-cyan-600">CPU</span><span className="text-violet-600">Memory</span><span className="text-amber-600">Disk</span></div>
                        </div>
                        <MetricChart metrics={metrics} />
                        {latestMetric && <p className="mt-2 text-sm muted">CPU {latestMetric.cpu_percent}% · Memory {latestMetric.memory_percent}% · Disk {latestMetric.disk_percent}%</p>}
                    </section>
                    <section className="panel mt-6">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div><h2 className="font-semibold">Metric agent</h2><p className="mt-1 text-sm muted">{server.monitoring_enabled ? 'Enabled' : 'Disabled'} / Last seen {server.last_seen_at || 'never'}</p></div>
                            <div className="flex gap-3">
                                <a className="button-secondary" href={route('monitoring.agent', server.id)}>Download agent</a>
                                <button className="button-primary" onClick={() => router.post(route('monitoring.rotate', server.id))}>{server.monitoring_enabled ? 'Rotate secret' : 'Enable monitoring'}</button>
                                {server.monitoring_enabled && <button className="button-secondary !text-rose-600" onClick={() => router.delete(route('monitoring.disable', server.id))}>Disable</button>}
                            </div>
                        </div>
                        {latestMetric?.services && (
                            <div className="mt-5 flex flex-wrap gap-2">
                                {Object.entries(latestMetric.services).map(([name, running]) => (
                                    <span key={name} className={`badge ${running ? 'badge-success' : 'badge-danger'}`}>{name.replace('_', ' ')} {running ? 'up' : 'down'}</span>
                                ))}
                            </div>
                        )}
                    </section>
                    {server.monitoring_enabled && (
                        <section className="panel mt-6">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h2 className="font-semibold">Auto-heal</h2>
                                    <p className="mt-1 text-sm muted">{server.auto_heal_enabled ? 'Enabled' : 'Disabled'}. When enabled, Nginx, PHP-FPM, MySQL, Redis, and Supervisor are restarted after {server.auto_heal_consecutive_samples || 3} consecutive down samples, with a {server.auto_heal_cooldown_minutes || 30}-minute cooldown per service.</p>
                                </div>
                                {server.auto_heal_enabled
                                    ? <button className="button-secondary !text-rose-600" onClick={() => router.delete(route('auto-heal.disable', server.id))}>Disable auto-heal</button>
                                    : <button className="button-primary" onClick={() => router.post(route('auto-heal.enable', server.id))}>Enable auto-heal</button>}
                            </div>
                        </section>
                    )}
                    <div className="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
                        <AlertRuleForm server={server} />
                        <div className="space-y-3">
                            {(server.alert_rules || server.alertRules || []).length === 0 && <div className="panel text-center muted">No alert rules.</div>}
                            {(server.alert_rules || server.alertRules || []).map((rule: any) => (
                                <article key={rule.id} className="panel flex items-center justify-between gap-4">
                                    <div><h3 className="font-medium">{rule.name}</h3><p className="mt-1 text-sm muted">{rule.metric} {rule.operator} {rule.threshold} / {rule.consecutive_samples} samples / {rule.severity}</p></div>
                                    <button className="link-danger" onClick={() => router.delete(route('alert-rules.destroy', rule.id))}>Delete</button>
                                </article>
                            ))}
                        </div>
                    </div>
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        <section className="panel">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="font-semibold">Recent incidents</h2>
                                <Link href={notificationLinks?.incidents || `/notifications?tab=incidents&server=${server.id}`} className="link-action text-sm">{notificationLinks?.view_all || 'View all incidents'}</Link>
                            </div>
                            {(server.alert_incidents || server.alertIncidents || []).length === 0 && <p className="py-5 text-sm muted">No incidents on this server.</p>}
                            {(server.alert_incidents || server.alertIncidents || []).map((incident: any) => (
                                <div key={incident.id} className="border-t border-slate-100 py-3">
                                    <div className="flex justify-between gap-3"><span>{incident.message}</span><span className="text-xs uppercase">{incident.status}</span></div>
                                </div>
                            ))}
                        </section>
                        <section className="panel">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="font-semibold heading">Email notifications</h2>
                                <Link href={notificationLinks?.email || '/notifications?tab=email'} className="link-action text-sm">{notificationLinks?.manage || 'Manage notifications'}</Link>
                            </div>
                            <p className="mt-2 text-sm muted">Recipients are account-wide. Add mailboxes and choose which events they receive from the Notifications page.</p>
                        </section>
                    </div>
                </TabPanel>

                <TabPanel when="databases" tab={tabState.tab}>
                    <div className="grid gap-6 lg:grid-cols-[380px_1fr]">
                        <DatabaseForm server={server} />
                        <div className="space-y-3">
                            {(server.databases || []).length === 0 && <div className="panel text-center muted">No managed databases.</div>}
                            {(server.databases || []).map((database: any) => (
                                <article key={database.id} className="panel">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <h3 className="font-medium heading">{database.name}</h3>
                                            <p className="text-xs muted">{database.engine} · {database.username} · {database.site?.domain || 'Not attached'}</p>
                                            {database.failure_reason && <p className="mt-1 text-xs text-rose-600">{database.failure_reason}</p>}
                                        </div>
                                        <StatusBadge status={database.status} />
                                    </div>
                                    {['ready', 'failed'].includes(database.status) && (
                                        <form onSubmit={(e) => { e.preventDefault(); const site_id = (e.currentTarget.elements.namedItem('site_id') as HTMLSelectElement).value; router.patch(route('databases.update', database.id), { site_id, _tab: 'databases' }); }} className="mt-3 flex gap-2">
                                            <select className="field mt-0" name="site_id" defaultValue={database.site_id || ''}><option value="">None</option>{(server.sites || []).map((site: any) => <option key={site.id} value={site.id}>{site.domain}</option>)}</select>
                                            <button className="button-secondary !px-2.5 text-xs">Save attachment</button>
                                        </form>
                                    )}
                                    <form onSubmit={(e) => { e.preventDefault(); const confirmation = (e.currentTarget.elements.namedItem('confirmation') as HTMLInputElement).value; if (confirm(`Permanently delete database ${database.name}?`)) router.delete(route('databases.destroy', database.id), { data: { confirmation, _tab: 'databases' } }); }} className="mt-3 flex gap-2">
                                        <input className="field mt-0" name="confirmation" placeholder={database.name} /><button className="button-secondary !text-rose-600">Delete database</button>
                                    </form>
                                    <div className="mt-4 border-t border-slate-100 pt-4">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <h4 className="text-sm font-medium">Import and export {database.name}</h4>
                                            <button className="button-secondary text-xs" disabled={database.status !== 'ready'} onClick={() => router.post(route('databases.export', database.id), { _tab: 'databases' })}>Create SQL export</button>
                                        </div>
                                        <form onSubmit={(e) => { e.preventDefault(); const sql = (e.currentTarget.elements.namedItem('sql') as HTMLInputElement).files?.[0]; if (sql) router.post(route('databases.import', database.id), { sql, _tab: 'databases' }, { forceFormData: true }); }} className="mt-3 flex flex-wrap gap-2">
                                            <input className="field mt-0" type="file" name="sql" accept=".sql,.txt" />
                                            <button className="button-primary">Import</button>
                                        </form>
                                        <div className="mt-3 flex flex-wrap gap-3">
                                            {(database.backups || []).filter((backup: any) => backup.type === 'export').map((backup: any) => (
                                                backup.status === 'ready'
                                                    ? <a key={backup.id} className="link-action" href={route('database-backups.download', backup.id)}>Download {backup.created_at}</a>
                                                    : <span key={backup.id} className="text-sm muted">Export {backup.status}</span>
                                            ))}
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                    <section className="panel mt-6">
                        <h2 className="font-semibold">phpMyAdmin</h2>
                        {phpmyadminUrl ? (
                            <div className="mt-3 flex gap-3"><a className="button-primary" href={phpmyadminUrl} target="_blank" rel="noreferrer">Open phpMyAdmin</a><button className="button-secondary !text-rose-600" onClick={() => { if (confirm('Remove phpMyAdmin from this server?')) router.delete(route('phpmyadmin.destroy', server.id)); }}>Remove</button></div>
                        ) : (
                            <form onSubmit={(e) => { e.preventDefault(); const port = (e.currentTarget.elements.namedItem('port') as HTMLInputElement).value; router.post(route('phpmyadmin.store', server.id), { port, _tab: 'databases' }); }} className="mt-3 flex items-center gap-2">
                                <input className="field mt-0 !w-28" type="number" name="port" min={1024} max={65535} defaultValue={phpMyAdminPort} title="Port (8080 is reserved for Laravel Reverb)" />
                                <button className="button-primary" disabled={status !== 'ready'}>Install phpMyAdmin</button>
                            </form>
                        )}
                    </section>
                </TabPanel>

                <TabPanel when="backups" tab={tabState.tab}>
                    <section className="panel">
                        <h2 className="font-semibold">{backupCopy.policy || 'Automated backup policy'}</h2>
                        {! server.provider_id && ! server.cloud_account_id && <p className="mt-2 text-sm muted">Custom servers support {backupCopy.custom || 'database backup policies'} only.</p>}
                        <BackupPolicyForm server={server} disks={backupDiskOptions} />
                        {(server.backup_policies || server.backupPolicies || []).map((policy: any) => (
                            <div key={policy.id} className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-sm">
                                <span>{policy.name} · {policy.type} · {policy.frequency} · {policy.enabled ? 'enabled' : 'disabled'}</span>
                                <span className="flex gap-3">
                                    <button className="link-action" onClick={() => router.patch(route('backup-policies.toggle', policy.id), { _tab: 'backups' })}>{policy.enabled ? 'Disable' : 'Enable'}</button>
                                    <button className="link-action" onClick={() => router.post(route('backup-policies.run', policy.id), { _tab: 'backups' })}>Run now</button>
                                    <button className="link-danger" onClick={() => router.delete(route('backup-policies.destroy', policy.id), { data: { _tab: 'backups' } })}>Delete</button>
                                </span>
                            </div>
                        ))}
                    </section>
                    <section className="panel mt-6">
                        <h2 className="font-semibold">{backupCopy.os || 'OS backups (provider snapshots)'}</h2>
                        {server.provider_id || server.cloud_account_id
                            ? (features.os_backups ? <SnapshotForm server={server} label={backupCopy.snapshot} /> : <Link href="/billing" className="button-secondary mt-3 inline-flex">{backupCopy.upgrade_os || 'Upgrade for OS backups'}</Link>)
                            : <p className="mt-2 text-sm muted">Provider snapshots are unavailable on custom servers.</p>}
                        <p className="mt-2 text-sm muted">{backupCopy.os_note || 'OS backup (provider snapshot) copies the whole disk at the cloud provider.'}</p>
                        {(server.snapshots || []).map((snap: any) => (
                            <div key={snap.id} className="mt-3 flex flex-wrap justify-between gap-3 text-sm">
                                <span>{snap.name || snap.label || snap.id} · {snap.status}</span>
                                <span className="flex gap-2">
                                    <button className="link-action" onClick={() => { const confirmation = prompt(`Type ${server.hostname} to restore`) || ''; if (confirmation) router.post(route('snapshots.restore', snap.id), { confirmation, _tab: 'backups' }); }}>Restore</button>
                                    <button className="link-danger" onClick={() => router.delete(route('snapshots.destroy', snap.id), { data: { _tab: 'backups' } })}>Delete</button>
                                </span>
                            </div>
                        ))}
                    </section>
                    {! features.database_backups && <p className="mt-4 text-sm muted">Database backup policies require a plan upgrade.</p>}
                </TabPanel>

                <TabPanel when="cron" tab={tabState.tab}>
                    <CronForm server={server} presets={cronPresets || []} />
                </TabPanel>

                <TabPanel when="workers" tab={tabState.tab}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        {(server.sites || []).map((site: any) => (
                            <section key={site.id} className="panel">
                                <h2 className="font-semibold">{site.domain}</h2>
                                {(site.queue_workers || site.queueWorkers || []).map((worker: any) => (
                                    <div key={worker.id} className="mt-3 border-t border-slate-100 pt-3 text-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <span>{worker.name} · {worker.type}{worker.type === 'queue' ? ` · ${worker.processes} processes · ${worker.queue}` : ''}{worker.type === 'reverb' ? ` · port ${worker.port}` : ''}</span>
                                            <span className="flex gap-3">
                                                <button className="link-action text-xs" onClick={() => router.post(route('workers.status', worker.id), { _tab: 'workers' })}>Check status</button>
                                                <button className="link-danger text-xs" onClick={() => router.delete(route('workers.destroy', worker.id), { data: { _tab: 'workers' } })}>Delete</button>
                                            </span>
                                        </div>
                                        {worker.runtime_status && <p className="mt-1 text-xs">Supervisor: {worker.runtime_status}</p>}
                                    </div>
                                ))}
                                <WorkerForm site={site} />
                            </section>
                        ))}
                        {(server.sites || []).length === 0 && <p className="muted">Add a site before configuring workers.</p>}
                    </div>
                </TabPanel>

                <TabPanel when="services" tab={tabState.tab}>
                    <section className="panel">
                        <h2 className="font-semibold">Server operations</h2>
                        <p className="mt-1 text-sm muted">Software hardening, package updates, and major release upgrades.</p>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <button className="button-secondary" onClick={() => { if (confirm(`Run software hardening on ${server.hostname}? SSH password auth will be disabled if a key is in use.`)) router.post(route('server-operations.store', server.id), { type: 'system:harden', _tab: 'services' }); }}>Software hardening</button>
                            <button className="button-secondary" onClick={() => router.post(route('server-operations.store', server.id), { type: 'system:update', _tab: 'services' })}>Update Ubuntu packages</button>
                            <button className="button-secondary" onClick={() => { const confirmation = prompt(`Type ${server.hostname} to confirm a major release upgrade`) || ''; if (confirmation) router.post(route('server-operations.store', server.id), { type: 'system:release-upgrade', confirmation, _tab: 'services' }); }}>Major release upgrade</button>
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {['nginx:test', 'nginx:reload', 'php:reload', 'supervisor:restart', 'redis:restart', 'mysql:restart'].map((type) => (
                                <button key={type} className="button-secondary !px-3 !py-1.5 text-xs" onClick={() => router.post(route('server-operations.store', server.id), { type, _tab: 'services' })}>{type}</button>
                            ))}
                        </div>
                    </section>
                    <section className="panel mt-6">
                        <h2 className="font-semibold">PHP extensions</h2>
                        <PhpExtensionForm server={server} />
                    </section>
                    <section className="panel mt-6">
                        <h2 className="font-semibold">Recent operations</h2>
                        {(server.operations || operations || []).length === 0 && <p className="mt-3 text-sm muted">No operations yet.</p>}
                        {(server.operations || []).map((operation: any) => (
                            <p key={operation.id} className="mt-2 text-sm muted">{operation.type} · {operation.status}</p>
                        ))}
                    </section>
                </TabPanel>
                </>
                )}
            </div>
        </ConsoleLayout>
    );
}

function incompleteStatusCopy(status: string): string {
    if (status === 'awaiting_payment') {
        return 'This server is not ready yet. Complete payment to start provisioning. This page will update automatically.';
    }
    if (status === 'failed') {
        return 'Provisioning did not finish. Retry bootstrap if the host is available, or cancel this server.';
    }
    if (status === 'deleting') {
        return 'This server is being removed.';
    }
    if (status === 'creating') {
        return 'Creating the cloud server. This page will update automatically.';
    }
    if (status === 'active') {
        return 'The host is up. Bootstrap is starting. This page will update automatically.';
    }
    if (status === 'provisioning') {
        return 'Installing and configuring the server. This page will update automatically.';
    }

    return 'Queued. Provisioning will start shortly. This page will update automatically.';
}

function IncompleteServerPanel({ server, status, branding }: { server: any; status: string; branding: { name: string } }) {
    const progress = Math.min(100, Math.max(0, Number(server.progress) || 0));
    const showProgress = !['awaiting_payment', 'failed', 'deleting'].includes(status);

    return (
        <section className="panel mt-5">
            <h2 className="font-semibold">Waiting for this server</h2>
            <p className="mt-2 text-sm muted">{incompleteStatusCopy(status)}</p>
            {showProgress && (
                <div className="mt-4 max-w-md">
                    <div className="flex items-center justify-between gap-3 text-xs muted">
                        <span className="truncate">{server.current_step || 'Provisioning'}</span>
                        <span className="shrink-0 tabular-nums">{progress}%</span>
                    </div>
                    <div className="meter mt-1.5"><span className="meter-fill" style={{ width: `${progress}%` }} /></div>
                </div>
            )}
            {status !== 'deleting' && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const confirmation = (e.currentTarget.elements.namedItem('confirmation') as HTMLInputElement).value;
                        if (confirm(`Permanently delete ${server.hostname}?`)) {
                            router.delete(route('servers.destroy', server.id), { data: { confirmation } });
                        }
                    }}
                    className="mt-6 flex flex-wrap items-end gap-3"
                >
                    <label className="block text-sm">
                        <span className="muted">{status === 'awaiting_payment' ? 'Cancel this server' : 'Delete this server'}</span>
                        <input className="field mt-1" name="confirmation" placeholder={`Type ${server.hostname} to confirm`} />
                    </label>
                    <button className="button-secondary !text-rose-600">{status === 'awaiting_payment' ? 'Cancel server' : 'Delete server'}</button>
                    <p className="basis-full text-xs muted">Removes this server from {branding.name}{server.provider_id ? ' and destroys its Droplet at the provider' : ''}.</p>
                </form>
            )}
        </section>
    );
}

function TransferForm({ server, teams }: any) {
    const form = useForm({ team_id: server.team_id || '', confirmation: '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.patch(route('servers.team.update', server.id)); }} className="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
            <select className="field mt-0" name="team_id" value={form.data.team_id} onChange={(e) => form.setData('team_id', e.target.value)}>
                <option value="">Personal workspace</option>
                {(teams || []).map((team: any) => <option key={team.id} value={team.id}>{team.name}</option>)}
            </select>
            <input className="field mt-0" name="confirmation" placeholder={`Type ${server.hostname} to confirm`} value={form.data.confirmation} onChange={(e) => form.setData('confirmation', e.target.value)} />
            <button className="button-secondary">Transfer</button>
        </form>
    );
}

function DatabaseForm({ server }: any) {
    const form = useForm({ engine: 'mysql', name: '', username: '', site_id: '', _tab: 'databases' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('databases.store', server.id)); }} className="panel h-fit">
            <h2 className="font-semibold">Create database</h2>
            <label className="mt-4 block text-sm">Engine<select className="field" name="engine" value={form.data.engine} onChange={(e) => form.setData('engine', e.target.value)}><option value="mysql">MySQL / MariaDB</option><option value="postgresql">PostgreSQL</option></select></label>
            <label className="mt-4 block text-sm">Database name<input className="field" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="application" /></label>
            <label className="mt-4 block text-sm">Username<input className="field" name="username" value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} placeholder="application_user" /></label>
            <label className="mt-4 block text-sm">Attach to site<select className="field" name="site_id" value={form.data.site_id} onChange={(e) => form.setData('site_id', e.target.value)}><option value="">None</option>{(server.sites || []).map((site: any) => <option key={site.id} value={site.id}>{site.domain}</option>)}</select></label>
            <button className="button-primary mt-5">Create database</button>
        </form>
    );
}

function BackupPolicyForm({ server, disks }: any) {
    const form = useForm({ name: 'Nightly', type: 'database', frequency: 'daily', run_at: '02:00', timezone: 'UTC', weekday: 1, day_of_month: 1, retention_count: 7, disk: Object.keys(disks || { local: 'Local' })[0], managed_database_id: '', _tab: 'backups' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('backup-policies.store', server.id)); }} className="mt-4 grid gap-3 sm:grid-cols-2">
            <label className="text-sm">Name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
            <label className="text-sm">Type<select className="field" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}><option value="database">Database backup</option>{(server.provider_id || server.cloud_account_id) && <option value="snapshot">OS backups (provider snapshots)</option>}</select></label>
            <label className="text-sm">Frequency<select className="field" value={form.data.frequency} onChange={(e) => form.setData('frequency', e.target.value)}><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
            <label className="text-sm">Run at<input className="field" type="time" value={form.data.run_at} onChange={(e) => form.setData('run_at', e.target.value)} /></label>
            <label className="text-sm">Timezone<input className="field" value={form.data.timezone} onChange={(e) => form.setData('timezone', e.target.value)} /></label>
            <label className="text-sm">Retention<input className="field" type="number" min={1} max={100} value={form.data.retention_count} onChange={(e) => form.setData('retention_count', Number(e.target.value))} /></label>
            <label className="text-sm">Storage disk<select className="field" name="disk" value={form.data.disk} onChange={(e) => form.setData('disk', e.target.value)}>{Object.entries(disks || { local: 'Local' }).map(([key, label]) => <option key={key} value={key}>{String(label)}</option>)}</select></label>
            {form.data.type === 'database' && <label className="text-sm">Database backup<select className="field" value={form.data.managed_database_id} onChange={(e) => form.setData('managed_database_id', e.target.value)}><option value="">None</option>{(server.databases || []).map((database: any) => <option key={database.id} value={database.id}>{database.name}</option>)}</select></label>}
            <button className="button-primary self-end">Save policy</button>
        </form>
    );
}

function SnapshotForm({ server, label }: { server: any; label?: string }) {
    const form = useForm({ name: `${server.hostname}-manual`, _tab: 'backups' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('snapshots.store', server.id)); }} className="mt-3 flex flex-wrap gap-2">
            <input className="field mt-0" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            <button className="button-primary">{label || 'Create snapshot'}</button>
        </form>
    );
}

function CronForm({ server, presets }: { server: any; presets: Array<{ label: string; name: string; expression: string; command: string }> }) {
    const form = useForm({ name: '', expression: '* * * * *', command: '', _tab: 'cron' });
    return (
        <div className="grid gap-6 lg:grid-cols-[380px_1fr]">
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('cron-jobs.store', server.id)); }} className="panel">
                <h2 className="font-semibold">Add cron job</h2>
                <div className="mt-4" data-cron-presets>
                    <p className="text-sm">Preset</p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {(presets || []).map((preset) => (
                            <button key={preset.label} type="button" data-cron-command={preset.command} className="rounded border px-2.5 py-1 text-xs" onClick={() => form.setData({ ...form.data, name: preset.name, expression: preset.expression, command: preset.command })}>{preset.label}</button>
                        ))}
                    </div>
                </div>
                <label className="mt-4 block text-sm">Name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                <label className="mt-4 block text-sm">Expression<input className="field font-mono" value={form.data.expression} onChange={(e) => form.setData('expression', e.target.value)} /></label>
                <label className="mt-4 block text-sm">Command<input className="field font-mono" value={form.data.command} onChange={(e) => form.setData('command', e.target.value)} /></label>
                <button className="button-primary mt-5">Add cron</button>
            </form>
            <div className="space-y-3">
                {(server.cron_jobs || server.cronJobs || []).map((cron: any) => (
                    <article key={cron.id} className="panel">
                        <p className="heading">{cron.name} <span className="text-xs muted capitalize">· {cron.status}</span></p>
                        <code className="text-xs muted">{cron.expression} · {cron.command}</code>
                        <div className="mt-3 flex gap-3">
                            <button className="link-action" onClick={() => router.patch(route('cron-jobs.toggle', cron.id), { _tab: 'cron' })}>{cron.enabled ? 'Disable' : 'Enable'}</button>
                            <button className="link-danger" onClick={() => router.delete(route('cron-jobs.destroy', cron.id), { data: { _tab: 'cron' } })}>Delete</button>
                        </div>
                    </article>
                ))}
                {(server.cron_jobs || server.cronJobs || []).length === 0 && <div className="panel text-center muted">No cron jobs.</div>}
            </div>
        </div>
    );
}

function WorkerForm({ site }: any) {
    const form = useForm({ name: 'horizon', type: 'horizon', processes: 1, queue: 'default', connection: 'redis', port: 8080, tries: 3, timeout: 90, memory: 256, _tab: 'workers' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); router.post(route('workers.store', site.id), form.data); }} className="mt-4 grid gap-2">
            <input className="field" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            <select className="field" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}><option value="horizon">Horizon</option><option value="queue">Queue</option><option value="reverb">Reverb</option></select>
            {form.data.type === 'queue' && (
                <>
                    <input className="field" placeholder="Queue" value={form.data.queue} onChange={(e) => form.setData('queue', e.target.value)} />
                    <input className="field" type="number" min={1} max={20} value={form.data.processes} onChange={(e) => form.setData('processes', Number(e.target.value))} />
                </>
            )}
            {form.data.type === 'reverb' && <input className="field" type="number" min={1024} max={65535} value={form.data.port} onChange={(e) => form.setData('port', Number(e.target.value))} />}
            <button className="button-secondary text-xs">Add worker</button>
        </form>
    );
}

function AlertRuleForm({ server }: any) {
    const form = useForm({ name: 'High CPU', metric: 'cpu_percent', operator: 'gte', threshold: 90, consecutive_samples: 3, cooldown_minutes: 30, severity: 'warning', _tab: 'monitoring' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('alert-rules.store', server.id)); }} className="panel h-fit">
            <h2 className="font-semibold">Create alert rule</h2>
            <label className="mt-4 block text-sm">Name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
            <label className="mt-4 block text-sm">Metric<select className="field" value={form.data.metric} onChange={(e) => form.setData('metric', e.target.value)}><option value="cpu_percent">CPU percent</option><option value="memory_percent">Memory percent</option><option value="disk_percent">Disk percent</option><option value="load_average">Load average</option><option value="server_offline">Offline minutes</option></select></label>
            <div className="grid grid-cols-2 gap-3">
                <label className="mt-4 block text-sm">Operator<select className="field" value={form.data.operator} onChange={(e) => form.setData('operator', e.target.value)}><option value="gte">At least</option><option value="gt">Greater than</option><option value="lte">At most</option><option value="lt">Less than</option></select></label>
                <label className="mt-4 block text-sm">Threshold<input className="field" type="number" step="0.01" value={form.data.threshold} onChange={(e) => form.setData('threshold', Number(e.target.value))} /></label>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <label className="mt-4 block text-sm">Samples<input className="field" type="number" min={1} max={12} value={form.data.consecutive_samples} onChange={(e) => form.setData('consecutive_samples', Number(e.target.value))} /></label>
                <label className="mt-4 block text-sm">Cooldown<input className="field" type="number" min={5} value={form.data.cooldown_minutes} onChange={(e) => form.setData('cooldown_minutes', Number(e.target.value))} /></label>
            </div>
            <label className="mt-4 block text-sm">Severity<select className="field" value={form.data.severity} onChange={(e) => form.setData('severity', e.target.value)}><option value="warning">Warning</option><option value="critical">Critical</option><option value="info">Info</option></select></label>
            <button className="button-primary mt-5">Create rule</button>
        </form>
    );
}

function PhpExtensionForm({ server }: any) {
    const form = useForm({ extension: 'gd', _tab: 'services' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('php-extensions.store', server.id)); }} className="mt-4 flex gap-2">
            <select className="field mt-0" value={form.data.extension} onChange={(e) => form.setData('extension', e.target.value)}>
                {phpExtensions.map((extension) => <option key={extension} value={extension}>{extension}</option>)}
            </select>
            <button className="button-secondary">Install</button>
        </form>
    );
}
