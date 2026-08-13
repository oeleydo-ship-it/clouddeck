import { useForm } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';

export default function VerifyEmail() {
    const form = useForm({});

    return (
        <GuestLayout title="Verify your email">
            <div className="auth-card">
                <p className="text-sm muted">Check your inbox for a verification link. You can request another copy if it did not arrive.</p>
                <form onSubmit={(e) => { e.preventDefault(); form.post('/email/verification-notification'); }} className="mt-5">
                    <button className="button-primary" disabled={form.processing}>Resend verification email</button>
                </form>
            </div>
        </GuestLayout>
    );
}
