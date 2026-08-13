import { useForm } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';
import { route } from '../../lib/route';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    return (
        <GuestLayout title="Choose a new password">
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('password.update')); }} className="auth-card">
                <label className="block text-sm heading">Email<input className="field mt-1" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required /></label>
                <label className="mt-4 block text-sm heading">Password<input className="field mt-1" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required /></label>
                <label className="mt-4 block text-sm heading">Confirm password<input className="field mt-1" type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} required /></label>
                <button className="button-primary mt-5" disabled={form.processing}>Reset password</button>
            </form>
        </GuestLayout>
    );
}
