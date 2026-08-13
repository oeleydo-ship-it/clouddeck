import { useForm } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { route } from '../../lib/route';

export default function Custom({ sshKey, account, authorizedKeysPath, sshNote }: { sshKey: { public_key: string; name: string }; account?: { id: string; name?: string; provider?: string } | null; authorizedKeysPath?: string; sshNote?: string }) {
    const params = new URLSearchParams(window.location.search);
    const form = useForm({
        name: '',
        public_ip: params.get('public_ip') || '',
        ssh_port: Number(params.get('ssh_port') || 22),
        image: 'ubuntu-24-04-x64',
        cloud_account_id: account?.id || params.get('cloud_account') || '',
    });

    return (
        <ConsoleLayout crumb="Add existing server">
            <div className="app-main !max-w-3xl">
                <p className="page-eyebrow">Bring your own server</p>
                <h1 className="page-title">Add a server by IP</h1>
                <p className="page-subtitle">{sshNote || 'Servers attached by IP over SSH'}. Authorise the public key below on the host, then submit the address.</p>
                {account && <p className="mt-3 text-sm muted">Connecting under {account.name || account.provider}.</p>}
                <Flash />
                <section className="panel mt-8">
                    <h2 className="font-semibold heading">1. Authorise this key</h2>
                    <p className="mt-2 text-sm muted">Add the line to <code>{authorizedKeysPath || 'authorized_keys'}</code> for root (or the SSH user).</p>
                    <pre className="log-pane mt-4">{sshKey.public_key?.trim()}</pre>
                </section>
                <form onSubmit={(e) => { e.preventDefault(); form.post(route('servers.custom.store')); }} className="panel mt-6 space-y-4">
                    <h2 className="font-semibold heading">2. Connection details</h2>
                    <label className="text-sm heading">Name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /></label>
                    <label className="text-sm heading">Public IP<input className="field" name="public_ip" value={form.data.public_ip} onChange={(e) => form.setData('public_ip', e.target.value)} required /></label>
                    <label className="text-sm heading">SSH port<input className="field" type="number" name="ssh_port" value={form.data.ssh_port} onChange={(e) => form.setData('ssh_port', Number(e.target.value))} /></label>
                    <label className="text-sm heading">Image
                        <select className="field" name="image" value={form.data.image} onChange={(e) => form.setData('image', e.target.value)}>
                            <option value="ubuntu-24-04-x64">Ubuntu 24.04</option>
                            <option value="ubuntu-22-04-x64">Ubuntu 22.04</option>
                        </select>
                    </label>
                    <button className="button-primary" disabled={form.processing}>Attach and provision</button>
                </form>
            </div>
        </ConsoleLayout>
    );
}
