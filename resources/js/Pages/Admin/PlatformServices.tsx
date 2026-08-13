import { useEffect, useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';

const SERVICE_ORDER = ['redis', 'horizon', 'queue', 'reverb'];

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function badgeClass(status?: string) {
    if (status === 'running' || status === 'valid') return 'badge-success';
    if (status === 'stopped') return 'badge-neutral';
    if (status === 'degraded' || status === 'unavailable' || status === 'expiring_soon' || status === 'not_https') return 'badge-warning';
    if (status === 'error' || status === 'expired' || status === 'unreachable') return 'badge-danger';
    return 'badge-neutral';
}

export default function PlatformServices({ initial, heading, renewLabel }: { initial: any; heading?: string; renewLabel?: string }) {
    const [status, setStatus] = useState(initial || {});
    const [busy, setBusy] = useState(false);
    const [flash, setFlash] = useState('');
    const [flashOk, setFlashOk] = useState(true);
    const [now, setNow] = useState(Date.now());

    const poll = () => {
        fetch(route('admin.platform-services.status'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then((res) => res.ok ? res.json() : null)
            .then((data) => { if (data) setStatus(data); })
            .catch(() => {});
    };

    useEffect(() => {
        poll();
        const timer = setInterval(poll, 7000);
        const clock = setInterval(() => setNow(Date.now()), 1000);
        return () => { clearInterval(timer); clearInterval(clock); };
    }, []);

    const act = async (service: string, action: 'start' | 'stop' | 'restart') => {
        if (busy) return;
        setBusy(true);
        setFlash('');
        try {
            const res = await fetch(route(`admin.platform-services.${action}`, service), {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                credentials: 'same-origin',
                body: '{}',
            });
            const data = await res.json();
            setFlashOk(!! data.ok);
            setFlash(data.message || (data.ok ? 'Done.' : 'Action failed.'));
            if (data.service) setStatus((current: any) => ({ ...current, services: { ...current.services, [service]: data.service } }));
            poll();
        } catch {
            setFlashOk(false);
            setFlash('Request failed.');
        } finally {
            setBusy(false);
        }
    };

    const renewSsl = async () => {
        if (busy) return;
        setBusy(true);
        setFlash('');
        try {
            const res = await fetch(route('admin.platform-services.ssl.renew'), {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                credentials: 'same-origin',
                body: '{}',
            });
            const data = await res.json();
            setFlashOk(!! data.ok);
            setFlash(data.message || (data.ok ? 'Renew finished.' : 'Renew failed.'));
            if (data.ssl) setStatus((current: any) => ({ ...current, ssl: data.ssl }));
            poll();
        } catch {
            setFlashOk(false);
            setFlash('Request failed.');
        } finally {
            setBusy(false);
        }
    };

    const services = status.services || {};
    const ssl = status.ssl || {};
    const secs = status.polled_at ? Math.max(0, Math.round((now - new Date(status.polled_at).getTime()) / 1000)) : null;

    return (
        <AdminLayout title="Platform services" description="Live status and start/stop for this control plane’s Redis, Horizon, queue workers, Reverb, and HTTPS/TLS.">
            <section className="panel">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="section-title">{heading || 'Control-plane runtime'}</h2>
                        <p className="mt-1 text-sm muted">These are <strong className="heading">Uplary’s own</strong> processes — not customer-site Supervisor workers. Status refreshes every few seconds.</p>
                    </div>
                    <div className="text-right text-xs muted">
                        <p>{status.windows ? 'Windows' : (status.platform || 'Unix')} · {status.pcntl ? 'pcntl ok' : 'no pcntl'}</p>
                        <p className="mt-1">Updated {secs == null ? '—' : secs < 5 ? 'just now' : `${secs}s ago`}</p>
                    </div>
                </div>
                {! status.horizon_recommended && <p className="mt-3 text-xs muted">This PHP build looks like Windows or lacks <code>pcntl</code>/<code>posix</code>. Prefer <strong className="heading">Queue workers</strong> over Horizon here.</p>}
                {flash && <p className={`mt-4 ${flashOk ? 'flash-success' : 'flash-danger'}`}>{flash}</p>}
            </section>
            <div className="grid gap-4 sm:grid-cols-2">
                {SERVICE_ORDER.map((key) => {
                    const svc = services[key] || {};
                    return (
                        <section key={key} className="panel flex flex-col gap-3">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h3 className="section-title">{svc.name || (key === 'queue' ? 'Queue workers' : key === 'redis' ? 'Redis' : key === 'horizon' ? 'Horizon' : 'Reverb')}</h3>
                                    <p className="mt-1 text-xs muted">{svc.detail}</p>
                                </div>
                                <span className={`badge shrink-0 ${badgeClass(svc.status)}`}>
                                    <span className={`badge-dot ${svc.status === 'running' || svc.status === 'valid' ? 'bg-emerald-500' : svc.status === 'stopped' ? 'bg-slate-400' : 'bg-amber-500'}`} />
                                    <span className="capitalize">{svc.status || 'unknown'}</span>
                                </span>
                            </div>
                            <p className="text-xs muted">{svc.note}</p>
                            {svc.last_error && <p className="text-xs text-rose-600 dark:text-rose-300">{svc.last_error}</p>}
                            <div className="mt-auto flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-white/5">
                                {svc.link && <a href={svc.link} target="_blank" rel="noreferrer" className="button-secondary">Horizon dashboard</a>}
                                <button type="button" className="button-secondary" disabled={busy || svc.actions?.start === false} onClick={() => act(key, 'start')}>Start</button>
                                <button type="button" className="button-secondary" disabled={busy || svc.actions?.stop === false} onClick={() => act(key, 'stop')}>Stop</button>
                                <button type="button" className="button-secondary" disabled={busy || svc.actions?.restart === false} onClick={() => act(key, 'restart')}>Restart</button>
                            </div>
                        </section>
                    );
                })}
            </div>
            <section className="panel flex flex-col gap-3">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h3 className="section-title">{ssl.name || 'SSL / TLS'}</h3>
                        <p className="mt-1 text-xs muted">{ssl.detail}</p>
                    </div>
                    <span className={`badge shrink-0 ${badgeClass(ssl.status)}`}>
                        <span className={`badge-dot ${ssl.status === 'valid' ? 'bg-emerald-500' : ssl.status === 'expired' ? 'bg-rose-500' : 'bg-amber-500'}`} />
                        <span className="capitalize">{String(ssl.status || '—').replaceAll('_', ' ')}</span>
                    </span>
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt className="text-xs muted">APP_URL</dt><dd className="heading mt-0.5 break-all">{ssl.app_url || '—'}</dd></div>
                    <div><dt className="text-xs muted">Domain</dt><dd className="heading mt-0.5">{ssl.domain || '—'}</dd></div>
                    <div><dt className="text-xs muted">Issuer</dt><dd className="heading mt-0.5">{ssl.meta?.issuer || '—'}</dd></div>
                    <div><dt className="text-xs muted">Expires</dt><dd className="heading mt-0.5">{ssl.meta?.valid_to || '—'}</dd></div>
                </dl>
                <p className="text-xs muted">{ssl.note}</p>
                {ssl.last_error && <p className="text-xs text-rose-600 dark:text-rose-300">{ssl.last_error}</p>}
                <div className="mt-auto flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-white/5">
                    {ssl.docs_url && <a href={ssl.docs_url} className="button-secondary">SSL docs</a>}
                    <button type="button" className="button-secondary" disabled={busy || ssl.actions?.renew === false} onClick={renewSsl}>{renewLabel || 'Renew certificate'}</button>
                </div>
            </section>
        </AdminLayout>
    );
}
