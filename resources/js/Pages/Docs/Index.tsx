import { Link, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

export default function Index({ managedServersEnabled, stagingSitesEnabled }: { managedServersEnabled: boolean; stagingSitesEnabled: boolean }) {
    const { branding } = usePage<PageProps>().props;
    const sections = [
        ['getting-started', 'Getting started'],
        ['whats-new', "What's new"],
        ...(managedServersEnabled ? [['managed-servers', 'Managed servers'] as const] : []),
        ['providers', 'Providers & IPs'],
        ['ssh-keys', 'SSH keys'],
        ['provisioning', 'Provisioning (BYOS)'],
        ['sites', 'Adding a site'],
        ['deployments', 'Deployments'],
        ['ssl', 'SSL certificates'],
        ['databases', 'Databases'],
        ['workers', 'Workers & cron'],
        ['monitoring', 'Monitoring'],
        ['security-detection', 'Security detection'],
        ['notifications', 'Notifications'],
        ['firewall', 'Firewall'],
        ['backups', 'Backups'],
        ...(stagingSitesEnabled ? [['staging', 'Staging sites'] as const] : []),
        ['remote', 'Remote management'],
        ['maintenance', 'Server maintenance'],
        ['dns', 'DNS'],
        ['teams', 'Teams & API'],
        ['plans', 'Plans & billing'],
        ['password', 'Account security'],
        ['support', 'Getting help'],
    ];

    return (
        <ConsoleLayout crumb="Documentation">
            <div className="app-main !max-w-6xl">
                <p className="page-eyebrow">Help center</p>
                <h1 className="page-title">Support & documentation</h1>
                <p className="page-subtitle">{branding.name} is a SaaS control plane. Connect your cloud account or VPS, auto-provision servers, deploy Laravel, WordPress, and React sites, and operate them from one dashboard.</p>
                <div className="mt-10 grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <nav className="panel h-fit lg:sticky lg:top-20" aria-label="Documentation">
                        <p className="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] muted">On this page</p>
                        <ul className="space-y-0.5">{sections.map(([id, label]) => <li key={id}><a href={`#${id}`} className="nav-link !block !px-2.5 !py-1.5">{label}</a></li>)}</ul>
                        <Link href={route('contact')} className="button-secondary mt-5 w-full justify-center text-sm">Contact support</Link>
                    </nav>
                    <div className="docs-body min-w-0 space-y-6">
                        <section className="panel space-y-8">
                            <div id="getting-started" className="scroll-mt-24"><h2 className="section-title">Getting started</h2><p className="mt-3 text-sm muted">Create an account, provision or attach a server, then add a Laravel, WordPress, or React site.</p></div>
                            <div id="whats-new" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">What's new</h2><p className="mt-3 text-sm muted">React/Vite SPA deploys, Let’s Encrypt uninstall + custom SSL, and the Inertia console.</p></div>
                            {managedServersEnabled && <div id="managed-servers" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Managed servers</h2><p className="mt-3 text-sm muted">Provision a VPS through {branding.name} without connecting your own cloud token. Paid sizes check out via Stripe first.</p></div>}
                            <div id="providers" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Providers & IPs</h2><p className="mt-3 text-sm muted">Connect DigitalOcean to create Droplets, or attach any Ubuntu VPS by IP over SSH.</p></div>
                            <div id="ssh-keys" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">SSH keys</h2><p className="mt-3 text-sm muted">Generate a key pair or upload a public key before provisioning.</p></div>
                            <div id="provisioning" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Provisioning (BYOS)</h2><p className="mt-3 text-sm muted">BYOS creates a Droplet on your account, then bootstraps Nginx, PHP-FPM, Redis, and the monitoring agent.</p></div>
                        </section>
                        <section className="panel space-y-8">
                            <div id="sites" className="scroll-mt-24">
                                <h2 className="section-title">Adding a site</h2>
                                <h3 className="mt-6 text-sm font-semibold heading">Laravel sites</h3>
                                <p className="mt-2 text-sm muted">Git repository, Composer, migrations, zero-downtime releases, PHP 8.2–8.5.</p>
                                <h3 className="mt-6 text-sm font-semibold heading">React sites</h3>
                                <ol className="mt-2 list-decimal space-y-2 pl-5 text-sm muted">
                                    <li>Choose the <strong className="heading">React</strong> platform (Vite SPA).</li>
                                    <li>Select a ready server, enter the domain, and provide the Git repository and branch.</li>
                                    <li>No PHP version or database is required. Add <code>VITE_*</code> keys on the Environment tab so they are available at build time.</li>
                                    <li>Deploy clones the repo, runs <code>npm ci</code> and <code>npm run build</code>, then Nginx serves <code>current/dist</code> with an <code>index.html</code> SPA fallback.</li>
                                    <li>If the build writes to <code>build/</code> instead of <code>dist/</code>, the platform links it so the document root stays <code>current/dist</code>.</li>
                                </ol>
                                <h3 className="mt-6 text-sm font-semibold heading">WordPress sites</h3>
                                <p className="mt-2 text-sm muted">Downloaded from wordpress.org. Attach a database, deploy, then finish wp-admin/install.php.</p>
                            </div>
                            <div id="deployments" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Deployments</h2><p className="mt-3 text-sm muted">Zero-downtime releases, rollbacks, live logs, and Git webhooks.</p></div>
                            <div id="ssl" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">SSL certificates</h2><p className="mt-3 text-sm muted">Issue Let’s Encrypt, upload a custom PEM, or remove SSL to serve HTTP again.</p></div>
                        </section>
                        <section className="panel space-y-8">
                            <div id="databases" className="scroll-mt-24"><h2 className="section-title">Databases</h2><p className="mt-3 text-sm muted">MySQL and PostgreSQL on the server, attachable to a site. phpMyAdmin optional.</p></div>
                            <div id="workers" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Workers & cron</h2><p className="mt-3 text-sm muted">Horizon, queue workers, Reverb, and the Laravel scheduler.</p></div>
                            <div id="monitoring" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Monitoring</h2><p className="mt-3 text-sm muted">Metric agent, uptime and DNS probes, alert rules.</p></div>
                            <div id="security-detection" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Security detection</h2><p className="mt-3 text-sm muted">Scan servers for common probes and block offending IPs.</p></div>
                            <div id="notifications" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Notifications</h2><p className="mt-3 text-sm muted">In-app bell plus optional email recipients.</p></div>
                            <div id="firewall" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Firewall</h2><p className="mt-3 text-sm muted">UFW rules synced from the console.</p></div>
                            <div id="backups" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Backups</h2><p className="mt-3 text-sm muted">Full site archives, database dumps, and provider OS snapshots.</p></div>
                            {stagingSitesEnabled && <div id="staging" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Staging sites</h2><p className="mt-3 text-sm muted">Linked staging domain on the same server, then promote to production.</p></div>}
                            <div id="remote" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Remote management</h2><p className="mt-3 text-sm muted">Edit Nginx/PHP, browse files, and run commands without SSH.</p></div>
                            <div id="maintenance" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Server maintenance</h2><p className="mt-3 text-sm muted">Package updates, release upgrades, and software hardening.</p></div>
                            <div id="dns" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">DNS</h2><p className="mt-3 text-sm muted">Cloudflare token, zones, and records.</p></div>
                        </section>
                        <section className="panel space-y-8">
                            <div id="teams" className="scroll-mt-24"><h2 className="section-title">Teams & API</h2><p className="mt-3 text-sm muted">Invite operators or read-only members. Create API tokens from Account.</p></div>
                            <div id="plans" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Plans & billing</h2><p className="mt-3 text-sm muted">Subscribe via Stripe or request a plan for manual approval.</p></div>
                            <div id="password" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Account security</h2><p className="mt-3 text-sm muted">Password, 2FA, sessions, and API tokens.</p></div>
                            <div id="support" className="scroll-mt-24 border-t border-slate-100 pt-8 dark:border-white/5"><h2 className="section-title">Getting help</h2><p className="mt-3 text-sm muted">Use Contact support or email the address shown in the footer.</p></div>
                        </section>
                    </div>
                </div>
            </div>
        </ConsoleLayout>
    );
}
