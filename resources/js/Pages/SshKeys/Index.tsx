import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

export default function Index({ keys }: { keys: any[] }) {
    const generate = useForm({ name: '' });
    const upload = useForm({ name: '', public_key: '' });
    const { flash } = usePage<PageProps>().props;

    useEffect(() => {
        if (flash.download_key) window.location.href = route('ssh-keys.download', flash.download_key);
    }, [flash.download_key]);

    return (
        <ConsoleLayout crumb="SSH keys">
            <div className="app-main">
                <h1 className="page-title">SSH keys</h1>
                <p className="page-subtitle">Keys used to provision and connect to servers.</p>
                <Flash />
                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    <form onSubmit={(e) => { e.preventDefault(); generate.post('/ssh-keys/generate'); }} className="panel">
                        <h2 className="font-semibold heading">Generate a key pair</h2>
                        <label className="mt-4 block text-sm">Name<input className="field" value={generate.data.name} onChange={(e) => generate.setData('name', e.target.value)} required /></label>
                        <button className="button-primary mt-4">Generate</button>
                    </form>
                    <form onSubmit={(e) => { e.preventDefault(); upload.post('/ssh-keys'); }} className="panel">
                        <h2 className="font-semibold heading">Upload a public key</h2>
                        <label className="mt-4 block text-sm">Name<input className="field" value={upload.data.name} onChange={(e) => upload.setData('name', e.target.value)} required /></label>
                        <label className="mt-4 block text-sm">Public key<textarea className="field min-h-32 font-mono text-xs" value={upload.data.public_key} onChange={(e) => upload.setData('public_key', e.target.value)} required /></label>
                        <button className="button-primary mt-4">Upload</button>
                    </form>
                </div>
                <div className="mt-6 space-y-3">
                    {keys.map((key) => (
                        <div key={key.id} className="panel flex flex-wrap items-center justify-between gap-3">
                            <div><p className="font-medium heading">{key.name}</p><p className="font-mono text-xs muted">{key.fingerprint}</p></div>
                            <button className="link-danger" onClick={() => router.delete(`/ssh-keys/${key.id}`)}>Delete</button>
                        </div>
                    ))}
                </div>
            </div>
        </ConsoleLayout>
    );
}
