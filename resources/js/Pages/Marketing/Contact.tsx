import { useForm, usePage } from '@inertiajs/react';
import MarketingLayout from '../../Layouts/MarketingLayout';
import { MarketingCta } from '../../Components/MarketingCta';
import { MarketingHero } from '../../Components/MarketingHero';
import { route } from '../../lib/route';
import { PageProps } from '../../types';

type Landing = Record<string, string>;

export default function Contact({ heading, landing, supportEmail }: { heading?: string; landing?: Landing; supportEmail?: string | null }) {
    const { branding, supportEmail: sharedEmail, auth } = usePage<PageProps>().props;
    const email = supportEmail || sharedEmail;
    const form = useForm({ name: '', email: '', subject: '', body: '' });

    return (
        <MarketingLayout>
            <MarketingHero
                eyebrow="Support"
                title="Contact"
                subtitle={heading || 'Send us a message.'}
            />

            <section className="mx-auto grid max-w-7xl gap-8 px-5 pb-16 lg:grid-cols-[minmax(0,1.15fr)_minmax(16rem,0.75fr)]">
                <form onSubmit={(e) => { e.preventDefault(); form.post(route('contact.submit')); }} className="panel space-y-4">
                    <label className="field-label">Name
                        <input className="field" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        {form.errors.name && <span className="field-hint !text-rose-600">{form.errors.name}</span>}
                    </label>
                    <label className="field-label">Email
                        <input className="field" type="email" name="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                        {form.errors.email && <span className="field-hint !text-rose-600">{form.errors.email}</span>}
                    </label>
                    <label className="field-label">Subject
                        <input className="field" name="subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                    </label>
                    <label className="field-label">Message
                        <textarea className="field min-h-40" name="body" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required />
                        {form.errors.body && <span className="field-hint !text-rose-600">{form.errors.body}</span>}
                    </label>
                    <button className="button-primary" disabled={form.processing}>Send message</button>
                </form>
                <aside className="panel h-fit space-y-4">
                    <h2 className="section-title">Reach {branding.name}</h2>
                    <p className="text-sm leading-relaxed muted">
                        Billing, onboarding, deploys, and product questions all land here. We reply to the address you enter.
                    </p>
                    {email && (
                        <p className="text-sm heading">
                            Email{' '}
                            <a className="link-action" href={`mailto:${email}`}>{email}</a>
                        </p>
                    )}
                    <ul className="space-y-2 text-sm muted">
                        <li>Servers, sites, SSL, and staging</li>
                        <li>Plans, invoices, and managed sizes</li>
                        <li>Teams and operator access</li>
                    </ul>
                    {auth.user ? (
                        <p className="text-sm muted">
                            Signed-in operators also have <a href={route('docs')} className="link-action">documentation</a> in the console.
                        </p>
                    ) : (
                        <p className="text-sm muted">Looking for a walkthrough? Create an account and open documentation from the console.</p>
                    )}
                </aside>
            </section>

            <MarketingCta landing={landing} />
        </MarketingLayout>
    );
}
