import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { PageProps } from '../types';
import { route } from '../lib/route';

export function AiGuide() {
    const { csrf_token } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [log, setLog] = useState<Array<{ role: string; text: string }>>([]);
    const [busy, setBusy] = useState(false);

    const send = async () => {
        if (! message.trim() || busy) return;
        const text = message.trim();
        setMessage('');
        setLog((rows) => [...rows, { role: 'user', text }]);
        setBusy(true);
        try {
            const res = await fetch(route('guide.chat'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf_token, Accept: 'application/json' },
                body: JSON.stringify({ message: text }),
            });
            const data = await res.json();
            setLog((rows) => [...rows, { role: 'assistant', text: data.reply || data.message || 'No reply.' }]);
        } catch {
            setLog((rows) => [...rows, { role: 'assistant', text: 'The guide is unavailable right now.' }]);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="fixed bottom-5 right-5 z-40">
            {open && (
                <div className="panel mb-3 w-80 !p-4 shadow-lg shadow-slate-900/8">
                    <p className="text-sm font-semibold tracking-[-0.02em] heading">Platform guide</p>
                    <div className="mt-3 max-h-64 space-y-2 overflow-y-auto text-sm">
                        {log.map((row, i) => <p key={i} className={row.role === 'user' ? 'text-right heading' : 'muted'}>{row.text}</p>)}
                    </div>
                    <div className="mt-3 flex gap-2">
                        <input className="field !mt-0" value={message} onChange={(e) => setMessage(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && send()} placeholder="Ask how to…" />
                        <button type="button" className="button-primary !px-3" onClick={send} disabled={busy}>Send</button>
                    </div>
                </div>
            )}
            <button type="button" onClick={() => setOpen(! open)} className="button-primary rounded-full !px-4 shadow-md shadow-sky-700/20">{open ? 'Close' : 'Guide'}</button>
        </div>
    );
}
