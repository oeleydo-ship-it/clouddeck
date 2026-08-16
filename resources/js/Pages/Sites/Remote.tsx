import { useEffect } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { TabBar, TabPanel, Tabs } from '../../Components/Tabs';
import { useLiveReload } from '../../lib/live';
import { route } from '../../lib/route';

const tabs = { configuration: 'PHP & Nginx', files: 'File manager', terminal: 'Command console' };

export default function Remote({ site, configurations, operations, commands, path, editor, listing, readOperation, pending }: any) {
    const tabState = Tabs({ tabs, initial: 'configuration' });
    const entries = Array.isArray(listing) ? listing : (listing?.entries || []);
    const parentPath = path === '.' ? null : (path.includes('/') ? path.split('/').slice(0, -1).join('/') || '.' : '.');
    const nginx = useForm({ type: 'nginx', client_max_body_mb: 100, static_cache: true, include_www: false, allow_iframe_embedding: false });
    const php = useForm({ type: 'php', memory_limit_mb: 256, max_children: 10, upload_max_mb: 100, post_max_mb: 100, max_execution_time: 60, display_errors: false });
    const term = useForm({ command: '' });
    const editorForm = useForm({ action: 'write', path: editor || '', content: readOperation?.result || readOperation?.payload || '', current_path: path || '.' });
    const mkdir = useForm({ action: 'mkdir', path: path === '.' ? 'new-folder' : `${path}/new-folder`, current_path: path || '.' });
    const upload = useForm({ action: 'upload', path: path || '.', file: null as File | null, current_path: path || '.' });

    useLiveReload({ active: Boolean(pending), interval: 3000 });
    useEffect(() => {
        if (readOperation?.result) {
            editorForm.setData((data) => ({ ...data, path: editor || data.path, content: String(readOperation.result) }));
        }
    }, [editor, readOperation?.id, readOperation?.result]);

    const fileAction = (action: string, extra: Record<string, unknown> = {}) => {
        router.post(route('site-files.store', site.id), { action, current_path: path || '.', ...extra });
    };

    return (
        <ConsoleLayout crumb="Remote">
            <div className="app-main">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <Link className="link-action" href={route('sites.show', site.id)}>Back to site</Link>
                        <h1 className="page-title">Remote tools</h1>
                        <p className="mt-2 text-sm muted">{site.domain} / operations execute through {site.server?.name}</p>
                    </div>
                    {pending && <span className="badge badge-info">Remote work in progress</span>}
                </div>
                <Flash />
                <TabBar tabs={tabs} tab={tabState.tab} setTab={tabState.setTab} />

                <TabPanel when="configuration" tab={tabState.tab}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <form onSubmit={(e) => { e.preventDefault(); nginx.post(route('site-configurations.store', site.id)); }} className="panel">
                            <h2 className="font-semibold">Nginx virtual host</h2>
                            <p className="mt-2 text-sm muted">Generated from constrained settings, tested before reload, and restored automatically if validation fails.</p>
                            <label className="mt-4 block text-sm">Maximum request body, MB<input className="field" type="number" min={1} max={1024} value={nginx.data.client_max_body_mb} onChange={(e) => nginx.setData('client_max_body_mb', Number(e.target.value))} /></label>
                            <label className="mt-4 flex gap-2 text-sm"><input type="checkbox" checked={nginx.data.static_cache} onChange={(e) => nginx.setData('static_cache', e.target.checked)} />Cache static assets for 30 days</label>
                            <label className="mt-3 flex gap-2 text-sm"><input type="checkbox" checked={nginx.data.include_www} onChange={(e) => nginx.setData('include_www', e.target.checked)} />Include www hostname</label>
                            <label className="mt-3 flex gap-2 text-sm"><input type="checkbox" checked={nginx.data.allow_iframe_embedding} onChange={(e) => nginx.setData('allow_iframe_embedding', e.target.checked)} />Allow embedding in iframes on other sites</label>
                            <button className="button-primary mt-5">Apply Nginx settings</button>
                        </form>
                        <form onSubmit={(e) => { e.preventDefault(); php.post(route('site-configurations.store', site.id)); }} className="panel">
                            <h2 className="font-semibold">PHP {site.php_version} FPM pool</h2>
                            <p className="mt-2 text-sm muted">A dedicated site pool runs as www-data with its own socket and bounded process limits.</p>
                            <div className="grid grid-cols-2 gap-3">
                                <label className="mt-4 block text-sm">Memory MB<input className="field" type="number" value={php.data.memory_limit_mb} onChange={(e) => php.setData('memory_limit_mb', Number(e.target.value))} /></label>
                                <label className="mt-4 block text-sm">Max children<input className="field" type="number" value={php.data.max_children} onChange={(e) => php.setData('max_children', Number(e.target.value))} /></label>
                                <label className="block text-sm">Upload MB<input className="field" type="number" value={php.data.upload_max_mb} onChange={(e) => php.setData('upload_max_mb', Number(e.target.value))} /></label>
                                <label className="block text-sm">POST MB<input className="field" type="number" value={php.data.post_max_mb} onChange={(e) => php.setData('post_max_mb', Number(e.target.value))} /></label>
                            </div>
                            <label className="mt-3 block text-sm">Execution seconds<input className="field" type="number" value={php.data.max_execution_time} onChange={(e) => php.setData('max_execution_time', Number(e.target.value))} /></label>
                            <label className="mt-4 flex gap-2 text-sm"><input type="checkbox" checked={php.data.display_errors} onChange={(e) => php.setData('display_errors', e.target.checked)} />Display errors in responses</label>
                            <button className="button-primary mt-5">Apply PHP settings</button>
                        </form>
                    </div>
                    <section className="panel mt-6">
                        <h2 className="font-semibold">Configuration revisions</h2>
                        {(configurations || []).length === 0 && <p className="py-5 text-sm muted">No managed configuration revisions.</p>}
                        {(configurations || []).map((configuration: any) => (
                            <div key={configuration.id} className="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 py-3">
                                <div>
                                    <span className="uppercase">{configuration.type}</span> v{configuration.version} <span className="ml-2 text-sm capitalize muted">{configuration.status}</span>
                                    {configuration.failure_reason && <p className="mt-1 text-xs text-rose-600">{configuration.failure_reason}</p>}
                                </div>
                                {['active', 'superseded'].includes(configuration.status) && <button className="text-sm text-amber-600" onClick={() => router.post(route('site-configurations.rollback', configuration.id))}>Restore as new revision</button>}
                            </div>
                        ))}
                    </section>
                </TabPanel>

                <TabPanel when="files" tab={tabState.tab}>
                    <section className="panel">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h2 className="font-semibold">/var/www/{site.domain}/{path === '.' ? '' : path}</h2>
                                <p className="mt-1 text-sm muted">All paths are resolved beneath this site's root, including symlink targets.</p>
                            </div>
                            <div className="flex gap-2">
                                {parentPath !== null && <Link className="button-secondary" href={`${route('sites.remote', site.id)}?path=${encodeURIComponent(parentPath)}&tab=files`}>Parent</Link>}
                                <button className="button-primary" onClick={() => fileAction('list', { path: path || '.' })}>Refresh</button>
                            </div>
                        </div>
                        <div className="mt-5 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="muted"><tr><th className="pb-3">Name</th><th>Type</th><th>Size</th><th>Permissions</th><th /></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {entries.length === 0 && <tr><td colSpan={5} className="py-8 text-center muted">Refresh to load this directory.</td></tr>}
                                    {entries.map((entry: any) => {
                                        const name = entry.name || entry;
                                        const type = entry.type || (String(name).endsWith('/') ? 'directory' : 'file');
                                        const entryPath = path === '.' ? name : `${path}/${name}`;
                                        return (
                                            <tr key={entryPath}>
                                                <td className="py-3">
                                                    {type === 'directory' || type === 'dir'
                                                        ? <Link className="link-action" href={`${route('sites.remote', site.id)}?path=${encodeURIComponent(entryPath)}&tab=files`}>{name}/</Link>
                                                        : name}
                                                </td>
                                                <td className="muted">{type}</td>
                                                <td className="muted">{type === 'file' && entry.size != null ? entry.size : '-'}</td>
                                                <td>
                                                    <form onSubmit={(e) => { e.preventDefault(); const mode = (e.currentTarget.elements.namedItem('mode') as HTMLInputElement).value; fileAction('chmod', { path: entryPath, mode }); }} className="flex items-center gap-2">
                                                        <input className="field mt-0 w-20 py-1 font-mono text-xs" name="mode" defaultValue={`0${entry.mode ?? '644'}`} pattern="0[0-7]{3}" />
                                                        <button className="link-action text-xs">Set</button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div className="flex justify-end gap-3">
                                                        {type === 'file' && (
                                                            <>
                                                                <button className="link-action" onClick={() => fileAction('read', { path: entryPath })}>Edit</button>
                                                                <button className="link-action" onClick={() => fileAction('download', { path: entryPath })}>Prepare download</button>
                                                            </>
                                                        )}
                                                        <button className="link-action" onClick={() => fileAction('zip', { path: entryPath, destination: `${entryPath}.zip` })}>Zip</button>
                                                        {String(name).endsWith('.zip') && <button className="link-action" onClick={() => fileAction('unzip', { path: entryPath, destination: path || '.' })}>Unzip</button>}
                                                        <button className="link-danger" onClick={() => { if (confirm('Delete this path from the server?')) fileAction('delete', { path: entryPath, confirmed: 1 }); }}>Delete</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <div className="mt-5 grid gap-4 lg:grid-cols-2">
                            <form onSubmit={(e) => { e.preventDefault(); mkdir.post(route('site-files.store', site.id)); }} className="flex gap-2">
                                <input className="field mt-0" value={mkdir.data.path} onChange={(e) => mkdir.setData('path', e.target.value)} placeholder="new-folder" />
                                <button className="button-secondary">Mkdir</button>
                            </form>
                            <form onSubmit={(e) => { e.preventDefault(); upload.post(route('site-files.store', site.id), { forceFormData: true }); }} className="flex gap-2">
                                <input className="field mt-0" type="file" onChange={(e) => upload.setData('file', e.target.files?.[0] || null)} />
                                <button className="button-secondary">Upload</button>
                            </form>
                        </div>
                    </section>
                    {editor && (
                        <section className="panel mt-6">
                            <h2 className="font-semibold">Edit {editor}</h2>
                            <form onSubmit={(e) => { e.preventDefault(); editorForm.setData('path', editor); editorForm.post(route('site-files.store', site.id)); }} className="mt-4">
                                <textarea className="field min-h-80 font-mono text-xs" value={editorForm.data.content} onChange={(e) => editorForm.setData('content', e.target.value)} spellCheck={false} />
                                <button className="button-primary mt-4">Save file</button>
                            </form>
                        </section>
                    )}
                    {(operations || []).some((operation: any) => operation.action === 'download' && operation.status === 'successful') && (
                        <section className="panel mt-6">
                            <h2 className="font-semibold">Prepared downloads</h2>
                            {(operations || []).filter((operation: any) => operation.action === 'download' && operation.status === 'successful').map((operation: any) => (
                                <a key={operation.id} className="mt-2 block link-action" href={route('site-files.download', operation.id)}>{operation.path}</a>
                            ))}
                        </section>
                    )}
                </TabPanel>

                <TabPanel when="terminal" tab={tabState.tab}>
                    <section className="panel">
                        <h2 className="font-semibold heading">Command console</h2>
                        <p className="mt-1 text-sm muted">Commands run as the site user on {site.server?.name}.</p>
                        <form onSubmit={(e) => { e.preventDefault(); term.post(route('terminal.store', site.id)); }} className="mt-4 flex gap-2">
                            <input className="field mt-0 font-mono" value={term.data.command} onChange={(e) => term.setData('command', e.target.value)} placeholder="ls -la" />
                            <button className="button-primary">Run command</button>
                        </form>
                        <div className="mt-4 space-y-2">
                            {(commands || []).map((cmd: any) => {
                                const commandText = cmd.command ?? cmd.command_text ?? cmd.input ?? cmd.body ?? '';
                                const outputText = cmd.output ?? cmd.stdout ?? cmd.stderr ?? '';

                                return (
                                    <pre key={cmd.id} className="log-pane max-h-64">{`$ ${commandText}\n${outputText || cmd.status || ''}`}</pre>
                                );
                            })}
                        </div>
                    </section>
                </TabPanel>
            </div>
        </ConsoleLayout>
    );
}
