import { useEffect, useRef } from 'react';
import { Link, router } from '@inertiajs/react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { StatusBadge } from '../../Components/StatusBadge';
import { useLiveReload } from '../../lib/live';
import { route } from '../../lib/route';
import { enumValue } from '../../lib/ui';

export default function Show({ deployment }: { deployment: any }) {
    const status = enumValue(deployment.status);
    const active = ['pending', 'running'].includes(status);
    const logs = [...(deployment.logs || [])].sort((a: any, b: any) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
    const scroller = useRef<HTMLPreElement>(null);

    useLiveReload({
        active,
        channels: [`deployments.${deployment.id}`],
        events: ['.log-appended', '.deployment-finished'],
        only: ['deployment'],
        interval: 2000,
    });

    useEffect(() => {
        if (scroller.current) scroller.current.scrollTop = scroller.current.scrollHeight;
    }, [logs.length, deployment.progress]);

    return (
        <ConsoleLayout crumb="Deployment">
            <div className="app-main">
                <Link className="link-action" href={route('sites.show', deployment.site_id || deployment.site?.id)}>← {deployment.site?.domain}</Link>
                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="page-title !mt-0">{deployment.release || deployment.id}</h1>
                        <StatusBadge status={status} />
                    </div>
                    <div className="flex gap-3">
                        {active && <button className="button-secondary !text-rose-600" onClick={() => router.post(route('deployments.cancel', deployment.id))}>Cancel</button>}
                        {! active && <button className="button-primary" onClick={() => router.post(route('deployments.retry', deployment.id))}>Deploy again</button>}
                    </div>
                </div>
                <Flash />
                {active && (
                    <div className="meter mt-5">
                        <span className="meter-fill transition-[width] duration-200" style={{ width: `${Math.max(2, Number(deployment.progress) || 2)}%` }} />
                    </div>
                )}
                <pre ref={scroller} className="log-pane mt-6">
                    {logs.length === 0
                        ? (active ? 'Waiting for a deployment worker…' : 'This deployment recorded no output.')
                        : logs.map((log: any) => {
                            const stamp = log.created_at ? String(log.created_at).slice(11, 19) : '';
                            return `[${stamp}] ${log.output}`;
                        }).join('\n')}
                </pre>
            </div>
        </ConsoleLayout>
    );
}
