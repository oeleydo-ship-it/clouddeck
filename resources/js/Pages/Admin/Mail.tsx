import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { setting } from '../../lib/ui';

export default function Mail({ settings }: any) {
    const form = useForm({
        mail_host: setting(settings, 'mail_host'),
        mail_port: setting(settings, 'mail_port', '587'),
        mail_encryption: setting(settings, 'mail_encryption', 'tls'),
        mail_username: setting(settings, 'mail_username'),
        mail_password: '',
        mail_from_address: setting(settings, 'mail_from_address'),
        mail_from_name: setting(settings, 'mail_from_name'),
    });
    const test = useForm({ test_email: '' });

    return (
        <AdminLayout
            title="SMTP"
            description="Outbound mail for operational alerts, invites, and password resets."
            actions={<button form="mail-form" className="button-primary">Save mail</button>}
        >
            <form id="mail-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.mail')); }} className="panel grid gap-4 sm:grid-cols-2">
                <h2 className="section-title sm:col-span-2">Server</h2>
                <label className="field-label">Host<input className="field" name="mail_host" value={form.data.mail_host} onChange={(e) => form.setData('mail_host', e.target.value)} /></label>
                <label className="field-label">Port<input className="field" name="mail_port" value={form.data.mail_port} onChange={(e) => form.setData('mail_port', e.target.value)} /></label>
                <label className="field-label">Encryption
                    <select className="field" name="mail_encryption" value={form.data.mail_encryption} onChange={(e) => form.setData('mail_encryption', e.target.value)}>
                        <option value="tls">tls</option>
                        <option value="ssl">ssl</option>
                        <option value="none">none</option>
                    </select>
                </label>
                <label className="field-label">Username<input className="field" name="mail_username" value={form.data.mail_username} onChange={(e) => form.setData('mail_username', e.target.value)} /></label>
                <label className="field-label sm:col-span-2">Password<input className="field" type="password" name="mail_password" placeholder="Saved — leave blank to keep it" value={form.data.mail_password} onChange={(e) => form.setData('mail_password', e.target.value)} autoComplete="new-password" /></label>
                <label className="field-label">From address<input className="field" name="mail_from_address" value={form.data.mail_from_address} onChange={(e) => form.setData('mail_from_address', e.target.value)} /></label>
                <label className="field-label">From name<input className="field" name="mail_from_name" value={form.data.mail_from_name} onChange={(e) => form.setData('mail_from_name', e.target.value)} /></label>
            </form>
            <form onSubmit={(e) => { e.preventDefault(); test.post(route('admin.settings.mail.test')); }} className="panel space-y-4">
                <h2 className="section-title">Send a test</h2>
                <div className="flex flex-wrap gap-2">
                    <input className="field mt-0 max-w-sm flex-1" type="email" placeholder="you@example.com" value={test.data.test_email} onChange={(e) => test.setData('test_email', e.target.value)} />
                    <button className="button-secondary">Send test</button>
                </div>
            </form>
        </AdminLayout>
    );
}
