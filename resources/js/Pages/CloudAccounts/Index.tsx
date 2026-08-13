import { Link, router, useForm, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

export default function Index({ accounts, providers, sshNote, addByIp }: { accounts: any[]; providers: Record<string, { label?: string; api?: boolean }>; sshNote?: string; addByIp?: string }) {
    const { features } = usePage<PageProps>().props;
    const form = useForm({ name: '', provider: Object.keys(providers || { digitalocean: {} })[0], token: '', public_ip: '', ssh_port: 22 });
    const drivesApi = Boolean(providers?.[form.data.provider]?.api ?? form.data.provider === 'digitalocean');

    return (
        <ConsoleLayout crumb="Providers">
            <div className="app-main">
                <h1 className="page-title">Cloud providers</h1>
                <p className="page-subtitle">Connect DigitalOcean to provision Droplets, or record another provider and attach servers by IP.</p>
                <Flash />
                {! features.providers && <p className="flash-warning mt-5">Cloud providers isn’t on your plan. <Link className="link-action" href={route('billing.index')}>Upgrade</Link>.</p>}
                <form onSubmit={(e) => { e.preventDefault(); form.post('/cloud-accounts'); }} className="panel mt-8 grid gap-4 sm:grid-cols-2">
                    <label className="text-sm heading">Name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /></label>
                    <label className="text-sm heading">Provider
                        <select className="field" value={form.data.provider} onChange={(e) => form.setData('provider', e.target.value)}>
                            {Object.entries(providers || {}).map(([key, info]) => <option key={key} value={key}>{(info as any).label || key}</option>)}
                        </select>
                    </label>
                    {drivesApi ? <label className="text-sm heading sm:col-span-2">API token<input className="field" type="password" value={form.data.token} onChange={(e) => form.setData('token', e.target.value)} /></label> : (
                        <>
                            <label className="text-sm heading">Public IP<input className="field" value={form.data.public_ip} onChange={(e) => form.setData('public_ip', e.target.value)} /></label>
                            <label className="text-sm heading">SSH port<input className="field" type="number" value={form.data.ssh_port} onChange={(e) => form.setData('ssh_port', Number(e.target.value))} /></label>
                            <p className="sm:col-span-2 text-sm muted">{sshNote || 'Servers attached by IP over SSH'}. Continue to add the server after saving.</p>
                        </>
                    )}
                    <button className="button-primary">Connect</button>
                </form>
                <div className="mt-6 space-y-3">
                    {accounts.map((account) => (
                        <div key={account.id} className="panel flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-semibold heading">{account.name}</p>
                                <p className="text-sm muted capitalize">{account.provider} · {account.status_label || (account.validated_at ? 'Validated' : 'Pending')} · {account.servers_count || 0} servers</p>
                                {account.connection_note && <p className="text-xs muted">{account.connection_note}</p>}
                            </div>
                            <div className="flex gap-3">
                                {providers?.[account.provider]?.api
                                    ? <Link href={account.servers_url || route('cloud-accounts.servers', account.id)} className="button-secondary">{account.action_label || 'Discover and connect'}</Link>
                                    : <Link href={account.servers_url || route('servers.custom', { cloud_account: account.id })} className="button-secondary">{account.action_label || addByIp || 'Add a server by IP'}</Link>}
                                <button className="link-danger" onClick={() => router.delete(`/cloud-accounts/${account.id}`)}>Disconnect</button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </ConsoleLayout>
    );
}
