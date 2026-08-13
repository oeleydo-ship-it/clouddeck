import { Link } from '@inertiajs/react';
import { route } from '../lib/route';

type Landing = {
    cta_headline?: string;
    cta_subcopy?: string;
    cta_button?: string;
};

export function MarketingCta({ landing }: { landing?: Landing | null }) {
    if (! landing?.cta_headline) {
        return null;
    }

    return (
        <section className="landing-cta-wash">
            <div className="mx-auto max-w-7xl px-5 py-20">
                <div className="panel px-8 py-12 text-center">
                    <h2 className="page-title !mt-0">{landing.cta_headline}</h2>
                    {landing.cta_subcopy && <p className="page-subtitle mx-auto">{landing.cta_subcopy}</p>}
                    <Link href={route('register')} className="button-primary mt-6 inline-flex !px-6 !py-2.5">{landing.cta_button || 'Create free account'}</Link>
                </div>
            </div>
        </section>
    );
}
