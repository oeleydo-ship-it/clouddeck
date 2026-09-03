import { useForm, usePage } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { route } from '../../lib/route';

type RemovedServer = {
    id: string;
    name: string;
    hostname: string;
    public_ip: string;
    ssh_port?: number;
    provisioned_at?: string | null;
    deleted_at?: string | null;
    provisioning_source?: string | null;
};

export default function Custom({ sshKey, account, authorizedKeysPath, sshNote, removedServers = [] }: { sshKey: { public_key: string; name: string }; account?: { id: string; name?: string; provider?: string } | null; authorizedKeysPath?: string; sshNote?: string; removedServers?: RemovedServer[] }) {
    const params = new URLSearchParams(window.location.search);
    const flash = usePage().props.flash as { reconnect_server_id?: string } | undefined;
    const suggestedReconnect = flash?.reconnect_server_id || '';
    const form = useForm({
        name: '',
        public_ip: params.get('public_ip') || '',
        ssh_port: Number(params.get('ssh_port') || 22),
        image: 'ubuntu-24-04-x64',
        cloud_account_id: account?.id || params.get('cloud_account') || '',
        reconnect_server_id: suggestedReconnect,
        reinstall_stack: false,
    });

    const reconnect = (server: RemovedServer) => {
        form.setData({
            ...form.data,
            reconnect_server_id: server.id,
            name: server.name,
            public_ip: server.public_ip,
            ssh_port: server.ssh_port || 22,
        });
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    };

    return (
        <ConsoleLayout crumb="Add existing server">
            <div className="app-main !max-w-3xl">
                <p className="page-eyebrow">Bring your own server</p>
                <h1 className="page-title">Add a server by IP</h1>
                <p className="page-subtitle">{sshNote || 'Servers attached by IP over SSH'}. Authorise the public key below on the host, then submit the address.</p>
                {account && <p className="mt-3 text-sm muted">Connecting under {account.name || account.provider}.</p>}
                <Flash />
                {removedServers.length > 0 && (
                    <section className="panel mt-8">
                        <h2 className="font-semibold heading">Reconnect a removed server</h2>
                        <p className="mt-2 text-sm muted">If you removed a server from Uplary by mistake but the machine is still running, reconnect it here to restore the same record instead of creating a duplicate.</p>
                        <div className="mt-4 space-y-3">
                            {removedServers.map((server) => (
                                <div key={server.id} className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-control)] border border-slate-200/80 px-4 py-3 dark:border-white/10">
                                    <div>
                                        <p className="font-medium heading">{server.name}</p>
                                        <p className="text-sm muted">{server.public_ip}{server.provisioned_at ? ' · previously provisioned' : ''}</p>
                                    </div>
                                    <button type="button" className="button-secondary !min-h-8 !px-3 !py-1.5 text-xs" onClick={() => reconnect(server)}>Use for reconnect</button>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
                <section className="panel mt-8">
                    <h2 className="font-semibold heading">1. Authorise this key</h2>
                    <p className="mt-2 text-sm muted">Add the line to <code>{authorizedKeysPath || 'authorized_keys'}</code> for root (or the SSH user).</p>
                    <pre className="log-pane mt-4">{sshKey.public_key?.trim()}</pre>
                </section>
                <form onSubmit={(e) => { e.preventDefault(); form.post(route('servers.custom.store')); }} className="panel mt-6 space-y-4">
                    <h2 className="font-semibold heading">{form.data.reconnect_server_id ? '2. Reconnect details' : '2. Connection details'}</h2>
                    {form.data.reconnect_server_id && (
                        <>
                            <p className="text-sm muted">Reconnecting an existing Uplary record. The host should still be running with your sites and data intact.</p>
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" className="mt-0.5" checked={form.data.reinstall_stack} onChange={(e) => form.setData('reinstall_stack', e.target.checked)} />
                                <span>Reinstall nginx, databases, PHP and other services on this host. Use this if the machine was reimaged or wiped since it was last provisioned — leave unchecked if the existing stack is still intact.</span>
                            </label>
                        </>
                    )}
                    <input type="hidden" name="reconnect_server_id" value={form.data.reconnect_server_id} />
                    <label className="text-sm heading">Name<input className="field" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /></label>
                    <label className="text-sm heading">Public IP<input className="field" name="public_ip" value={form.data.public_ip} onChange={(e) => form.setData('public_ip', e.target.value)} required /></label>
                    <label className="text-sm heading">SSH port<input className="field" type="number" name="ssh_port" value={form.data.ssh_port} onChange={(e) => form.setData('ssh_port', Number(e.target.value))} /></label>
                    <label className="text-sm heading">Image
                        <select className="field" name="image" value={form.data.image} onChange={(e) => form.setData('image', e.target.value)}>
                            <option value="ubuntu-26-04-x64">Ubuntu 26.04</option>
                            <option value="ubuntu-24-04-x64">Ubuntu 24.04</option>
                            <option value="ubuntu-22-04-x64">Ubuntu 22.04</option>
                        </select>
                    </label>
                    <button className="button-primary" disabled={form.processing}>{form.data.reconnect_server_id ? 'Reconnect server' : 'Attach and provision'}</button>
                </form>
            </div>
        </ConsoleLayout>
    );
}
