import { Link } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { route } from '../../lib/route';

export default function Upgrade({ label, billingHref }: { label: string; billingHref?: string }) {
    return (
        <ConsoleLayout crumb="Plan upgrade">
            <div className="app-main">
                <div className="mx-auto max-w-lg py-10 text-center sm:py-16">
                    <p className="page-eyebrow">Plan upgrade</p>
                    <h1 className="page-title mt-2">{label} isn’t on your plan</h1>
                    <p className="page-subtitle mx-auto mt-3 max-w-md">Your current subscription doesn’t include this module. Subscribe or upgrade to unlock it — quotas and features for each plan are listed on Billing.</p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Link href={billingHref || route('billing.index')} className="button-primary">View plans & upgrade</Link>
                        <Link href={route('dashboard')} className="button-secondary">Go back</Link>
                    </div>
                </div>
            </div>
        </ConsoleLayout>
    );
}
