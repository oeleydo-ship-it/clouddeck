import { useForm } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';
import { route } from '../../lib/route';

export default function TwoFactorChallenge() {
    const form = useForm({ code: '' });

    return (
        <GuestLayout title="Two-factor authentication">
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('two-factor.login')); }} className="auth-card">
                <p className="text-sm muted">Enter the code from your authenticator app or a recovery code.</p>
                <label className="mt-4 block text-sm heading">Code<input className="field mt-1" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} required autoFocus /></label>
                <button className="button-primary mt-5" disabled={form.processing}>Continue</button>
            </form>
        </GuestLayout>
    );
}
