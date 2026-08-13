import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { route } from '../../lib/route';

export default function Index({ servers, rules, detectionEnabled, settingsScope, canManageSettings, protectedServers, protectedSites, openCritical, lastScan, empty, default_label, copy }: any) {
    const [scanning, setScanning] = useState(false);
    const form = useForm({ enabled: detectionEnabled, rules: Object.fromEntries((rules || []).map((r: any) => [r.key, r])) });

    useEffect(() => {
        if (! scanning) return;
        const t = setInterval(() => {
            fetch(route('security.status'), { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((body) => { if (! body.scanning) { setScanning(false); router.reload(); } });
        }, 2000);
        return () => clearInterval(t);
    }, [scanning]);

    return (
        <ConsoleLayout crumb="Security">
            <div className="app-main">
                <h1 className="page-title">Security</h1>
                <p className="page-subtitle">{settingsScope || default_label || 'Default: on'}. Protects servers and sites from common probes.</p>
                <Flash />
                <div className="mt-8 grid gap-3 sm:grid-cols-4">
                    {[['Protected servers', protectedServers], ['Protected sites', protectedSites], ['Open critical', openCritical], ['Last scan', lastScan || 'Never']].map(([label, value]) => (
                        <div key={String(label)} className="stat-card"><p className="stat-label">{label}</p><p className="stat-value mt-2 text-lg">{String(value)}</p></div>
                    ))}
                </div>
                <div className="mt-6 flex gap-3">
                    <button className="button-primary" disabled={scanning || ! detectionEnabled} onClick={() => { setScanning(true); router.post(route('security.scan'), {}, { onFinish: () => {} }); }}>{scanning ? 'Scanning…' : 'Scan now'}</button>
                </div>
                {servers.length === 0 && <p className="mt-6 panel text-center muted">{empty || 'Add a server to start protecting it'}</p>}
                <div className="mt-6 space-y-2">{servers.map((server: any) => (
                    <div key={server.id} className="panel">
                        <p className="heading">{server.name}</p>
                        <p className="text-xs muted">{server.security_scan_status || copy?.[2] || 'idle'}{server.security_scan_error ? ` · ${server.security_scan_error}` : ''}</p>
                    </div>
                ))}</div>
                <p className="sr-only">{(copy || []).join(' ')}</p>
                {canManageSettings && (
                    <form onSubmit={(e) => { e.preventDefault(); form.put(route('security.settings.update')); }} className="panel mt-8">
                        <label className="flex gap-2 text-sm heading"><input type="checkbox" checked={form.data.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} />Enable security detection</label>
                        <button className="button-primary mt-4">Save settings</button>
                    </form>
                )}
            </div>
        </ConsoleLayout>
    );
}
