import { Link, useForm, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

type Server = { id: string; name: string; public_ip?: string | null };

export default function Create({ servers, phpVersions, defaultPhpVersion }: { servers: Server[]; phpVersions: string[]; defaultPhpVersion: string }) {
    const form = useForm({
        platform: 'laravel',
        server_id: '',
        domain: '',
        php_version: defaultPhpVersion,
        repository_url: '',
        branch: 'main',
        auto_deploy: false,
        zero_downtime: true,
    });
    const { errors } = usePage<PageProps>().props;
    const platforms = [
        ['laravel', 'Laravel', 'Deployed from your Git repository, with Composer, migrations, and asset builds.'],
        ['wordpress', 'WordPress', 'Downloaded from wordpress.org and configured for you. No repository needed.'],
        ['react', 'React', 'Vite SPA from Git. Built with npm and served as static files by Nginx.'],
    ] as const;

    return (
        <ConsoleLayout crumb="Create a site">
            <div className="app-main !max-w-3xl">
                <p className="page-eyebrow">New application</p>
                <h1 className="page-title">Create a site</h1>
                <p className="mt-2 muted">Configures Nginx and prepares the release directories in the background.</p>
                <Flash />
                {servers.length === 0 && (
                    <div className="flash-warning mt-5">
                        No ready servers are connected. <Link className="link-action" href={route('servers.custom')}>Attach an existing server</Link> or <Link className="link-action" href={route('servers.create')}>provision a new one</Link>, then wait for the bootstrap to finish.
                    </div>
                )}
                <form onSubmit={(e) => { e.preventDefault(); form.post(route('sites.store')); }} className="panel mt-8">
                    <fieldset>
                        <legend className="text-sm font-medium heading">Platform</legend>
                        <div className="mt-3 grid gap-3 sm:grid-cols-3">
                            {platforms.map(([value, label, description]) => (
                                <label key={value} className={`choice-card !items-start ${form.data.platform === value ? 'choice-card-active' : ''}`}>
                                    <input type="radio" name="platform" value={value} checked={form.data.platform === value} onChange={() => form.setData('platform', value)} className="mt-0.5" />
                                    <span><span className="block text-sm font-medium heading">{label}</span><span className="mt-1 block text-xs muted">{description}</span></span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <div className="mt-6 grid gap-5 sm:grid-cols-2">
                        <label className="text-sm heading sm:col-span-2">Server
                            <select className="field" name="server_id" value={form.data.server_id} onChange={(e) => form.setData('server_id', e.target.value)} required>
                                <option value="">Select a ready server</option>
                                {servers.map((server) => <option key={server.id} value={server.id}>{server.name} / {server.public_ip}</option>)}
                            </select>
                        </label>
                        <label className="text-sm heading">Domain<input className="field" name="domain" value={form.data.domain} onChange={(e) => form.setData('domain', e.target.value)} placeholder="app.example.com" required /></label>
                        {form.data.platform !== 'react' && (
                            <label className="text-sm heading">PHP version
                                <select className="field" name="php_version" value={form.data.php_version} onChange={(e) => form.setData('php_version', e.target.value)}>
                                    {phpVersions.map((version) => <option key={version} value={version}>{version}</option>)}
                                </select>
                            </label>
                        )}
                    </div>
                    {(form.data.platform === 'laravel' || form.data.platform === 'react') && (
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label className="text-sm heading sm:col-span-2">Git repository
                                <input className="field" name="repository_url" value={form.data.repository_url} onChange={(e) => form.setData('repository_url', e.target.value)} placeholder="https://gitlab.com/acme/app.git" />
                                <span className="mt-1 block text-xs muted">GitHub, GitLab, or Bitbucket — HTTPS or SSH.</span>
                            </label>
                            <label className="text-sm heading">Branch<input className="field" name="branch" value={form.data.branch} onChange={(e) => form.setData('branch', e.target.value)} /></label>
                            <div className="flex items-end gap-5 pb-3">
                                <label className="flex gap-2 text-sm heading"><input type="checkbox" name="auto_deploy" checked={form.data.auto_deploy} onChange={(e) => form.setData('auto_deploy', e.target.checked)} />Auto deploy</label>
                                <label className="flex gap-2 text-sm heading"><input type="checkbox" name="zero_downtime" checked={form.data.zero_downtime} onChange={(e) => form.setData('zero_downtime', e.target.checked)} />Zero downtime</label>
                            </div>
                        </div>
                    )}
                    {form.data.platform === 'react' && <p className="mt-5 text-sm muted">The site is built with <code>npm run build</code> and served from <code>dist/</code> (or <code>build/</code>). Add <code>VITE_*</code> keys on the Environment tab so they are available at build time. No database is required.</p>}
                    {form.data.platform === 'wordpress' && (
                        <div className="well mt-5">
                            <p className="text-sm heading">WordPress is installed for you</p>
                            <p className="mt-1 text-sm muted">Deploying downloads the latest release, writes <code>wp-config.php</code>, and keeps <code>wp-content</code> outside the release. Create a database and attach it before deploying, then finish the famous five-minute install in the browser.</p>
                        </div>
                    )}
                    {errors.platform && <p className="mt-4 text-sm text-rose-600">{errors.platform}</p>}
                    <button className="button-primary mt-6" disabled={form.processing || servers.length === 0}>Create site</button>
                </form>
            </div>
        </ConsoleLayout>
    );
}
