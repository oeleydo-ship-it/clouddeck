import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { setting } from '../../lib/ui';

export default function Webmaster({ settings }: any) {
    const form = useForm({ gsc_verification: setting(settings, 'gsc_verification') });
    return (
        <AdminLayout
            title="Webmaster"
            description="Google Search Console HTML tag verification token."
            actions={<button form="webmaster-form" className="button-primary">Save</button>}
        >
            <form id="webmaster-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.webmaster')); }} className="panel space-y-4">
                <label className="field-label">Verification token
                    <input className="field font-mono text-xs" name="gsc_verification" value={form.data.gsc_verification} onChange={(e) => form.setData('gsc_verification', e.target.value)} placeholder="google-site-verification=…" />
                    <span className="field-hint">Rendered as a meta tag on public pages.</span>
                </label>
            </form>
        </AdminLayout>
    );
}
