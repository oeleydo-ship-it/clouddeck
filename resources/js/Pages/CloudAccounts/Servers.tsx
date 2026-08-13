import { router } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { route } from '../../lib/route';

export default function Servers({ account, droplets, imported, keys }: any) {
    return (
        <ConsoleLayout crumb="Import Droplets">
            <div className="app-main">
                <h1 className="page-title">{account.name}</h1>
                <p className="page-subtitle">Import and bootstrap existing Droplets onto this account.</p>
                <Flash />
                <div className="mt-8 space-y-3">
                    {(droplets || []).map((droplet: any) => {
                        const existing = imported?.[droplet.id] || imported?.[String(droplet.id)];
                        return (
                            <div key={droplet.id} className="panel flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="font-semibold heading">{droplet.name || droplet.id}</p>
                                    <p className="text-sm muted">{droplet.networks?.v4?.[0]?.ip_address || droplet.ip || droplet.public_ip} · {droplet.region?.slug || droplet.region}</p>
                                </div>
                                {existing ? <span className="text-sm muted">Already imported</span> : (
                                    <form onSubmit={(e) => { e.preventDefault(); const ssh_key_id = (e.currentTarget.elements.namedItem('ssh_key_id') as HTMLSelectElement).value; router.post(route('cloud-accounts.servers.store', account.id), { provider_id: droplet.id, ssh_key_id }); }} className="flex gap-2">
                                        <select className="field mt-0" name="ssh_key_id">{(keys || []).map((key: any) => <option key={key.id} value={key.id}>{key.name}</option>)}</select>
                                        <button className="button-primary">Import and bootstrap</button>
                                    </form>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </ConsoleLayout>
    );
}
