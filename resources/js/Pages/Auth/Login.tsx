import { Link, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';
import { GoogleButton } from '../../Components/GoogleButton';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

export default function Login({ googleAuthEnabled, googleButtonLabel, googleRedirect, passwordRequestHref }: { googleAuthEnabled: boolean; googleButtonLabel?: string | null; googleRedirect?: string | null; passwordRequestHref?: string }) {
    const { flash, errors } = usePage<PageProps>().props;
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <GuestLayout>
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('login')); }} className="auth-card">
                <h1 className="text-2xl font-semibold tracking-[-0.03em] heading">Welcome back</h1>
                <p className="mt-2 text-sm muted">Sign in to your infrastructure.</p>
                {flash.status && <p className="flash-success mt-4">{flash.status}</p>}
                {errors.email && <p className="mt-4 text-sm text-rose-600 dark:text-rose-300">{errors.email}</p>}
                <label className="mt-7 block text-sm heading">Email<input className="field" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required autoFocus /></label>
                <label className="mt-4 block text-sm heading">Password<input className="field" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required /></label>
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <label className="flex items-center gap-2 muted"><input type="checkbox" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} />Remember me</label>
                    <Link href={passwordRequestHref || route('password.request')} className="link-action">Forgot your password?</Link>
                </div>
                <button className="button-primary mt-6 w-full" disabled={form.processing}>Sign in</button>
                <GoogleButton enabled={googleAuthEnabled} label={googleButtonLabel} href={googleRedirect} />
                <p className="mt-5 text-center text-sm muted">New here? <Link href={route('register')} className="link-action">Create account</Link></p>
            </form>
        </GuestLayout>
    );
}
