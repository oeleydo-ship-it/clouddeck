import { Link, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

type DocSection = {
    id: string;
    title: string;
    html: string;
};

export default function Index({ sections }: { sections: DocSection[] }) {
    const { branding } = usePage<PageProps>().props;

    return (
        <ConsoleLayout crumb="Documentation">
            <div className="app-main !max-w-6xl">
                <p className="page-eyebrow">Help center</p>
                <h1 className="page-title">Support & documentation</h1>
                <p className="page-subtitle">
                    {branding.name} is a SaaS control plane. Connect your cloud account or VPS, auto-provision servers, deploy Laravel, WordPress, and React sites, and operate them from one dashboard.
                </p>
                <div className="mt-10 grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <nav className="panel h-fit lg:sticky lg:top-20" aria-label="Documentation">
                        <p className="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] muted">On this page</p>
                        <ul className="space-y-0.5">
                            {sections.map((section) => (
                                <li key={section.id}>
                                    <a href={`#${section.id}`} className="nav-link !block !px-2.5 !py-1.5">
                                        {section.title}
                                    </a>
                                </li>
                            ))}
                        </ul>
                        <Link href={route('contact')} className="button-secondary mt-5 w-full justify-center text-sm">
                            Contact support
                        </Link>
                    </nav>
                    <div className="docs-body min-w-0 space-y-6">
                        {sections.map((section, index) => (
                            <section key={section.id} id={section.id} className={`panel docs-prose scroll-mt-24 ${index > 0 ? '' : ''}`}>
                                <div dangerouslySetInnerHTML={{ __html: section.html }} />
                            </section>
                        ))}
                    </div>
                </div>
            </div>
        </ConsoleLayout>
    );
}
