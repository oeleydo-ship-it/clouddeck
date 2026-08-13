import { Link, usePage } from '@inertiajs/react';
import MarketingLayout from '../../Layouts/MarketingLayout';
import { MarketingCta } from '../../Components/MarketingCta';
import { MarketingHero } from '../../Components/MarketingHero';
import { route } from '../../lib/route';
import { PageProps } from '../../types';

type Landing = Record<string, string>;

export default function UseCases({ heading, landing, managedServersEnabled, stagingSitesEnabled }: {
    heading?: string;
    landing?: Landing;
    managedServersEnabled?: boolean;
    stagingSitesEnabled?: boolean;
}) {
    const { branding } = usePage<PageProps>().props;
    const name = branding.name;

    const cases = [
        {
            title: 'Agencies',
            kicker: 'Client fleets',
            body: `Run Laravel, WordPress, and React for multiple clients from one ${name} account. Invite the people who need deploy access, keep SSH keys and logs per site, and stop sharing a root password in a spreadsheet.`,
            points: [
                'Team roles instead of a shared SSH user',
                'Per-site deploy history you can show a client',
                'BYOS so each client keeps their own DigitalOcean or Hetzner bill',
                ...(managedServersEnabled ? ['Managed servers when a client has no cloud account yet'] : []),
            ],
        },
        {
            title: 'Laravel apps',
            kicker: 'App platform',
            body: 'Forge-style workflow on Ubuntu you already pay for: git deploys, zero-downtime releases, env files, migrations, then Horizon and queue workers when the plan allows.',
            points: [
                'Composer + npm in the release',
                'Horizon, Reverb, and Redis workers as plan modules',
                'Rollback to the last good release in one click',
                'Webhooks so a push ships without opening the console',
            ],
        },
        {
            title: 'WordPress sites',
            kicker: 'Not shared hosting',
            body: 'Each WordPress site gets its own nginx vhost and release root — not a crowded shared box. WP-CLI, backups, and SSL live next to the deploy.',
            points: [
                'Isolated vhost per site',
                'Application and database backups from the console',
                'Let’s Encrypt or a custom PEM without a cPanel maze',
                'Force HTTPS and auto-renew on the site page',
            ],
        },
        {
            title: 'React SPAs',
            kicker: 'Vite on your VPS',
            body: 'Build the frontend in the release, point nginx at the static root, and keep a Laravel API on the same host. Same SSL, webhook, and rollback path as every other site.',
            points: [
                'npm / Vite build as part of deploy',
                'Static root + API on one Ubuntu server',
                'Own the CDN story — or skip it',
                'Git push or console redeploy',
            ],
        },
        {
            title: 'Staging workflows',
            kicker: 'Promote, don’t FTP',
            body: stagingSitesEnabled
                ? 'A linked staging site on the same server with its own vhost and env. When it looks right, promote repository, branch, deploy script, and PHP version to production.'
                : `Staging is a second site on the same host — not a hope-and-copy. ${name} can enable linked staging and promote-to-production when the platform allows it.`,
            points: [
                'Own nginx vhost and environment on staging',
                'Promote repo, branch, script, and PHP version',
                'Production deploy queued after promote',
                'Same SSL and rollback tools as production',
            ],
        },
    ];

    return (
        <MarketingLayout>
            <MarketingHero
                eyebrow={heading || 'Use cases'}
                title="Built for operators"
                subtitle={`The same console whether you run one VPS or a client fleet. ${name} is for people who already know Laravel, WordPress, or React.`}
            />

            <section className="mx-auto max-w-7xl px-5 py-8 pb-16">
                <div className="grid gap-4 lg:grid-cols-2">
                    {cases.map((item) => (
                        <article key={item.title} className="panel flex flex-col">
                            <p className="landing-kicker">{item.kicker}</p>
                            <h2 className="mt-2 text-xl font-semibold tracking-[-0.03em] heading">{item.title}</h2>
                            <p className="mt-3 text-sm leading-relaxed muted">{item.body}</p>
                            <ul className="mt-4 flex-1 space-y-2 text-sm heading">
                                {item.points.map((point) => (
                                    <li key={point} className="flex gap-2"><span className="muted">→</span><span>{point}</span></li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </div>
                <p className="mt-10 text-sm muted">
                    See the <Link href={route('features')} className="link-action">full feature list</Link>
                    {' '}or <Link href={route('home') + '#pricing'} className="link-action">pricing</Link>.
                </p>
            </section>

            <MarketingCta landing={landing} />
        </MarketingLayout>
    );
}
