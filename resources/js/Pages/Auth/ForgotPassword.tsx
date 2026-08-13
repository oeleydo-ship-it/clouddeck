import { useForm } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';
import { route } from '../../lib/route';

export default function ForgotPassword() {
    const form = useForm({ email: '' });

    return (
        <GuestLayout title="Reset password">
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('password.email')); }} className="auth-card">
                <p className="text-sm muted">Enter your email and we will send a reset link.</p>
                <label className="mt-4 block text-sm heading">Email<input className="field mt-1" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required /></label>
                <button className="button-primary mt-5" disabled={form.processing}>Send reset link</button>
            </form>
        </GuestLayout>
    );
}
