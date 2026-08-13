import { Link, usePage } from '@inertiajs/react';
import MarketingLayout from '../../Layouts/MarketingLayout';
import { MarketingCta } from '../../Components/MarketingCta';
import { BlogCard, BlogPostCard } from '../../Components/BlogCard';
import { route } from '../../lib/route';
import { money } from '../../lib/ui';
import { faqs, homepageFeatures, platforms, stackChips } from '../../lib/marketingContent';
import { PageProps } from '../../types';

type Plan = { id: number; name: string; monthly_price: number; yearly_price: number; currency: string; slug: string; quota_lines?: string[] };
type Step = { title: string; body: string };
type Landing = Record<string, string> & { steps?: Step[] };

export default function Home({ landing, plans, posts, managedServersEnabled, dnsEnabled, stagingSitesEnabled }: {
    landing: Landing;
    plans: Plan[];
    posts: BlogPostCard[];
    managedServersEnabled: boolean;
    dnsEnabled?: boolean;
    stagingSitesEnabled?: boolean;
}) {
    const { branding } = usePage<PageProps>().props;
    const flags = { managedServersEnabled, dnsEnabled, stagingSitesEnabled, name: branding.name };
    const featuredId = (plans.find((plan) => plan.monthly_price > 0) ?? plans[Math.min(1, Math.max(plans.length - 1, 0))])?.id;
    const steps = landing.steps?.length ? landing.steps : [
        { title: 'Connect a server', body: `Attach a VPS over SSH. ${branding.name} installs nginx, PHP, and the worker stack.` },
        { title: 'Create a Laravel, WordPress, or React site', body: 'Point a domain, pick the stack, and the console writes the vhost and release root.' },
        { title: 'Deploy, SSL, and monitor', body: 'Push from git, issue Let’s Encrypt, and roll back if a release fails.' },
    ];
    const features = homepageFeatures(flags);
    const stacks = platforms(branding.name);

    return (
        <MarketingLayout>
            <section className="landing-hero">
                <div className="landing-hero-wash" aria-hidden="true" />
                <div className="landing-hero-grid" aria-hidden="true" />
                <div className="relative mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 md:grid-cols-2 md:py-28">
                    <div className="landing-fade-up max-w-xl">
                        <p className="page-eyebrow">{landing.hero_eyebrow}</p>
                        <h1 className="mt-4 font-display text-4xl font-extrabold leading-[1.05] tracking-[-0.04em] text-slate-950 sm:text-5xl lg:text-[3.35rem] dark:text-white">{landing.hero_headline}</h1>
                        <p className="mt-5 max-w-md text-lg leading-relaxed text-slate-600 dark:text-zinc-300">{landing.hero_subcopy}</p>
                        <div className="mt-8 flex flex-wrap items-center gap-3">
                            <Link href={route('register')} className="button-primary !px-6 !py-2.5">{landing.hero_cta_primary}</Link>
                            <a href="#how-it-works" className="button-secondary !px-6 !py-2.5">{landing.hero_cta_secondary}</a>
                        </div>
                        <p className="mt-4 text-sm muted">{landing.hero_microcopy}</p>
                    </div>
                    <div className="landing-fade-up landing-fade-up-delay overflow-hidden rounded-2xl border border-slate-800/80 bg-[#111316] shadow-xl shadow-slate-900/20 dark:border-white/10">
                        <div className="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                            <span className="size-2.5 rounded-full bg-zinc-600" />
                            <span className="size-2.5 rounded-full bg-zinc-600" />
                            <span className="size-2.5 rounded-full bg-zinc-600" />
                            <span className="ml-2 font-mono text-[11px] text-zinc-500">console · production-api</span>
                            <span className="ml-auto rounded-full bg-emerald-400/15 px-2 py-0.5 text-[10px] font-medium text-emerald-300">Active</span>
                        </div>
                        <div className="grid grid-cols-[7.5rem_minmax(0,1fr)]">
                            <div className="border-r border-white/10 px-3 py-4 text-[11px] text-zinc-500">
                                <p className="mb-2 text-[10px] font-medium uppercase tracking-[0.14em] text-zinc-600">Sites</p>
                                <p className="rounded-md bg-white/[0.08] px-2 py-1.5 text-zinc-200">api</p>
                                <p className="mt-1 px-2 py-1.5">www</p>
                                <p className="px-2 py-1.5">staging</p>
                            </div>
                            <pre className="overflow-hidden p-4 font-mono text-[11px] leading-6 text-zinc-300">{`$ deploy
→ composer install
→ npm run build
→ migrate --force
→ switch live release
✓ Live in 42s`}</pre>
                        </div>
                    </div>
                </div>
            </section>

            <section className="landing-strip">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-2 px-5 py-6">
                    {stackChips(flags).map((chip) => (
                        <span key={chip} className="landing-chip">{chip}</span>
                    ))}
                </div>
            </section>

            <section id="platform" className="mx-auto max-w-7xl px-5 py-20 md:py-24">
                <p className="page-eyebrow">Platform</p>
                <h2 className="page-title">The control plane, not a teaser</h2>
                <p className="page-subtitle">Servers, sites, SSL, staging, backups, firewall, DNS, teams — the same console you use after you sign in.</p>
                <div className="landing-bento mt-10">
                    {features.map((item, i) => (
                        <div key={item.title} className={`panel ${i === 0 ? 'landing-bento-wide' : ''}`}>
                            <h3 className="font-semibold tracking-[-0.02em] heading">{item.title}</h3>
                            <p className="mt-2 text-sm leading-relaxed muted">{item.body}</p>
                        </div>
                    ))}
                </div>
                <p className="mt-6 text-sm muted">
                    <Link href={route('features')} className="link-action">Grouped feature list</Link>
                    {' · '}
                    <Link href={route('use-cases')} className="link-action">How teams use {branding.name}</Link>
                </p>
            </section>

            <section id="how-it-works" className="mx-auto max-w-7xl px-5 py-20 md:py-24">
                <p className="page-eyebrow">{landing.steps_eyebrow}</p>
                <h2 className="page-title">{landing.steps_headline}</h2>
                <p className="page-subtitle">{landing.steps_subcopy}</p>
                <div className="mt-10 grid gap-4 sm:grid-cols-3">
                    {steps.map((step, i) => (
                        <div key={step.title} className="panel">
                            <p className="landing-kicker">Step {i + 1}</p>
                            <p className="mt-3 font-semibold tracking-[-0.02em] heading">{step.title}</p>
                            {step.body && <p className="mt-2 text-sm leading-relaxed muted">{step.body}</p>}
                        </div>
                    ))}
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-5 py-20 md:py-24">
                <p className="page-eyebrow">Stacks</p>
                <h2 className="page-title">Laravel, WordPress, and React</h2>
                <p className="page-subtitle">First-class deploys for the apps you actually ship — not a generic “PHP site” box.</p>
                <div className="mt-10 grid gap-4 lg:grid-cols-3">
                    {stacks.map((stack) => (
                        <article key={stack.title} className="panel flex flex-col">
                            <p className="landing-kicker">{stack.kicker}</p>
                            <h3 className="mt-2 text-xl font-semibold tracking-[-0.03em] heading">{stack.title}</h3>
                            <p className="mt-3 text-sm leading-relaxed muted">{stack.body}</p>
                            <ul className="mt-4 space-y-2 text-sm heading">
                                {stack.points.map((point) => (
                                    <li key={point} className="flex gap-2"><span className="muted">→</span><span>{point}</span></li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </div>
            </section>

            <section id="pricing" className="mx-auto max-w-7xl px-5 py-20 md:py-24">
                <p className="page-eyebrow">Plans</p>
                <h2 className="page-title">Pricing</h2>
                <p className="page-subtitle">Start free, then scale limits as the fleet grows. Modules such as Horizon, backups, and teams follow the plan.</p>
                <div className="mt-10 grid gap-4 md:grid-cols-3">
                    {plans.map((plan) => {
                        const featured = plan.id === featuredId;
                        return (
                            <div key={plan.id} className={`panel flex flex-col ${featured ? 'plan-featured' : ''}`}>
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="font-semibold tracking-[-0.02em] heading">{plan.name}</h3>
                                    {featured && <span className="badge badge-info">Popular</span>}
                                </div>
                                <p className="mt-3 text-3xl font-semibold tracking-[-0.03em] heading">
                                    {plan.monthly_price === 0 ? 'Free' : money(plan.monthly_price, plan.currency)}
                                    {plan.monthly_price > 0 && <span className="text-sm font-normal muted">/mo</span>}
                                </p>
                                {plan.yearly_price > 0 && (
                                    <p className="mt-1 text-sm muted">{money(plan.yearly_price, plan.currency)} billed yearly</p>
                                )}
                                <ul className="mt-4 flex-1 space-y-2 text-sm muted">
                                    {(plan.quota_lines || []).map((line: string) => <li key={line}>{line}</li>)}
                                </ul>
                                <Link href={route('register')} className={`${featured ? 'button-primary' : 'button-secondary'} mt-6 inline-flex w-full`}>{featured ? (landing.hero_cta_primary || 'Get started') : 'Choose plan'}</Link>
                            </div>
                        );
                    })}
                    {plans.length === 0 && <p className="muted">Plans will appear here once published.</p>}
                </div>
                {managedServersEnabled && <p className="mt-4 text-sm muted">Managed servers are available on eligible plans.</p>}
            </section>

            {posts.length > 0 && (
                <section className="mx-auto max-w-7xl px-5 py-16">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="page-eyebrow">Journal</p>
                            <h2 className="page-title">From the blog</h2>
                        </div>
                        <Link href={route('blog')} className="link-action">View all</Link>
                    </div>
                    <div className="mt-8 grid gap-4 md:grid-cols-3">
                        {posts.map((post) => <BlogCard key={post.id} post={post} />)}
                    </div>
                </section>
            )}

            <section className="mx-auto max-w-7xl px-5 py-20 md:py-24">
                <p className="page-eyebrow">FAQ</p>
                <h2 className="page-title">Questions operators ask</h2>
                <div className="mt-10 grid gap-4 md:grid-cols-2">
                    {faqs(flags).map(([q, a]) => (
                        <div key={q} className="panel">
                            <h3 className="font-semibold tracking-[-0.02em] heading">{q}</h3>
                            <p className="mt-2 text-sm leading-relaxed muted">{a}</p>
                        </div>
                    ))}
                </div>
            </section>

            <MarketingCta landing={landing} />
        </MarketingLayout>
    );
}
