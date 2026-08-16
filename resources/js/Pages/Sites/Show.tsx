import { FormEvent, useMemo, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { Pagination } from '../../Components/Pagination';
import { StatusBadge } from '../../Components/StatusBadge';
import { TabBar, TabPanel, Tabs } from '../../Components/Tabs';
import { PageProps } from '../../types';
import { useLiveReload } from '../../lib/live';
import { route } from '../../lib/route';
import { enumValue, items } from '../../lib/ui';

type Meta = {
    is_react: boolean; is_wordpress: boolean; is_laravel: boolean; uses_php: boolean; platform_label: string;
    is_staging: boolean; is_production: boolean; wordpress_installed: boolean; has_database: boolean; secure: boolean;
    php_versions: string[]; scheduler_command: string; webhook_url: string; visit_url: string;
    deploy_action: string; database_notice: string | null; visit_label: string;
};

export default function Show({ site, meta, tabs, logSources, stagingSitesEnabled, directoryThemes, directoryPlugins, wordpressThemes, wordpressPlugins, deployments, environment }: any) {
    const { branding, features } = usePage<PageProps>().props;
    const tabState = Tabs({ tabs, initial: 'overview' });
    const scheme = meta.secure ? 'https://' : 'http://';
    const action = meta.deploy_action || (meta.is_wordpress ? (meta.wordpress_installed ? 'Reinstall WordPress' : 'Install WordPress') : 'Deploy now');
    const [envText, setEnvText] = useState(environment || '');
    const [cronPreset, setCronPreset] = useState({ name: '', expression: '* * * * *', command: '' });
    const [logSource, setLogSource] = useState(meta.is_wordpress || meta.is_react ? 'nginx' : 'laravel');
    const [logError, setLogError] = useState<string | null>(null);
    const snapshots = site.log_snapshots || site.logSnapshots || [];
    const snapshot = snapshots.find((row: any) => row.source === logSource);
    const running = snapshot && ['pending', 'running'].includes(snapshot.status);
    const logsTabActive = tabState.tab === 'logs';
    const canSiteBackups = Boolean(features.site_backups);
    const cert = [...(site.ssl_certificates || site.sslCertificates || [])].sort((a: any, b: any) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())[0];
    const sslBusy = cert && ['pending', 'issuing', 'removing'].includes(cert.status);
    const isCustomSsl = cert?.provider === 'custom';
    const settled = ['active', 'failed'].includes(site.status);
    const visibleSources = useMemo(() => Object.entries(logSources || {}).filter(([key]) => {
        if (meta.is_react) return ['nginx', 'nginx-access'].includes(key);
        if (meta.is_wordpress) return ! ['laravel', 'reverb', 'supervisor'].includes(key);
        return true;
    }), [logSources, meta.is_react, meta.is_wordpress]);
    const needsDatabase = ! meta.has_database && ! meta.is_react;
    const backups = site.backups || [];
    const fullBackups = backups.filter((backup: any) => backup.kind === 'full_app');
    const wpBackups = backups.filter((backup: any) => backup.kind === 'wordpress_local');
    const incidents = site.monitor_incidents || site.monitorIncidents || [];

    useLiveReload({
        active: ! settled,
        channels: [`sites.${site.id}`],
        events: ['.status-updated'],
        only: ['site', 'meta', 'deployments'],
        interval: 5000,
    });
    useLiveReload({
        active: logsTabActive && Boolean(running),
        only: ['site'],
        interval: 2000,
    });

    const deployNow = (e: FormEvent) => {
        e.preventDefault();
        if (meta.wordpress_installed && ! confirm('Replace the WordPress core files with the latest release? Your database, uploads, plugins, and themes are kept.')) return;
        router.post(route('sites.deploy', site.id));
    };

    return (
        <ConsoleLayout crumb={site.domain}>
            <div className="app-main">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <Link className="link-action" href={route('sites.index')}>← Sites</Link>
                        <div className="mt-2 flex flex-wrap items-center gap-3">
                            <h1 className="page-title !mt-0">{site.domain}</h1>
                            <StatusBadge status={site.status} />
                            <span className="badge badge-neutral">{meta.platform_label}</span>
                            <span className={`badge ${meta.is_staging ? 'badge-warning' : 'badge-success'}`}>{meta.is_staging ? 'Staging' : 'Production'}</span>
                            {meta.is_staging && site.production_site && <Link className="link-action text-xs" href={route('sites.show', site.production_site.id)}>Production: {site.production_site.domain}</Link>}
                            {meta.is_production && site.staging_site && <Link className="link-action text-xs" href={route('sites.show', site.staging_site.id)}>Staging: {site.staging_site.domain}</Link>}
                            <a href={meta.visit_url || scheme + site.domain} target="_blank" rel="noreferrer" className="button-secondary !min-h-7 !px-2.5 !py-1 text-xs">{meta.visit_label || 'Visit site'}</a>
                        </div>
                        <p className="mt-2 text-sm muted">{site.server?.name}{meta.uses_php ? ` · PHP ${site.php_version}` : ''}</p>
                    </div>
                    <div className="flex gap-3">
                        {! meta.is_wordpress && <button type="button" className="button-secondary" onClick={() => tabState.setTab('deploy')}>Edit site</button>}
                        {meta.is_staging && stagingSitesEnabled && (
                            <form onSubmit={(e) => { e.preventDefault(); if (confirm(`Copy staging branch and settings to production and deploy ${site.production_site?.domain}?`)) router.post(route('sites.promote', site.id)); }}>
                                <button className="button-secondary !text-amber-700" disabled={site.status !== 'active'}>Promote to production</button>
                            </form>
                        )}
                        <form onSubmit={deployNow}><button className="button-primary" disabled={site.status !== 'active' || needsDatabase}>{action}</button></form>
                    </div>
                </div>
                <Flash />
                {needsDatabase && (
                    <div className="flash-warning mt-5">
                        <p className="font-medium">{meta.database_notice || `Create a database before ${meta.is_wordpress ? 'installing' : 'deploying'}`}</p>
                        <p className="mt-1">{meta.is_wordpress ? 'WordPress cannot run without one.' : 'This site has no DB_CONNECTION in its environment, so Laravel would fall back to SQLite.'} Create one on <Link className="link-action" href={`${route('servers.manage', site.server_id)}?tab=databases`}>{site.server?.name}</Link>.</p>
                    </div>
                )}
                {meta.is_wordpress && meta.has_database && site.last_deployed_at && ! meta.wordpress_installed && (
                    <div className="flash-info mt-5">
                        <p className="font-medium">Finish the WordPress install</p>
                        <p className="mt-1">Complete the setup at <a className="link-action" href={`${scheme}${site.domain}/wp-admin/install.php`} target="_blank" rel="noreferrer">{site.domain}/wp-admin/install.php</a></p>
                        <form onSubmit={(e) => { e.preventDefault(); router.post(route('sites.wordpress-status', site.id)); }} className="mt-3"><button className="button-secondary !px-3 !py-1.5 text-xs">Check again</button></form>
                    </div>
                )}
                <div className="mt-5"><Link href={route('sites.remote', site.id)} className="button-secondary inline-block">Open PHP, Nginx, files, and console</Link></div>
                <details id="danger-zone" className="panel panel-danger mt-5"><summary className="cursor-pointer font-medium text-rose-600 dark:text-rose-300">Danger zone</summary>
                    <p className="mt-3 text-sm muted">Permanently removes this site from {branding.name} and deletes its Nginx configuration, PHP-FPM pool, SSL certificate, and files from the server.</p>
                    <form onSubmit={(e) => { e.preventDefault(); const form = e.currentTarget; const confirmation = (form.elements.namedItem('confirmation') as HTMLInputElement).value; if (confirm(`Permanently delete ${site.domain} and all its files on the server?`)) router.delete(route('sites.destroy', site.id), { data: { confirmation } }); }} className="mt-4 flex flex-wrap gap-3">
                        <input className="field mt-0" name="confirmation" placeholder={`Type ${site.domain} to confirm`} /><button className="button-secondary !text-rose-600">Delete site</button>
                    </form>
                </details>
                <TabBar tabs={tabs} tab={tabState.tab} setTab={tabState.setTab} />

                <TabPanel when="overview" tab={tabState.tab}>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {meta.is_wordpress ? (
                            <>
                                <div className="panel"><p className="text-xs uppercase tracking-wide muted">Source</p><p className="mt-2 truncate text-sm heading">wordpress.org</p></div>
                                <div className="panel"><p className="text-xs uppercase tracking-wide muted">Installed version</p><p className="mt-2 text-sm heading">{items(deployments).find((d: any) => enumValue(d.status) === 'successful')?.commit_message || 'Not deployed yet'}</p><p className="mt-1 text-xs">{meta.wordpress_installed ? 'Setup complete' : 'Setup not finished'}</p></div>
                            </>
                        ) : (
                            <>
                                <div className="panel"><p className="text-xs uppercase tracking-wide muted">Repository</p><p className="mt-2 truncate text-sm heading">{site.repository_url}</p></div>
                                <div className="panel"><p className="text-xs uppercase tracking-wide muted">Branch</p><p className="mt-2 font-mono text-sm heading">{site.branch}</p></div>
                            </>
                        )}
                        <div className="panel"><p className="text-xs uppercase tracking-wide muted">Last deployed</p><p className="mt-2 text-sm heading">{site.last_deployed_at || 'Never'}</p></div>
                    </div>
                    <section className="panel-flush mt-6">
                        <div className="border-b border-slate-100 px-5 py-4 dark:border-white/5"><h2 className="section-title">Deployment history</h2></div>
                        {items(deployments).length === 0 && <div className="px-6 py-10 text-center muted">No deployments yet.</div>}
                        {items(deployments).map((deployment: any) => (
                            <div key={deployment.id} className="data-row grid items-center gap-4 sm:grid-cols-[1fr_150px_120px_auto]">
                                <Link href={route('deployments.show', deployment.id)}><p className="font-mono text-sm heading">{deployment.release || deployment.id.slice(0, 14)}</p><p className="mt-1 text-xs muted">{deployment.trigger} · {deployment.created_at}</p></Link>
                                <span className="capitalize text-sm">{enumValue(deployment.status).replace('_', ' ')}</span>
                                <span className="text-xs muted">{deployment.duration_for_humans || '—'}</span>
                                {deployment.release && ['successful', 'rolled_back'].includes(enumValue(deployment.status)) ? (
                                    <form onSubmit={(e) => { e.preventDefault(); router.post(route('sites.rollback', [site.id, deployment.id])); }}><button className="button-secondary !px-3 !py-1.5 text-xs !text-amber-600">Rollback</button></form>
                                ) : <span />}
                            </div>
                        ))}
                    </section>
                    <Pagination links={deployments.links} />
                    {meta.is_production && stagingSitesEnabled && (
                        <section id="staging-setup" className="panel mt-6">
                            <h2 className="font-semibold heading">Staging environment</h2>
                            {site.staging_site ? <p className="mt-2 text-sm muted">Staging is live at <Link className="link-action" href={route('sites.show', site.staging_site.id)}>{site.staging_site.domain}</Link>.</p> : (
                                <>
                                    <p className="mt-2 text-sm muted">Create a linked staging site on the same server using your own domain. You must point DNS at this server before SSL can issue.</p>
                                    {site.server?.public_ip && <div className="well mt-4"><p className="text-xs uppercase tracking-wide muted">DNS A record target</p><p className="mt-1 font-mono text-sm heading">{site.server.public_ip}</p></div>}
                                    <StagingForm site={site} meta={meta} />
                                </>
                            )}
                        </section>
                    )}
                </TabPanel>

                {(meta.is_wordpress ? ['themes', 'plugins'] : []).map((key) => (
                    <TabPanel key={key} when={key} tab={tabState.tab}>
                        <WpInventory site={site} meta={meta} target={key === 'themes' ? 'theme' : 'plugin'} plural={key} items={key === 'themes' ? wordpressThemes : wordpressPlugins} directory={key === 'themes' ? directoryThemes : directoryPlugins} ready={meta.wordpress_installed} />
                    </TabPanel>
                ))}

                <TabPanel when="backups" tab={tabState.tab}>
                    <section className="panel">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div><h2 className="font-semibold heading">{meta.full_backups_heading || 'Full site backups'}</h2><p className="mt-1 text-sm muted">Offloads the live application tree and a database dump.</p></div>
                            {canSiteBackups ? <form onSubmit={(e) => { e.preventDefault(); router.post(route('site-backups.store', site.id)); }}><button className="button-primary">{meta.full_backup_label || 'Create full backup'}</button></form> : <Link href={route('billing.index')} className="button-primary">Upgrade to unlock</Link>}
                        </div>
                        {! canSiteBackups && <p className="flash-warning mt-4 text-xs">Site backups (code + database) aren’t on your plan.</p>}
                        {fullBackups.length === 0 && <p className="mt-6 text-center text-sm muted">No full site backups yet. Create one before a risky deploy or plugin change.</p>}
                        {fullBackups.map((backup: any) => (
                            <article key={backup.id} className="mt-4 space-y-3 border-t border-slate-100 pt-4">
                                <div className="flex flex-wrap justify-between gap-3">
                                    <div>
                                        <p className="font-mono text-sm heading">{backup.label}</p>
                                        <p className="text-xs muted">{backup.status}{backup.size_for_humans ? ` · ${backup.size_for_humans}` : ''}{backup.user?.name ? ` · ${backup.user.name}` : ''}</p>
                                        {backup.failure_reason && <p className="mt-1 text-xs text-rose-600">{backup.failure_reason}</p>}
                                    </div>
                                    {canSiteBackups && (
                                        <div className="flex gap-2">
                                            {backup.status === 'ready' && <a className="button-secondary !px-3 !py-1.5 text-xs" href={route('site-backups.download', backup.id)}>Download</a>}
                                            <button className="button-secondary !px-3 !py-1.5 text-xs !text-rose-600" onClick={() => { if (confirm(backup.status === 'ready' ? 'Delete this archive from storage?' : 'Remove this backup record?')) router.delete(route('site-backups.destroy', backup.id)); }}>{backup.status === 'running' ? 'Cancel' : 'Delete'}</button>
                                        </div>
                                    )}
                                </div>
                                {canSiteBackups && backup.status === 'ready' && (
                                    <form onSubmit={(e) => { e.preventDefault(); const confirmation = (e.currentTarget.elements.namedItem('confirmation') as HTMLInputElement).value; router.post(route('site-backups.restore', backup.id), { confirmation }); }} className="well">
                                        <p className="text-xs muted">Restore this archive. Type <span className="font-mono heading">{site.domain}</span> to confirm.</p>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <input className="field mt-0 min-w-[12rem] flex-1 !py-1.5 text-xs" name="confirmation" placeholder={`Type ${site.domain}`} />
                                            <button className="button-secondary !px-3 !py-1.5 text-xs !text-amber-600">Restore</button>
                                        </div>
                                    </form>
                                )}
                            </article>
                        ))}
                    </section>
                    {meta.is_wordpress && (
                        <section className="panel mt-6">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div><h2 className="font-semibold heading">On-server WordPress backup</h2><p className="mt-1 text-sm muted">Captures the database and <code>wp-content</code> on this VPS. The ten most recent are kept on the server.</p></div>
                                {meta.wordpress_installed
                                    ? <form onSubmit={(e) => { e.preventDefault(); router.post(route('wordpress.backup', site.id)); }}><button className="button-primary">{meta.wp_backup_now || 'Back up now'}</button></form>
                                    : <p className="text-sm muted">Install WordPress before managing backups.</p>}
                            </div>
                            {meta.wordpress_installed && wpBackups.length === 0 && <p className="mt-6 text-center text-sm muted">No backups yet. Take one before installing a plugin or updating core.</p>}
                            {wpBackups.map((backup: any) => (
                                <div key={backup.id} className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                    <div>
                                        <p className="font-mono text-sm heading">{backup.label}</p>
                                        <p className="text-xs muted">{backup.status}{backup.size_for_humans ? ` · ${backup.size_for_humans}` : ''}</p>
                                    </div>
                                    {backup.status === 'completed' && (
                                        <form onSubmit={(e) => { e.preventDefault(); if (confirm(`Restore ${backup.label}? The database and wp-content are replaced with this backup.`)) router.post(route('wordpress.restore', backup.id)); }}>
                                            <button className="button-secondary !px-3 !py-1.5 text-xs !text-amber-600">Restore</button>
                                        </form>
                                    )}
                                </div>
                            ))}
                        </section>
                    )}
                </TabPanel>

                <TabPanel when="environment" tab={tabState.tab}>
                    <form onSubmit={(e) => { e.preventDefault(); router.put(route('sites.environment', site.id), { environment: envText }); }} className="panel">
                        <h2 className="font-semibold heading">Encrypted environment</h2>
                        <p className="mt-2 text-sm muted">Values are encrypted at rest and written only to the server's shared release directory.</p>
                        <textarea className="field mt-5 min-h-[28rem] font-mono text-xs leading-6" name="environment" value={envText} onChange={(e) => setEnvText(e.target.value)} spellCheck={false} />
                        <button className="button-primary mt-5">Save environment</button>
                    </form>
                </TabPanel>

                <TabPanel when="deploy" tab={tabState.tab}><DeployForm site={site} meta={meta} /></TabPanel>

                <TabPanel when="ssl" tab={tabState.tab}>
                    <section className="panel">
                        <h2 className="font-semibold heading">{site.domain}</h2>
                        <p className="mt-1 text-sm muted">{cert ? `${cert.status} · ${isCustomSsl ? 'Custom' : 'Let’s Encrypt'}` : 'No certificate'}{cert?.expires_at ? ` · expires ${cert.expires_at}` : ''}</p>
                        {cert?.status === 'active' && <p className="mt-2 text-sm font-medium text-emerald-600">Secure</p>}
                        {cert?.failure_reason && <p className="mt-3 text-xs text-rose-600">{cert.failure_reason}</p>}
                        {site.status !== 'active' && <p className="mt-3 text-xs muted">The site must finish configuring before a certificate can be installed.</p>}
                        <p className="mt-3 text-xs muted">Point an A record for <code>{site.domain}</code> at <code>{site.server?.public_ip || 'this server’s public IP'}</code> before issuing HTTPS.</p>
                        {cert?.status === 'removing' && <p className="mt-4 text-xs muted">Removing SSL… the site will serve HTTP when this finishes.</p>}
                        {cert && cert.status !== 'removing' && (
                            <form onSubmit={(e) => { e.preventDefault(); if (confirm(`Remove SSL from ${site.domain}? The site will serve HTTP until you issue Let’s Encrypt or upload a custom certificate.`)) router.delete(route('ssl.destroy', site.id), { data: { _tab: 'ssl' } }); }} className="mt-4">
                                <button className="button-secondary !px-3 !py-1.5 text-xs !text-rose-600" disabled={site.status !== 'active' || sslBusy}>Remove SSL</button>
                            </form>
                        )}
                    </section>
                    <section className="panel mt-6">
                        <h3 className="font-semibold heading">Let’s Encrypt</h3>
                        <p className="mt-1 text-sm muted">Free automated certificate. DNS must point at this server before issuing.</p>
                        <LetsEncryptForm site={site} cert={cert} isCustomSsl={isCustomSsl} sslBusy={sslBusy} />
                    </section>
                    <section className="panel mt-6">
                        <h3 className="font-semibold heading">Custom certificate</h3>
                        <p className="mt-1 text-sm muted">Upload or paste a PEM fullchain and private key.</p>
                        <CustomSslForm site={site} cert={cert} sslBusy={sslBusy} />
                    </section>
                </TabPanel>

                <TabPanel when="logs" tab={tabState.tab}>
                    <section className="panel">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div><h2 className="font-semibold heading">Logs</h2><p className="mt-1 text-sm muted">Read from the server on request. Nothing is stored on it.</p></div>
                            <form onSubmit={(e) => {
                                e.preventDefault();
                                setLogError(null);
                                router.post(route('site-logs.store', site.id), { source: logSource, lines: 200, _tab: 'logs' }, {
                                    preserveScroll: true,
                                    onError: (errors) => {
                                        const message = Object.values(errors).flat().join(' ') || 'Could not queue the log read.';
                                        setLogError(message);
                                    },
                                });
                            }} className="flex gap-3">
                                <button className="button-primary" disabled={Boolean(running)}>{running ? 'Reading…' : 'Read log'}</button>
                            </form>
                        </div>
                        <div className="mt-5 flex flex-wrap gap-2">
                            {visibleSources.map(([key, label]) => (
                                <button key={key} type="button" onClick={() => { setLogSource(key); setLogError(null); }} className={`chip ${logSource === key ? 'chip-active' : ''}`}>{String(label)}</button>
                            ))}
                        </div>
                        {logError && <p className="mt-4 text-sm text-rose-600">{logError}</p>}
                        <pre className="log-pane mt-4 max-h-[32rem]">{! snapshot ? 'Choose a log and press Read log.' : running ? `Reading ${logSources?.[snapshot.source] || snapshot.source} from the server…` : snapshot.status === 'failed' ? (snapshot.output || 'The read failed.') : (snapshot.output || 'The log is empty.')}</pre>
                    </section>
                </TabPanel>

                <TabPanel when="cron" tab={tabState.tab}>
                    <div className="grid gap-6 lg:grid-cols-[380px_1fr]">
                        <form onSubmit={(e) => { e.preventDefault(); router.post(route('sites.cron-jobs.store', site.id), cronPreset); }} className="panel h-fit">
                            <h2 className="font-semibold heading">Add cron job</h2>
                            <p className="mt-1 text-sm muted">Runs on {site.server?.name} for this site.</p>
                            <div className="mt-4" data-cron-presets>
                                <p className="text-sm heading">Preset</p>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    <button type="button" className="chip !px-2.5 !py-1 text-xs" onClick={() => setCronPreset({ name: '', expression: '* * * * *', command: '' })}>Custom</button>
                                    {! meta.is_wordpress && <button type="button" data-cron-command={meta.scheduler_command} title={meta.scheduler_command} className="chip !px-2.5 !py-1 text-xs" onClick={() => setCronPreset({ name: 'Laravel scheduler', expression: '* * * * *', command: meta.scheduler_command })}>Laravel scheduler</button>}
                                </div>
                            </div>
                            <label className="mt-4 block text-sm heading">Name<input className="field" name="name" value={cronPreset.name} onChange={(e) => setCronPreset({ ...cronPreset, name: e.target.value })} placeholder="Laravel scheduler" /></label>
                            <label className="mt-4 block text-sm heading">Expression<input className="field font-mono" name="expression" value={cronPreset.expression} onChange={(e) => setCronPreset({ ...cronPreset, expression: e.target.value })} /></label>
                            <label className="mt-4 block text-sm heading">Command<input className="field font-mono text-xs" name="command" value={cronPreset.command} onChange={(e) => setCronPreset({ ...cronPreset, command: e.target.value })} /></label>
                            <button className="button-primary mt-5">Add cron</button>
                        </form>
                        <div className="space-y-3">
                            {(site.cron_jobs || site.cronJobs || []).map((cron: any) => (
                                <article key={cron.id} className="panel">
                                    <h3 className="font-medium heading">{cron.name} <span className="text-xs muted capitalize">· {cron.status}</span></h3>
                                    <code className="mt-2 block text-xs muted">{cron.expression} · {cron.command}</code>
                                    <div className="mt-3 flex gap-3">
                                        <button className="link-action" onClick={() => router.patch(route('cron-jobs.toggle', cron.id))}>{cron.enabled ? 'Disable' : 'Enable'}</button>
                                        <button className="link-danger" onClick={() => router.delete(route('cron-jobs.destroy', cron.id))}>Delete</button>
                                    </div>
                                </article>
                            ))}
                            {(site.cron_jobs || site.cronJobs || []).length === 0 && <div className="panel text-center muted">No cron jobs for this site.</div>}
                        </div>
                    </div>
                </TabPanel>

                <TabPanel when="queue" tab={tabState.tab}><QueuePanel site={site} /></TabPanel>

                <TabPanel when="webhook" tab={tabState.tab}>
                    <div className="panel">
                        <h2 className="font-semibold heading">Automatic deployment webhook</h2>
                        <p className="mt-2 text-sm muted">Point a push webhook at this endpoint. GitHub uses <code>X-Hub-Signature-256</code>; Bitbucket uses <code>X-Hub-Signature</code> (HMAC SHA-256). GitLab sends the secret as <code>X-Gitlab-Token</code>.</p>
                        <label className="mt-5 block text-sm heading">Endpoint<code className="code-block">{meta.webhook_url}</code></label>
                        <label className="mt-4 block text-sm heading">Secret<code className="code-block">{site.webhook_secret}</code></label>
                        <p className="mt-4 text-xs muted">Only pushes to <b>{site.branch}</b> are deployed. Duplicate commit hashes and deleted branches are ignored.</p>
                    </div>
                </TabPanel>

                <TabPanel when="monitoring" tab={tabState.tab}>
                    <section className="panel">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="font-semibold heading">Website monitoring</h2>
                                <p className="mt-1 text-sm muted">{site.site_monitoring_enabled ? 'Enabled' : 'Disabled'}. Probes HTTP availability and DNS against {site.server?.public_ip || 'the server IP'} every minute when enabled.{! meta.is_wordpress && ! meta.is_react && ' Laravel failed-job checks also run every 15 minutes.'}</p>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                {! site.site_monitoring_enabled
                                    ? <form onSubmit={(e) => { e.preventDefault(); const monitor_path = (e.currentTarget.elements.namedItem('monitor_path') as HTMLInputElement)?.value || '/'; router.post(route('sites.monitoring.enable', site.id), { monitor_path, _tab: 'monitoring' }); }} className="flex flex-wrap items-end gap-3">
                                        <label className="text-sm heading">Path<input className="field mt-1 !w-36 font-mono text-xs" name="monitor_path" defaultValue={site.monitor_path || '/'} /></label>
                                        <button className="button-primary" disabled={site.status !== 'active'}>Enable monitoring</button>
                                    </form>
                                    : <>
                                        <button className="button-secondary" onClick={() => router.post(route('sites.monitoring.check', site.id), { _tab: 'monitoring' })}>Check now</button>
                                        <button className="button-secondary !text-rose-600" onClick={() => router.delete(route('sites.monitoring.disable', site.id), { data: { _tab: 'monitoring' } })}>Disable</button>
                                    </>}
                            </div>
                        </div>
                        <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="well"><p className="text-xs uppercase tracking-wide muted">HTTP status</p><p className="mt-2 text-sm font-medium heading">{site.monitor_last_status ? String(site.monitor_last_status) : 'Not checked'}</p></div>
                            <div className="well"><p className="text-xs uppercase tracking-wide muted">Latency</p><p className="mt-2 text-sm font-medium heading">{site.monitor_last_latency_ms != null ? `${site.monitor_last_latency_ms} ms` : '—'}</p></div>
                            <div className="well"><p className="text-xs uppercase tracking-wide muted">DNS</p><p className="mt-2 text-sm font-medium heading">{site.dns_last_status ? String(site.dns_last_status).replace('_', ' ') : 'Not checked'}</p></div>
                            <div className="well"><p className="text-xs uppercase tracking-wide muted">Consecutive failures</p><p className="mt-2 text-sm font-medium heading">{site.monitor_consecutive_down || 0} / {site.monitor_consecutive_failures || 0}</p></div>
                        </div>
                        {(site.monitor_last_error || site.dns_last_error) && (
                            <div className="mt-4 space-y-1 text-sm text-rose-600">
                                {site.monitor_last_error && <p>HTTP: {site.monitor_last_error}</p>}
                                {site.dns_last_error && <p>DNS: {site.dns_last_error}</p>}
                            </div>
                        )}
                    </section>
                    <section className="panel mt-6">
                        <h2 className="font-semibold heading">Incidents</h2>
                        {incidents.length === 0 && <p className="py-5 text-sm muted">No monitoring incidents yet.</p>}
                        {incidents.map((incident: any) => (
                            <div key={incident.id} className="border-t border-slate-100 py-3">
                                <div className="flex justify-between gap-3"><span className="text-sm heading">{incident.message}</span><span className="text-xs uppercase">{incident.status}</span></div>
                                <p className="mt-1 text-xs muted">{String(incident.type || '').replace('_', ' ')}</p>
                            </div>
                        ))}
                    </section>
                </TabPanel>
            </div>
        </ConsoleLayout>
    );
}

function StagingForm({ site, meta }: any) {
    const form = useForm({ domain: '', branch: site.branch === 'main' ? 'staging' : site.branch, domain_source: 'custom' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('sites.staging.store', site.id)); }} className="mt-5 space-y-4">
            <label className="text-sm heading">Staging domain<input className="field" name="domain" value={form.data.domain} onChange={(e) => form.setData('domain', e.target.value)} placeholder="staging.example.com" required /></label>
            {! meta.is_wordpress && <label className="text-sm heading">Git branch<input className="field" name="branch" value={form.data.branch} onChange={(e) => form.setData('branch', e.target.value)} /></label>}
            <button className="button-primary" disabled={site.status !== 'active' || ! site.server?.public_ip}>Create staging site</button>
        </form>
    );
}

function DeployForm({ site, meta }: any) {
    const form = useForm({
        repository_url: site.repository_url || '',
        branch: site.branch || 'main',
        php_version: site.php_version || '',
        auto_deploy: Boolean(site.auto_deploy),
        zero_downtime: Boolean(site.zero_downtime),
        deployment_script: site.deployment_script || '',
    });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.patch(route('sites.update', site.id)); }} className="panel">
            <div className="grid gap-5 sm:grid-cols-2">
                <label className="text-sm heading sm:col-span-2">Repository URL<input className="field font-mono text-xs" name="repository_url" value={form.data.repository_url} onChange={(e) => form.setData('repository_url', e.target.value)} /><span className="mt-1 block text-xs muted">GitHub, GitLab, or Bitbucket — HTTPS or SSH.</span></label>
                <label className="text-sm heading">Branch<input className="field" name="branch" value={form.data.branch} onChange={(e) => form.setData('branch', e.target.value)} /></label>
                {meta.uses_php && <label className="text-sm heading">PHP version<select className="field" name="php_version" value={form.data.php_version} onChange={(e) => form.setData('php_version', e.target.value)}>{meta.php_versions.map((v: string) => <option key={v}>{v}</option>)}</select></label>}
                <label className="flex gap-2 text-sm heading"><input type="checkbox" name="auto_deploy" checked={form.data.auto_deploy} onChange={(e) => form.setData('auto_deploy', e.target.checked)} />Automatic deployments</label>
                <label className="flex gap-2 text-sm heading"><input type="checkbox" name="zero_downtime" checked={form.data.zero_downtime} onChange={(e) => form.setData('zero_downtime', e.target.checked)} />Zero-downtime releases</label>
                <label className="text-sm heading sm:col-span-2">Custom post-build script<textarea className="field min-h-44 font-mono text-xs" name="deployment_script" value={form.data.deployment_script} onChange={(e) => form.setData('deployment_script', e.target.value)} /></label>
            </div>
            <button className="button-primary mt-5">Save settings</button>
        </form>
    );
}

function LetsEncryptForm({ site, cert, isCustomSsl, sslBusy }: any) {
    const form = useForm({ force_https: isCustomSsl ? true : (cert?.force_https ?? true), auto_renew: isCustomSsl ? true : (cert?.auto_renew ?? true), _tab: 'ssl' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('ssl.store', site.id)); }} className="mt-5 flex flex-wrap items-center gap-4">
            <label className="flex gap-2 text-sm heading"><input type="checkbox" name="force_https" checked={form.data.force_https} onChange={(e) => form.setData('force_https', e.target.checked)} />Force HTTPS</label>
            <label className="flex gap-2 text-sm heading"><input type="checkbox" name="auto_renew" checked={form.data.auto_renew} onChange={(e) => form.setData('auto_renew', e.target.checked)} />Auto renew</label>
            <button className="button-primary" disabled={site.status !== 'active' || sslBusy}>{cert && ! isCustomSsl && cert.status === 'active' ? 'Renew / update' : 'Issue certificate'}</button>
        </form>
    );
}

function CustomSslForm({ site, cert, sslBusy }: any) {
    const form = useForm({ fullchain: null as File | null, private_key: null as File | null, fullchain_pem: '', private_key_pem: '', force_https: cert?.force_https ?? true, _tab: 'ssl' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('ssl.custom', site.id), { forceFormData: true }); }} className="mt-5 space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="text-sm heading">Fullchain PEM (file)<input className="field mt-1" type="file" name="fullchain" onChange={(e) => form.setData('fullchain', e.target.files?.[0] || null)} /></label>
                <label className="text-sm heading">Private key PEM (file)<input className="field mt-1" type="file" name="private_key" onChange={(e) => form.setData('private_key', e.target.files?.[0] || null)} /></label>
            </div>
            <details className="well !p-3"><summary className="cursor-pointer text-sm font-medium heading">Or paste PEM text</summary>
                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                    <label className="text-sm heading">Fullchain<textarea className="field mt-1 min-h-36 font-mono text-xs" name="fullchain_pem" value={form.data.fullchain_pem} onChange={(e) => form.setData('fullchain_pem', e.target.value)} /></label>
                    <label className="text-sm heading">Private key<textarea className="field mt-1 min-h-36 font-mono text-xs" name="private_key_pem" value={form.data.private_key_pem} onChange={(e) => form.setData('private_key_pem', e.target.value)} /></label>
                </div>
            </details>
            <div className="flex flex-wrap items-center gap-4">
                <label className="flex gap-2 text-sm heading"><input type="checkbox" name="force_https" checked={form.data.force_https} onChange={(e) => form.setData('force_https', e.target.checked)} />Force HTTPS</label>
                <button className="button-primary" disabled={site.status !== 'active' || sslBusy}>{cert ? 'Install / replace custom SSL' : 'Install custom SSL'}</button>
            </div>
        </form>
    );
}

function QueuePanel({ site }: any) {
    const installed = site.installed_packages || {};
    const managed = site.managed_packages || [];
    const emails = (site.horizon_admin_emails || []).join('\n');
    const [horizonEmails, setHorizonEmails] = useState(emails);
    return (
        <div className="space-y-6">
            <section className="panel">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div><h2 className="font-semibold heading">Failed jobs</h2><p className="mt-1 text-sm muted">Counts rows in this site's own <code>failed_jobs</code> table.</p></div>
                    <div className="flex items-center gap-4">
                        {site.queue_checked_at && <p className="text-sm heading">{site.queue_failed_count === null ? 'Unable to check' : `${site.queue_failed_count} failed`}</p>}
                        <button className="button-secondary" disabled={site.status !== 'active'} onClick={() => router.post(route('sites.queue-health', site.id))}>Check now</button>
                    </div>
                </div>
            </section>
            <section className="panel">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div><h2 className="font-semibold heading">Horizon & Reverb</h2><p className="mt-1 text-sm muted">Detected in the currently deployed release, from <code>composer show</code>.</p></div>
                    <button className="button-secondary text-xs" disabled={site.status !== 'active'} onClick={() => router.post(route('site-packages.check', site.id))}>Refresh detection</button>
                </div>
                {[['laravel/horizon', 'Horizon'], ['laravel/reverb', 'Reverb']].map(([pkg, label]) => {
                    const version = installed[pkg];
                    return (
                        <div key={pkg} className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 py-3 text-sm">
                            <div>
                                <span className={version ? 'text-emerald-600' : 'muted'}>{label} {version ? `· ${version} installed` : '· not detected'}</span>
                                {managed.includes(pkg) && <span className="badge badge-info ml-2">Kept on every deploy</span>}
                                {pkg === 'laravel/horizon' && version && <p className="mt-1 text-xs muted">Dashboard: <a className="link-action" href={`https://${site.domain}/horizon`} target="_blank" rel="noreferrer">https://{site.domain}/horizon</a></p>}
                            </div>
                            {! managed.includes(pkg)
                                ? <button className="link-action text-xs" disabled={site.status !== 'active'} onClick={() => router.post(route('site-packages.store', site.id), { package: pkg })}>{version ? 'Keep on every deploy' : 'Install'}</button>
                                : <button className="link-danger text-xs" onClick={() => router.delete(route('site-packages.destroy', site.id), { data: { package: pkg } })}>Stop keeping</button>}
                        </div>
                    );
                })}
                {installed['laravel/horizon'] && (
                    <form onSubmit={(e) => { e.preventDefault(); router.post(route('site-horizon-admins.update', site.id), { emails: horizonEmails }); }} className="mt-5 border-t border-slate-100 pt-5">
                        <label className="block text-sm heading">Horizon dashboard access<span className="mt-1 block text-xs font-normal muted">Emails of your app's own users allowed to view <code>/horizon</code>. Comma or newline separated.</span></label>
                        <textarea className="field mt-2 min-h-20 font-mono text-xs" name="emails" placeholder="admin@example.com" value={horizonEmails} onChange={(e) => setHorizonEmails(e.target.value)} />
                        <button className="button-secondary mt-3 text-xs">Save access list</button>
                    </form>
                )}
            </section>
            <section className="panel">
                <h2 className="font-semibold heading">Supervisor processes</h2>
                {(site.queue_workers || site.queueWorkers || []).map((worker: any) => (
                    <div key={worker.id} className="border-t border-slate-100 py-3 text-sm">
                        <div className="flex items-center justify-between gap-3">
                            <span className="heading">{worker.name} · {worker.type}</span>
                            <button className="link-action text-xs" onClick={() => router.post(route('workers.status', worker.id))}>Check status</button>
                        </div>
                        {worker.runtime_status && <p className="mt-1 text-xs">{`Supervisor: ${worker.runtime_status}`}</p>}
                    </div>
                ))}
                {(site.queue_workers || site.queueWorkers || []).length === 0 && <p className="py-5 text-center text-sm muted">No workers configured yet. Add one from this server's <Link className="link-action" href={`${route('servers.manage', site.server_id)}?tab=workers`}>management page</Link>.</p>}
            </section>
        </div>
    );
}

function WpInventory({ site, meta, target, plural, items: list, directory, ready }: any) {
    const { branding } = usePage<PageProps>().props;
    const [q, setQ] = useState('');
    if (! ready) return <div className="panel text-sm muted">Install WordPress before managing {plural}.</div>;
    return (
        <div className="space-y-6">
            <section className="panel">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 className="font-semibold heading">{plural === 'themes' ? (meta?.wp_installed_themes || 'Installed themes') : (meta?.wp_installed_plugins || 'Installed plugins')}</h2><p className="mt-1 text-xs muted">{site.wordpress_inventory_at ? `Read ${site.wordpress_inventory_at}` : 'Reading from the server…'}</p></div>
                    <button className="button-secondary !px-3 !py-1.5 text-xs" onClick={() => router.post(route('wordpress.refresh', site.id))}>Refresh list</button>
                </div>
                {site.wordpress_inventory_error && <p className="flash-danger mt-4 font-mono text-xs">{meta?.wp_last_read_failed || 'The last read failed'}: {site.wordpress_inventory_error}</p>}
                {(list || []).map((item: any) => {
                    const active = ['active', 'active-network'].includes(item.status);
                    const actions = [item.update === 'available' ? 'update' : null, active ? (target === 'plugin' ? 'deactivate' : null) : 'activate', active ? null : 'delete'].filter(Boolean) as string[];
                    return (
                        <div key={item.name} className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 py-3">
                            <div>
                                <div className="flex flex-wrap items-center gap-2"><p className="text-sm font-medium heading">{item.title || item.name}</p><span className={`badge ${active ? 'badge-success' : 'badge-neutral'}`}>{active ? (meta?.wp_active || 'Active') : item.status || 'inactive'}</span>{item.update === 'available' && <span className="badge badge-warning">{meta?.wp_update_available || 'Update available'}</span>}</div>
                                <p className="mt-1 font-mono text-xs muted">{item.name}{item.version ? ` · ${item.version}` : ''}</p>
                            </div>
                            <div className="flex gap-2">
                                {actions.map((action) => (
                                    <button key={action} className={`button-secondary !px-3 !py-1.5 text-xs ${action === 'delete' ? '!text-rose-600' : ''}`} onClick={() => { if (action === 'delete' && ! confirm(`Delete ${item.name} from ${site.domain}?`)) return; router.post(route('wordpress.manage', site.id), { target, action, slug: item.name }); }}>{action[0].toUpperCase() + action.slice(1)}</button>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </section>
            <section className="panel">
                <h2 className="font-semibold heading">{plural === 'themes' ? (meta?.wp_browse_themes || 'Browse themes') : (meta?.wp_browse_plugins || 'Browse plugins')}</h2>
                <form onSubmit={(e) => { e.preventDefault(); router.get(route('sites.show', site.id), { tab: plural, [`${target}_search`]: q }, { preserveState: true }); }} className="mt-3 flex gap-3">
                    <input className="field" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search wordpress.org" />
                    <button className="button-secondary">Search</button>
                </form>
                {(directory || []).map((item: any) => (
                    <div key={item.slug || item.name} className="mt-4 flex flex-wrap items-start justify-between gap-3 border-t border-slate-100 pt-4">
                        <div>
                            <p className="font-medium heading">{item.name || item.title}</p>
                            <p className="mt-1 text-xs muted">{item.author}{item.installs_label ? ` · ${item.installs_label}` : (item.installs || item.active_installs ? ` · ${item.installs || item.active_installs}` : '')}{item.short_description || item.description ? ` · ${item.short_description || item.description}` : ''}</p>
                        </div>
                        <button className="button-secondary !px-3 !py-1.5 text-xs" onClick={() => router.post(route('wordpress.manage', site.id), { target, action: 'install', slug: item.slug || item.name })}>{meta?.wp_install_activate || 'Install and activate'}</button>
                    </div>
                ))}
            </section>
        </div>
    );
}
