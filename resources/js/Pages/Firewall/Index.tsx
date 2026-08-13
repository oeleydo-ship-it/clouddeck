import { router, useForm } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { route } from '../../lib/route';

export default function Index({ servers, selected, rules, namedPorts, lastSyncLabel }: any) {
    const form = useForm({ server_id: selected?.id || '', type: 'allow', protocol: 'tcp', port: '22', from_ip: '', description: '' });
    const selectedId = selected?.id;

    return (
        <ConsoleLayout crumb="Firewall">
            <div className="app-main">
                <h1 className="page-title">Firewall</h1>
                <p className="page-subtitle">UFW rules synced to the selected server. {lastSyncLabel || 'Last sync'} {selected?.firewall_synced_at || 'never'}.</p>
                <Flash />
                <div className="mt-6 flex flex-wrap gap-2">
                    {(servers || []).map((server: any) => (
                        <a key={server.id} href={`${route('firewall.index')}?server=${server.id}`} className={`button-secondary ${selectedId === server.id ? '!bg-slate-900 !text-white' : ''}`}>{server.name}</a>
                    ))}
                </div>
                {selected && (
                    <>
                        <div className="mt-4"><button className="button-secondary" onClick={() => router.post(route('firewall.refresh', selected.id))}>Refresh from server</button></div>
                        <form onSubmit={(e) => { e.preventDefault(); form.setData('server_id', selected.id); form.post(route('firewall.rules.store')); }} className="panel mt-6 grid gap-3 sm:grid-cols-5">
                            <input className="field" placeholder="Description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            <select className="field" value={form.data.port} onChange={(e) => form.setData('port', e.target.value)}>
                                <option value="22">22</option>
                                {(namedPorts || []).map((p: string) => <option key={p} value={p}>{p}</option>)}
                                <option value="80">80</option>
                                <option value="443">443</option>
                            </select>
                            <select className="field" value={form.data.protocol} onChange={(e) => form.setData('protocol', e.target.value)}><option>tcp</option><option>udp</option></select>
                            <select className="field" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}><option>allow</option><option>deny</option></select>
                            <button className="button-primary">Add rule</button>
                        </form>
                        <div className="mt-4 space-y-2">
                            {(rules || []).map((rule: any) => (
                                <div key={rule.id} className="panel flex justify-between text-sm"><span>{rule.description || rule.port} · {rule.type} {rule.protocol}</span><button className="link-danger" onClick={() => router.delete(route('firewall.rules.destroy', rule.id))}>Delete</button></div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </ConsoleLayout>
    );
}
