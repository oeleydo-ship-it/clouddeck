import { Link, usePage } from '@inertiajs/react';
import MarketingLayout from '../../Layouts/MarketingLayout';
import { MarketingCta } from '../../Components/MarketingCta';
import { MarketingHero } from '../../Components/MarketingHero';
import { route } from '../../lib/route';
import { PageProps } from '../../types';

type Landing = Record<string, string>;

export default function About({ managedServersEnabled, heading, landing }: { managedServersEnabled: boolean; heading?: string; landing?: Landing }) {
    const { branding } = usePage<PageProps>().props;
    const name = branding.name;

    const values = [
        ['Infrastructure you own', managedServersEnabled
            ? `Connect a VPS you already pay for, or provision a managed size when the platform offers it. Either way, ${name} is the control plane — not a lock-in host.`
            : `Your cloud bill stays with DigitalOcean, Hetzner, or whoever you chose. ${name} never resells the VPS.`],
        ['Stacks people actually ship', 'Laravel, WordPress, and React/Vite get first-class deploys — composer, WP-CLI, and npm builds — instead of a generic “PHP app” box.'],
        ['Operations in one console', 'SSL, firewall, backups, monitoring, DNS, remote files, nginx, and team access sit next to the site they belong to.'],
        ['Calm over clutter', 'The console is built for operators who already know their stack. Defaults are sensible; the rest stays out of the way until you need it.'],
    ];

    const stack = [
        ['Control plane', `${name} itself is a Laravel 12 app (Inertia + React). You run the panel; it talks to your servers over SSH.`],
        ['On the VPS', 'Ubuntu hosts with nginx and PHP-FPM. Each site gets its own vhost, release root, and environment — not a shared-hosting tree.'],
        ['Deploys', 'Git into a new release directory, build, then atomically switch live. Rollback is the previous release, not a hope.'],
        ['Certificates', 'Let’s Encrypt or a custom PEM. Force HTTPS, auto-renew, or remove SSL from the site page.'],
    ];

    return (
        <MarketingLayout>
            <MarketingHero
                eyebrow={heading || 'About'}
                title={name}
                subtitle={`${name} is a Laravel control plane for Ubuntu servers you own — provision a host, deploy Laravel, WordPress, and React, and run day-to-day ops without giving up the VPS.`}
            />

            <section className="mx-auto max-w-7xl px-5 py-16">
                <div className="grid gap-10 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] lg:items-start">
                    <div>
                        <p className="page-eyebrow">Story</p>
                        <h2 className="page-title">Built for people who already have a server</h2>
                        <div className="mt-6 space-y-4 text-[15px] leading-relaxed text-slate-600 dark:text-zinc-300">
                            <p>
                                Shared hosting is too small. A raw VPS is enough compute — and then you spend the week writing nginx, deploy scripts, and certificate cron.
                                {` ${name} `}
                                sits in that gap: a SaaS panel that talks to <em>your</em> Ubuntu machines over SSH.
                            </p>
                            <p>
                                Create a site, connect the repo, and a release is built into its own directory, then switched live. If it fails, roll back. SSL, firewall rules, databases, cron, and uptime checks live on the same page as the deploy log.
                            </p>
                            {managedServersEnabled && (
                                <p>
                                    When you do not want to bring a cloud account, eligible plans can provision a managed server from the platform catalog. You still see the same console — the difference is who created the VPS.
                                </p>
                            )}
                        </div>
                    </div>
                    <aside className="panel space-y-4">
                        <h3 className="font-semibold tracking-[-0.02em] heading">What {name} is not</h3>
                        <ul className="space-y-3 text-sm leading-relaxed muted">
                            <li>Not shared hosting — each site gets its own nginx vhost and release root.</li>
                            <li>Not a PaaS that hides the box. You can SSH in whenever you need to.</li>
                            {! managedServersEnabled && <li>Not a cloud reseller. Attach the VPS you already pay for.</li>}
                            <li>Not a page builder. You bring Laravel, WordPress, or a React app.</li>
                        </ul>
                        <div className="flex flex-wrap gap-2 pt-2">
                            <Link href={route('features')} className="button-secondary">Features</Link>
                            <Link href={route('use-cases')} className="button-secondary">Use cases</Link>
                        </div>
                    </aside>
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-5 pb-8">
                <p className="page-eyebrow">Stack honesty</p>
                <h2 className="page-title">What actually runs</h2>
                <p className="page-subtitle">No mystery runtime. The panel is Laravel; the servers are Ubuntu.</p>
                <div className="mt-8 grid gap-4 sm:grid-cols-2">
                    {stack.map(([title, body]) => (
                        <div key={title} className="panel">
                            <h3 className="font-semibold tracking-[-0.02em] heading">{title}</h3>
                            <p className="mt-2 text-sm leading-relaxed muted">{body}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-5 py-16">
                <p className="page-eyebrow">Principles</p>
                <h2 className="page-title">How we decide what ships</h2>
                <div className="mt-8 grid gap-4 sm:grid-cols-2">
                    {values.map(([title, body]) => (
                        <div key={title} className="panel">
                            <h3 className="font-semibold tracking-[-0.02em] heading">{title}</h3>
                            <p className="mt-2 text-sm leading-relaxed muted">{body}</p>
                        </div>
                    ))}
                </div>
            </section>

            <MarketingCta landing={landing} />
        </MarketingLayout>
    );
}
