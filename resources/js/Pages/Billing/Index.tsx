import { Link, router, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { PageProps } from '../../types';
import { route } from '../../lib/route';
import { money } from '../../lib/ui';

export default function Index({ plan, subscription, plans, usage, requests, stripeEnabled, empty, osBackupAddonActive, osBackupAddonGb, osBackupTitle, osBackupCta, checkoutLabel, requestLabel, currentPlanLabel }: any) {
    usePage<PageProps>();
    return (
        <ConsoleLayout crumb="Billing">
            <div className="app-main">
                <h1 className="page-title">Billing</h1>
                <p className="page-subtitle">{currentPlanLabel || 'Current plan:'} {plan?.name || 'None'}</p>
                <Flash />
                <div className="mt-6 grid gap-3 sm:grid-cols-3">
                    {Object.entries(usage || {}).map(([key, row]: any) => (
                        <div key={key} className="stat-card"><p className="stat-label capitalize">{key.replace('_', ' ')}</p><p className="stat-value mt-2">{row.label || `${row.used} / ${row.limit < 0 ? 'Unlimited' : row.limit}`}</p></div>
                    ))}
                </div>
                <div className="mt-8 grid gap-4 md:grid-cols-3">
                    {(plans || []).length === 0 && <p className="muted">{empty || 'No plans are available yet'}</p>}
                    {(plans || []).map((item: any) => (
                        <div key={item.id} className={`panel ${plan?.id === item.id ? 'plan-featured' : ''}`}>
                            <h2 className="font-semibold heading">{item.name}</h2>
                            <p className="mt-2 text-2xl font-bold">
                                {item.monthly_price === 0 ? 'Free' : money(item.monthly_price, item.currency)}
                                {item.monthly_price > 0 && <span className="text-sm muted">/mo</span>}
                            </p>
                            {item.yearly_price > 0 && <p className="mt-1 text-sm muted">or {money(item.yearly_price, item.currency)} billed yearly</p>}
                            {item.unlimited && <p className="mt-2 text-sm">{item.unlimited}</p>}
                            <ul className="mt-3 space-y-1 text-sm muted">
                                {(item.quota_lines || []).map((line: string) => <li key={line}>{line}</li>)}
                                {(item.feature_labels || []).map((label: string) => <li key={label}>{label}</li>)}
                            </ul>
                            {stripeEnabled
                                ? <button className="button-primary mt-4" onClick={() => router.post(route('billing.checkout'), { plan_id: item.id, billing_cycle: 'monthly' })}>{checkoutLabel || 'Pay & subscribe'}</button>
                                : <button className="button-secondary mt-4" onClick={() => router.post(route('billing.request'), { plan_id: item.id, billing_cycle: 'monthly' })}>{requestLabel || 'Request this plan'}</button>}
                        </div>
                    ))}
                </div>
                {(requests || []).length > 0 && (
                    <section className="panel mt-8"><h2 className="font-semibold">Requests</h2>{requests.map((r: any) => <p key={r.id} className="mt-2 text-sm">{r.plan?.name} · {r.status}</p>)}</section>
                )}
                <section className="panel mt-8">
                    <h2 className="font-semibold heading">{osBackupTitle || 'OS backup storage'}</h2>
                    <p className="mt-2 text-sm muted">{osBackupAddonActive ? `${osBackupAddonGb} GB add-on active.` : 'Buy extra provider snapshot capacity billed through Stripe.'}</p>
                    {stripeEnabled && ! osBackupAddonActive && (
                        <form action={route('billing.os-backup')} method="post" onSubmit={(e) => { e.preventDefault(); const form = e.currentTarget; const gigabytes = Number((form.elements.namedItem('gigabytes') as HTMLInputElement)?.value || 50); router.post(route('billing.os-backup'), { gigabytes }); }} className="mt-4 flex flex-wrap items-end gap-3">
                            <label className="text-sm">Gigabytes<input className="field mt-1 w-28" type="number" name="gigabytes" min={1} max={10000} defaultValue={50} /></label>
                            <button className="button-primary">{osBackupCta || 'Buy with Stripe'}</button>
                        </form>
                    )}
                    {stripeEnabled && subscription && (
                        <button className="button-secondary mt-4" onClick={() => router.post(route('billing.portal'))}>Open Stripe portal</button>
                    )}
                </section>
            </div>
        </ConsoleLayout>
    );
}
