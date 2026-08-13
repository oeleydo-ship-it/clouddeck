import { Link, useForm, usePage } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';
import { GoogleButton } from '../../Components/GoogleButton';
import { PageProps } from '../../types';
import { route } from '../../lib/route';

export default function Register({ googleAuthEnabled, googleButtonLabel, googleRedirect }: { googleAuthEnabled: boolean; googleButtonLabel?: string | null; googleRedirect?: string | null }) {
    const { errors } = usePage<PageProps>().props;
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

    return (
        <GuestLayout>
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('register')); }} className="auth-card">
                <h1 className="text-2xl font-semibold tracking-[-0.03em] heading">Create your account</h1>
                <p className="mt-2 text-sm muted">Deploy and operate your servers from one place.</p>
                {Object.values(errors).length > 0 && <p className="mt-4 text-sm text-rose-600 dark:text-rose-300">{Object.values(errors)[0]}</p>}
                {([['name', 'Name', 'text'], ['email', 'Email', 'email'], ['password', 'Password', 'password'], ['password_confirmation', 'Confirm password', 'password']] as const).map(([name, label, type]) => (
                    <label key={name} className="mt-4 block text-sm heading">{label}
                        <input className="field" type={type} value={(form.data as Record<string, string>)[name]} onChange={(e) => form.setData(name, e.target.value)} required />
                    </label>
                ))}
                <button className="button-primary mt-6 w-full" disabled={form.processing}>Create account</button>
                <GoogleButton enabled={googleAuthEnabled} label={googleButtonLabel} href={googleRedirect} />
                <p className="mt-5 text-center text-sm muted">Already have an account? <Link href={route('login')} className="link-action">Sign in</Link></p>
            </form>
        </GuestLayout>
    );
}
