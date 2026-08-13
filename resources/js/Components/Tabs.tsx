import { ReactNode, useEffect, useState } from 'react';

export function Tabs({ tabs, initial }: { tabs: Record<string, string>; initial?: string }) {
    const keys = Object.keys(tabs);
    const fromQuery = new URLSearchParams(window.location.search).get('tab');
    const start = fromQuery && keys.includes(fromQuery) ? fromQuery : (initial && keys.includes(initial) ? initial : keys[0]);
    const [tab, setTab] = useState(start);

    useEffect(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        history.replaceState(null, '', url);
    }, [tab]);

    return { tab, setTab, keys, labels: tabs };
}

export function TabBar({ tabs, tab, setTab }: { tabs: Record<string, string>; tab: string; setTab: (key: string) => void }) {
    return (
        <div className="tab-bar">
            {Object.entries(tabs).map(([key, label]) => (
                <button key={key} type="button" onClick={() => setTab(key)} className={`tab-item ${tab === key ? 'tab-item-active' : 'tab-item-idle'}`}>{label}</button>
            ))}
        </div>
    );
}

export function TabPanel({ when, tab, children }: { when: string; tab: string; children: ReactNode }) {
    if (when !== tab) return null;
    return <div className="mt-6">{children}</div>;
}
