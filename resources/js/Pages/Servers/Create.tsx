import { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

type Account = { id: string; name: string; provider: string; validated_at?: string };
type Key = { id: string; name: string; fingerprint?: string };
type Catalog = { regions: Array<{ slug: string; name: string }>; sizes: Array<{ slug: string; memory?: number; vcpus?: number; price_monthly?: number }>; images: Array<{ slug: string; name?: string }> };

export default function Create({ accounts, keys }: { accounts: Account[]; keys: Key[] }) {
    const form = useForm({ cloud_account_id: '', region: '', size: '', image: 'ubuntu-24-04-x64', ssh_key_id: keys[0]?.id || '', name: '', hostname: '' });
    const [step, setStep] = useState(1);
    const [catalog, setCatalog] = useState<Catalog>({ regions: [], sizes: [], images: [] });
    const [loading, setLoading] = useState(false);
    const [catalogError, setCatalogError] = useState('');
    const { errors, branding } = usePage<PageProps>().props;
    const selectedAccount = accounts.find((account) => account.id === form.data.cloud_account_id);
    const selectedKey = keys.find((key) => key.id === form.data.ssh_key_id);
    const selectedSize = catalog.sizes.find((size) => size.slug === form.data.size);

    const loadCatalog = (accountId: string) => {
        setLoading(true);
        setCatalogError('');
        fetch(route('servers.catalog', accountId), { headers: { Accept: 'application/json' } })
            .then(async (res) => {
                const body = await res.json();
                if (! res.ok) throw new Error(body.message || 'Catalog failed');
                setCatalog(body);
                const image = body.images?.find((item: any) => item.slug === 'ubuntu-24-04-x64')?.slug || body.images?.[0]?.slug || '';
                form.setData((data) => ({ ...data, cloud_account_id: accountId, image, region: '', size: '' }));
            })
            .catch((error) => {
                setCatalog({ regions: [], sizes: [], images: [] });
                setCatalogError(error.message || 'Unable to retrieve the provider catalog. Check the account and try again.');
            })
            .finally(() => setLoading(false));
    };

    const next = () => {
        if (step === 1 && ! form.data.cloud_account_id) return;
        if (step === 2 && (! form.data.region || ! form.data.size || ! form.data.image)) return;
        if (step === 3 && ! form.data.ssh_key_id) return;
        if (step === 4 && (! form.data.name || ! form.data.hostname)) return;
        setStep((value) => Math.min(5, value + 1));
    };

    return (
        <ConsoleLayout crumb="Provision server">
            <div className="app-main !max-w-4xl">
                <div className="mb-8 flex items-end justify-between">
                    <div>
                        <p className="page-eyebrow">New infrastructure</p>
                        <h1 className="page-title">Provision a server</h1>
                    </div>
                    <span className="text-sm muted">Step {step} of 5</span>
                </div>
                <div className="wizard-track" role="progressbar" aria-valuemin={1} aria-valuemax={5} aria-valuenow={step} aria-label="Provisioning step">
                    {[1, 2, 3, 4, 5].map((index) => <div key={index} className={`wizard-step ${index <= step ? 'wizard-step-on' : ''}`} />)}
                </div>
                <Flash />
                <section className={`panel sm:!p-8 ${loading ? 'opacity-60' : ''}`}>
                    {step === 1 && (
                        <>
                            <h2 className="section-title">Choose a cloud account</h2>
                            <p className="mt-2 text-sm muted">{branding.name} will provision through a validated provider connection.</p>
                            <div className="mt-6 grid gap-3">
                                {accounts.length === 0 && <p className="flash-warning">Connect a <Link className="link-action" href={route('cloud-accounts')}>cloud account</Link> first.</p>}
                                {accounts.map((account) => (
                                    <label key={account.id} className={`choice-card ${form.data.cloud_account_id === account.id ? 'choice-card-active' : ''}`}>
                                        <input type="radio" name="cloud_account_id" value={account.id} checked={form.data.cloud_account_id === account.id} onChange={() => loadCatalog(account.id)} />
                                        <span><b>{account.name}</b><small className="block capitalize muted">{account.provider}</small></span>
                                    </label>
                                ))}
                            </div>
                            {loading && <p className="mt-4 text-sm muted">Loading catalog…</p>}
                        </>
                    )}
                    {step === 2 && (
                        <>
                            <h2 className="section-title">Server configuration</h2>
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <label className="text-sm">Region
                                    <select className="field" value={form.data.region} onChange={(e) => form.setData('region', e.target.value)}>
                                        <option value="">Select region</option>
                                        {catalog.regions.map((region) => <option key={region.slug} value={region.slug}>{region.name}</option>)}
                                    </select>
                                </label>
                                <label className="text-sm">Size
                                    <select className="field" value={form.data.size} onChange={(e) => form.setData('size', e.target.value)}>
                                        <option value="">Select size</option>
                                        {catalog.sizes.map((size) => <option key={size.slug} value={size.slug}>{size.vcpus ? `${size.vcpus} vCPU · ${Math.round((size.memory || 0) / 1024 * 10) / 10} GB · ` : ''}{size.price_monthly ? `$${size.price_monthly}/mo` : size.slug}</option>)}
                                    </select>
                                </label>
                                <label className="text-sm sm:col-span-2">Ubuntu image
                                    <select className="field" value={form.data.image} onChange={(e) => form.setData('image', e.target.value)}>
                                        {catalog.images.map((image) => <option key={image.slug} value={image.slug}>{image.name || image.slug}</option>)}
                                    </select>
                                </label>
                            </div>
                        </>
                    )}
                    {step === 3 && (
                        <>
                            <h2 className="section-title">Select a managed SSH key</h2>
                            <p className="mt-2 text-sm muted">A managed key lets the provisioning workers securely bootstrap the server.</p>
                            <div className="mt-6 grid gap-3">
                                {keys.length === 0 && <p className="flash-warning">Generate a managed key under <Link className="link-action" href={route('ssh-keys')}>SSH keys</Link>.</p>}
                                {keys.map((key) => (
                                    <label key={key.id} className={`choice-card ${form.data.ssh_key_id === key.id ? 'choice-card-active' : ''}`}>
                                        <input type="radio" name="ssh_key_id" value={key.id} checked={form.data.ssh_key_id === key.id} onChange={() => form.setData('ssh_key_id', key.id)} />
                                        <span><b>{key.name}</b>{key.fingerprint && <small className="block font-mono muted">{key.fingerprint}</small>}</span>
                                    </label>
                                ))}
                            </div>
                        </>
                    )}
                    {step === 4 && (
                        <>
                            <h2 className="section-title">Identify your server</h2>
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <label className="text-sm">Display name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Production API" /></label>
                                <label className="text-sm">Hostname<input className="field" value={form.data.hostname} onChange={(e) => form.setData('hostname', e.target.value)} placeholder="app-server-01" /></label>
                            </div>
                        </>
                    )}
                    {step === 5 && (
                        <>
                            <h2 className="section-title">Review and deploy</h2>
                            <dl className="well mt-6 grid gap-4 sm:grid-cols-2">
                                {[['Name', form.data.name], ['Hostname', form.data.hostname], ['Account', selectedAccount?.name || '—'], ['Region', form.data.region], ['Size', selectedSize ? `${selectedSize.vcpus || ''} vCPU · ${form.data.size}` : form.data.size], ['Image', form.data.image], ['SSH key', selectedKey?.name || '—']].map(([label, value]) => (
                                    <div key={label}><dt className="text-xs uppercase tracking-wide muted">{label}</dt><dd className="mt-1 font-medium heading">{value}</dd></div>
                                ))}
                            </dl>
                            <p className="mt-5 text-sm muted">Provisioning runs asynchronously. You can leave the dashboard while {branding.name} completes installation.</p>
                        </>
                    )}
                    {(catalogError || Object.values(errors).length > 0) && <p className="mt-4 text-sm text-rose-600">{catalogError || Object.values(errors)[0]}</p>}
                    <div className="mt-8 flex justify-between">
                        {step > 1 ? <button type="button" className="button-secondary" onClick={() => setStep((value) => Math.max(1, value - 1))}>Back</button> : <span />}
                        {step < 5
                            ? <button type="button" className="button-primary" onClick={next} disabled={loading || (step === 1 && accounts.length === 0)}>Continue</button>
                            : <button type="button" className="button-primary" disabled={form.processing || accounts.length === 0 || keys.length === 0} onClick={() => form.post(route('servers.store'))}>Deploy server</button>}
                    </div>
                </section>
            </div>
        </ConsoleLayout>
    );
}
