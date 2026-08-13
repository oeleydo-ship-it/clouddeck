import { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

export default function Managed({ keys, defaultKeyId, regions, sizes, images, catalogError, stripeEnabled, platform }: any) {
    const form = useForm({
        region: regions[0]?.slug || '',
        size: sizes[0]?.slug || '',
        image: images.find((item: any) => item.slug === 'ubuntu-24-04-x64')?.slug || images[0]?.slug || '',
        ssh_key_id: defaultKeyId || keys[0]?.id || '',
        name: '',
        hostname: '',
    });
    const [step, setStep] = useState(1);
    const { errors } = usePage<PageProps>().props;
    const selected = sizes.find((size: any) => size.slug === form.data.size);
    const selectedRegion = regions.find((region: any) => region.slug === form.data.region);
    const selectedImage = images.find((image: any) => image.slug === form.data.image);
    const selectedKey = keys.find((key: any) => key.id === form.data.ssh_key_id);
    const price = Number(selected?.customer_price_monthly ?? selected?.price_monthly ?? 0);

    const next = () => {
        if (step === 1 && (! form.data.region || ! form.data.size || ! form.data.image)) return;
        if (step === 2 && ! form.data.ssh_key_id) return;
        if (step === 3 && (! form.data.name || ! form.data.hostname)) return;
        setStep((value) => Math.min(4, value + 1));
    };

    return (
        <ConsoleLayout crumb="Managed server">
            <div className="app-main !max-w-4xl">
                <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <div className="flex items-center gap-2">
                            <p className="page-eyebrow">Managed infrastructure</p>
                            <span className="badge badge-info">Platform hosted</span>
                        </div>
                        <h1 className="page-title">Provision a managed server</h1>
                        <p className="mt-2 max-w-xl text-sm muted">{platform} creates and bills this VPS for you — no cloud provider account required.</p>
                    </div>
                    <span className="text-sm muted">Step {step} of 4</span>
                </div>
                <div className="wizard-track" role="progressbar" aria-valuemin={1} aria-valuemax={4} aria-valuenow={step} aria-label="Provisioning step">
                    {[1, 2, 3, 4].map((index) => <div key={index} className={`wizard-step ${index <= step ? 'wizard-step-on' : ''}`} />)}
                </div>
                <Flash />
                {catalogError && <p className="flash-danger mb-5">{catalogError}</p>}
                <section className="panel sm:!p-8">
                    {step === 1 && (
                        <>
                            <h2 className="section-title">Server configuration</h2>
                            <p className="mt-0.5 text-sm muted">Sizes and regions come from the platform cloud account.</p>
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <label className="text-sm heading">Region
                                    <select className="field" value={form.data.region} onChange={(e) => form.setData('region', e.target.value)}>
                                        <option value="">Select region</option>
                                        {regions.map((region: any) => <option key={region.slug} value={region.slug}>{region.name}</option>)}
                                    </select>
                                </label>
                                <label className="text-sm heading">Size
                                    <select className="field" value={form.data.size} onChange={(e) => form.setData('size', e.target.value)}>
                                        <option value="">Select size</option>
                                        {sizes.map((size: any) => <option key={size.slug} value={size.slug}>{size.vcpus ? `${size.vcpus} vCPU · ${Math.round((size.memory || 0) / 1024 * 10) / 10} GB · ` : ''}{`$${Number(size.customer_price_monthly ?? size.price_monthly ?? 0).toFixed(2)}/mo`}</option>)}
                                    </select>
                                </label>
                                <label className="text-sm heading sm:col-span-2">Ubuntu image
                                    <select className="field" value={form.data.image} onChange={(e) => form.setData('image', e.target.value)}>
                                        {images.map((image: any) => <option key={image.slug} value={image.slug}>{image.name || image.slug}</option>)}
                                    </select>
                                </label>
                            </div>
                        </>
                    )}
                    {step === 2 && (
                        <>
                            <h2 className="section-title">SSH key</h2>
                            <p className="mt-0.5 text-sm muted">A managed key is created for you so provisioning workers can bootstrap the host.</p>
                            <div className="mt-6 grid gap-3">
                                {keys.length === 0 && <p className="flash-warning">Generate a key under <Link className="link-action" href={route('ssh-keys')}>SSH keys</Link>.</p>}
                                {keys.map((key: any) => (
                                    <label key={key.id} className={`choice-card ${form.data.ssh_key_id === key.id ? 'choice-card-active' : ''}`}>
                                        <input type="radio" name="ssh_key_id" value={key.id} checked={form.data.ssh_key_id === key.id} onChange={() => form.setData('ssh_key_id', key.id)} />
                                        <span><b className="heading">{key.name}</b>{key.fingerprint && <small className="block font-mono muted">{key.fingerprint}</small>}</span>
                                    </label>
                                ))}
                            </div>
                        </>
                    )}
                    {step === 3 && (
                        <>
                            <h2 className="section-title">Identify your server</h2>
                            <p className="mt-0.5 text-sm muted">Used across the dashboard and in DNS suggestions.</p>
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <label className="text-sm heading">Display name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Production API" /></label>
                                <label className="text-sm heading">Hostname<input className="field" value={form.data.hostname} onChange={(e) => form.setData('hostname', e.target.value)} placeholder="app-server-01" /></label>
                            </div>
                        </>
                    )}
                    {step === 4 && (
                        <>
                            <h2 className="section-title">Review and deploy</h2>
                            <div className="mt-6 flex flex-col gap-4 lg:flex-row">
                                <dl className="well grid min-w-0 flex-1 gap-4 sm:grid-cols-2">
                                    {[['Name', form.data.name], ['Hostname', form.data.hostname], ['Region', selectedRegion?.name || form.data.region], ['Size', selected ? `${selected.vcpus || ''} vCPU · ${Math.round((selected.memory || 0) / 1024 * 10) / 10} GB` : form.data.size], ['Image', selectedImage?.name || form.data.image], ['SSH key', selectedKey?.name || '—']].map(([label, value]) => (
                                        <div key={label}><dt className="text-xs uppercase tracking-wide muted">{label}</dt><dd className="mt-1 font-medium heading">{value}</dd></div>
                                    ))}
                                    <div className="sm:col-span-2"><dt className="text-xs uppercase tracking-wide muted">Type</dt><dd className="mt-1"><span className="badge badge-info">Managed server</span></dd></div>
                                </dl>
                                <div className="well plan-featured w-full shrink-0 lg:w-72">
                                    <p className="page-eyebrow !mt-0">Monthly price</p>
                                    <p className="mt-2 text-2xl font-semibold heading">${price.toFixed(2)}<span className="text-sm font-normal muted">/mo</span></p>
                                    <p className="mt-1 text-xs muted">{stripeEnabled ? `Checkout charges $${price.toFixed(2)}/mo before provisioning.` : 'Stripe is not configured; paid sizes cannot check out.'}</p>
                                    <ul className="mt-4 space-y-2 text-sm muted">
                                        <li>✓ Billed monthly via Stripe Checkout</li>
                                        <li>✓ Counts toward managed servers, not BYOS</li>
                                        <li>✓ Provisions after payment confirms</li>
                                    </ul>
                                </div>
                            </div>
                        </>
                    )}
                    {(errors.billing || errors.catalog) && <p className="mt-4 text-sm text-rose-600">{errors.billing || errors.catalog}</p>}
                    <div className="mt-8 flex justify-between">
                        {step > 1 ? <button type="button" className="button-secondary" onClick={() => setStep((value) => Math.max(1, value - 1))}>Back</button> : <span />}
                        {step < 4
                            ? <button type="button" className="button-primary" onClick={next} disabled={Boolean(catalogError)}>Continue</button>
                            : <button type="button" className="button-primary" disabled={form.processing || Boolean(catalogError)} onClick={() => form.post(route('servers.managed.store'))}>{price > 0 ? `Pay $${price.toFixed(2)}/mo & deploy` : 'Deploy managed server'}</button>}
                    </div>
                </section>
            </div>
        </ConsoleLayout>
    );
}
