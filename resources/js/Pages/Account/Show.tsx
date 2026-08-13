import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ConsoleLayout from '../../Layouts/ConsoleLayout';
import { Flash } from '../../Components/Flash';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

export default function Show({ tokens, sessions, provisioningUri, twoFactorConfirmed }: any) {
    const { auth, flash } = usePage<PageProps>().props;
    const profile = useForm({ name: auth.user?.name || '', email: auth.user?.email || '', timezone: 'UTC' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const token = useForm({ name: '' });
    const enable2fa = useForm({ password: '' });
    const disable2fa = useForm({ password: '' });
    const [qr, setQr] = useState<string | null>(null);

    useEffect(() => {
        if (! provisioningUri) return;
        import('qrcode').then((QR) => QR.toDataURL(provisioningUri).then(setQr));
    }, [provisioningUri]);

    return (
        <ConsoleLayout crumb="Account">
            <div className="app-main !max-w-3xl">
                <h1 className="page-title">Account settings</h1>
                <Flash />
                <form onSubmit={(e) => { e.preventDefault(); profile.patch(route('account') + '/profile'); }} className="panel mt-8 space-y-4">
                    <h2 className="font-semibold heading">Profile</h2>
                    <label className="text-sm">Name<input className="field" value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} /></label>
                    <label className="text-sm">Email<input className="field" type="email" value={profile.data.email} onChange={(e) => profile.setData('email', e.target.value)} /></label>
                    <label className="text-sm">Timezone<input className="field" value={profile.data.timezone} onChange={(e) => profile.setData('timezone', e.target.value)} /></label>
                    <button className="button-primary">Save profile</button>
                </form>
                <form onSubmit={(e) => { e.preventDefault(); password.put('/account/password'); }} className="panel mt-6 space-y-4">
                    <h2 className="font-semibold heading">Password</h2>
                    <label className="text-sm">Current<input className="field" type="password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} /></label>
                    <label className="text-sm">New<input className="field" type="password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} /></label>
                    <label className="text-sm">Confirm<input className="field" type="password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} /></label>
                    <button className="button-primary">Update password</button>
                </form>
                <section className="panel mt-6">
                    <h2 className="font-semibold heading">Two-factor authentication</h2>
                    {! provisioningUri && ! twoFactorConfirmed && (
                        <form onSubmit={(e) => { e.preventDefault(); enable2fa.post('/account/two-factor'); }} className="mt-4 flex flex-wrap gap-2">
                            <input className="field mt-0" type="password" placeholder="Confirm password" value={enable2fa.data.password} onChange={(e) => enable2fa.setData('password', e.target.value)} />
                            <button className="button-secondary">Enable 2FA</button>
                        </form>
                    )}
                    {qr && <img src={qr} alt="2FA QR" className="mt-4 size-40" />}
                    {provisioningUri && <p className="mt-3 break-all text-xs muted">{provisioningUri}</p>}
                    {provisioningUri && <Confirm2fa />}
                    {flash.recovery_codes && (
                        <div className="flash-warning mt-4">
                            <p>Save these recovery codes now. They will not be shown again.</p>
                            <ul className="mt-2 font-mono text-xs">{flash.recovery_codes.map((code) => <li key={code}>{code}</li>)}</ul>
                        </div>
                    )}
                    {(twoFactorConfirmed || provisioningUri) && (
                        <form onSubmit={(e) => { e.preventDefault(); disable2fa.delete('/account/two-factor'); }} className="mt-4 flex flex-wrap gap-2">
                            <input className="field mt-0" type="password" placeholder="Confirm password" value={disable2fa.data.password} onChange={(e) => disable2fa.setData('password', e.target.value)} />
                            <button className="button-secondary !text-rose-600">Disable 2FA</button>
                        </form>
                    )}
                </section>
                <form onSubmit={(e) => { e.preventDefault(); token.post('/account/tokens'); }} className="panel mt-6">
                    <h2 className="font-semibold heading">API tokens</h2>
                    <div className="mt-4 flex gap-3"><input className="field" placeholder="CLI" value={token.data.name} onChange={(e) => token.setData('name', e.target.value)} /><button className="button-primary">Create token</button></div>
                    <div className="mt-4 space-y-2">{tokens.map((row: any) => <div key={row.id} className="flex justify-between text-sm"><span>{row.name}</span><button className="link-danger" onClick={() => router.delete(`/account/tokens/${row.id}`)}>Revoke</button></div>)}</div>
                </form>
                <section className="panel mt-6">
                    <h2 className="font-semibold heading">Sessions</h2>
                    {sessions.map((row: any) => (
                        <div key={row.id} className="mt-2 flex items-center justify-between text-sm muted">
                            <span>{row.ip_address} · {row.user_agent}</span>
                            <button className="link-danger" onClick={() => router.delete(`/account/sessions/${row.id}`)}>Revoke</button>
                        </div>
                    ))}
                </section>
            </div>
        </ConsoleLayout>
    );
}

function Confirm2fa() {
    const form = useForm({ code: '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post('/account/two-factor/confirm'); }} className="mt-4 flex gap-3">
            <input className="field" placeholder="123456" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
            <button className="button-primary">Confirm</button>
        </form>
    );
}
