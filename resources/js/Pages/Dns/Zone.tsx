import { router, useForm } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { route } from '../../lib/route';

export default function Zone({ zone, records, error, types, sites }: any) {
    const form = useForm({ type: 'A', name: '@', content: '', ttl: 1, priority: 0 });
    return (
        <ConsoleLayout crumb={zone.name}>
            <div className="app-main">
                <h1 className="page-title">{zone.name}</h1>
                <Flash />
                {error && <p className="flash-danger mt-5">{error}</p>}
                <form onSubmit={(e) => { e.preventDefault(); form.post(route('dns.records.store', zone.id)); }} className="panel mt-6 grid gap-3 sm:grid-cols-5">
                    <select className="field" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>{(types || []).map((t: string) => <option key={t}>{t}</option>)}</select>
                    <input className="field" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <input className="field" placeholder="Content" value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} />
                    <select className="field" value={form.data.content} onChange={(e) => form.setData('content', e.target.value)}>
                        <option value="">Point at…</option>
                        {(sites || []).map((site: any) => <option key={site.id} value={site.server?.public_ip}>{site.domain} · {site.server?.public_ip}</option>)}
                    </select>
                    <button className="button-primary">Add record</button>
                </form>
                <div className="mt-4 space-y-2">
                    {(records || []).map((record: any) => (
                        <div key={record.id} className="panel flex justify-between text-sm">
                            <span>{record.type} · {record.name} · {record.content}</span>
                            <button className="link-danger" onClick={() => router.delete(route('dns.records.destroy', [zone.id, record.id]))}>Delete</button>
                        </div>
                    ))}
                </div>
            </div>
        </ConsoleLayout>
    );
}
