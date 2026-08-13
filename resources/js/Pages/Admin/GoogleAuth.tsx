import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { checked, setting } from '../../lib/ui';

export default function GoogleAuth({ settings, callbackUrl, enableLabel, secretSaved, idSaved, secretPlaceholder, idPlaceholder }: any) {
    const form = useForm({
        google_auth_enabled: checked(settings, 'google_auth_enabled', false),
        google_client_id: setting(settings, 'google_client_id'),
        google_client_secret: '',
    });

    return (
        <AdminLayout
            title="Google Auth"
            description="Sign-in with Google for customers. Paste OAuth credentials from Google Cloud."
            actions={<button form="google-auth-form" className="button-primary">Save Google Auth</button>}
        >
            <form id="google-auth-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.google-auth')); }} className="panel space-y-5">
                <label className="check-row">
                    <input type="checkbox" name="google_auth_enabled" checked={form.data.google_auth_enabled} onChange={(e) => form.setData('google_auth_enabled', e.target.checked)} />
                    {enableLabel || 'Enable Google sign-in'}
                </label>
                <p className="text-sm muted">Authorized redirect URI: <code className="break-all font-mono text-xs heading">{callbackUrl || `${typeof window !== 'undefined' ? window.location.origin : ''}/auth/google/callback`}</code></p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="field-label sm:col-span-2">Client ID
                        <input className="field font-mono text-xs" name="google_client_id" value={form.data.google_client_id} onChange={(e) => form.setData('google_client_id', e.target.value)} placeholder={idPlaceholder || (idSaved ? 'Using .env — paste to store in settings' : 'xxxx.apps.googleusercontent.com')} />
                    </label>
                    <label className="field-label sm:col-span-2">Client secret
                        <input className="field font-mono text-xs" type="password" name="google_client_secret" value={form.data.google_client_secret} onChange={(e) => form.setData('google_client_secret', e.target.value)} placeholder={secretPlaceholder || (secretSaved ? 'Saved — leave blank to keep it' : 'GOCSPX-...')} autoComplete="off" />
                    </label>
                </div>
            </form>
        </AdminLayout>
    );
}
