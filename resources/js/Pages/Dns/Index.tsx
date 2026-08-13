import { Link, router, useForm } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { route } from '../../lib/route';

export default function Index({ accounts, zones }: any) {
    const form = useForm({ name: '', token: '' });
    return (
        <ConsoleLayout crumb="DNS">
            <div className="app-main">
                <h1 className="page-title">DNS</h1>
                <p className="page-subtitle">Connect a Cloudflare token and manage zone records.</p>
                <Flash />
                <form onSubmit={(e) => { e.preventDefault(); form.post(route('dns.accounts.store')); }} className="panel mt-8 grid gap-4 sm:grid-cols-2">
                    <label className="text-sm">Name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /></label>
                    <label className="text-sm">API token<input className="field" type="password" value={form.data.token} onChange={(e) => form.setData('token', e.target.value)} required /></label>
                    <button className="button-primary">Connect Cloudflare</button>
                </form>
                <div className="mt-6 space-y-3">
                    {(accounts || []).map((account: any) => (
                        <div key={account.id} className="panel flex justify-between"><span>{account.name} · {account.zones_count} zones</span><button className="link-danger" onClick={() => router.delete(route('dns.accounts.destroy', account.id))}>Remove</button></div>
                    ))}
                    {(zones || []).map((zone: any) => (
                        <Link key={zone.id} href={route('dns.zones.show', zone.id)} className="panel block">{zone.name}</Link>
                    ))}
                </div>
            </div>
        </ConsoleLayout>
    );
}
