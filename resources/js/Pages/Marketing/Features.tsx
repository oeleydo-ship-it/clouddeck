import { Link, usePage } from '@inertiajs/react';
import MarketingLayout from '../../Layouts/MarketingLayout';
import { MarketingCta } from '../../Components/MarketingCta';
import { MarketingHero } from '../../Components/MarketingHero';
import { route } from '../../lib/route';
import { featureGroups } from '../../lib/marketingContent';
import { PageProps } from '../../types';

type Landing = Record<string, string>;

export default function Features({ managedServersEnabled, heading, landing, dnsEnabled, stagingSitesEnabled }: {
    managedServersEnabled: boolean;
    heading?: string;
    landing?: Landing;
    dnsEnabled?: boolean;
    stagingSitesEnabled?: boolean;
}) {
    const { branding, dnsEnabled: sharedDns } = usePage<PageProps>().props;
    const groups = featureGroups({
        managedServersEnabled,
        dnsEnabled: dnsEnabled ?? sharedDns,
        stagingSitesEnabled,
        name: branding.name,
    });

    return (
        <MarketingLayout>
            <MarketingHero
                eyebrow="Product"
                title={heading || 'Features'}
                subtitle={`${branding.name} is a Laravel control plane for Ubuntu servers you own — every deploy, certificate, backup, and firewall rule in one console.`}
            />

            <div className="mx-auto max-w-7xl space-y-16 px-5 pb-8 md:space-y-20">
                {groups.map((group) => (
                    <section key={group.title}>
                        <p className="page-eyebrow">{group.title}</p>
                        <div className="landing-bento mt-6">
                            {group.items.map((item) => (
                                <div key={item.title} className="panel">
                                    <h2 className="font-semibold tracking-[-0.02em] heading">{item.title}</h2>
                                    <p className="mt-2 text-sm leading-relaxed muted">{item.body}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                ))}
                <p className="text-sm muted">
                    Limits and modules depend on the plan. See <Link href={route('home') + '#pricing'} className="link-action">pricing</Link>
                    {' '}or <Link href={route('use-cases')} className="link-action">use cases</Link>.
                </p>
            </div>

            <MarketingCta landing={landing} />
        </MarketingLayout>
    );
}
