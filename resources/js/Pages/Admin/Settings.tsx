import { useForm, usePage } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { PageProps } from '../../types';
import { route } from '../../lib/route';
import { checked, setting } from '../../lib/ui';

export default function Settings({ settings }: any) {
    const { branding } = usePage<PageProps>().props;
    const form = useForm({
        platform_name: setting(settings, 'platform_name', branding.name),
        support_email: setting(settings, 'support_email'),
        maintenance_banner: setting(settings, 'maintenance_banner'),
        registration_enabled: checked(settings, 'registration_enabled', true),
        email_verification_required: checked(settings, 'email_verification_required', false),
        public_site_enabled: checked(settings, 'public_site_enabled', true),
        dns_enabled: checked(settings, 'dns_enabled', true),
        staging_sites_enabled: checked(settings, 'staging_sites_enabled', false),
        allow_impersonate_admins: checked(settings, 'allow_impersonate_admins', false),
    });
    const logo = useForm({ logo: null as File | null });
    const favicon = useForm({ favicon: null as File | null });
    const display = useForm({ logo_image_only: branding.logo_image_only });

    return (
        <AdminLayout
            title="Settings"
            description="Platform-wide configuration applied to every customer."
            actions={<button form="general-settings" className="button-primary">Save general settings</button>}
        >
            <form id="general-settings" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.update')); }} className="panel space-y-5">
                <h2 className="section-title">General information</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="field-label">Platform name<input className="field" name="platform_name" value={form.data.platform_name} onChange={(e) => form.setData('platform_name', e.target.value)} /></label>
                    <label className="field-label">Support email<input className="field" type="email" name="support_email" value={form.data.support_email} onChange={(e) => form.setData('support_email', e.target.value)} /></label>
                    <label className="field-label sm:col-span-2">Maintenance banner<textarea className="field" name="maintenance_banner" rows={3} value={form.data.maintenance_banner} onChange={(e) => form.setData('maintenance_banner', e.target.value)} /></label>
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                    <label className="check-row"><input type="checkbox" name="registration_enabled" checked={form.data.registration_enabled} onChange={(e) => form.setData('registration_enabled', e.target.checked)} />Public registration enabled</label>
                    <label className="check-row"><input type="checkbox" name="email_verification_required" checked={form.data.email_verification_required} onChange={(e) => form.setData('email_verification_required', e.target.checked)} />Require email verification</label>
                    <label className="check-row"><input type="checkbox" name="public_site_enabled" checked={form.data.public_site_enabled} onChange={(e) => form.setData('public_site_enabled', e.target.checked)} />Public marketing pages enabled</label>
                    <label className="check-row"><input type="checkbox" name="dns_enabled" checked={form.data.dns_enabled} onChange={(e) => form.setData('dns_enabled', e.target.checked)} />DNS management enabled</label>
                    <label className="check-row"><input type="checkbox" name="staging_sites_enabled" checked={form.data.staging_sites_enabled} onChange={(e) => form.setData('staging_sites_enabled', e.target.checked)} />Staging sites enabled</label>
                    <label className="check-row"><input type="checkbox" name="allow_impersonate_admins" checked={form.data.allow_impersonate_admins} onChange={(e) => form.setData('allow_impersonate_admins', e.target.checked)} />Allow impersonating other super admins</label>
                </div>
            </form>
            <section className="panel space-y-5">
                <h2 className="section-title">Logo</h2>
                {branding.logo_url && <img src={branding.logo_url} alt={branding.name} className="h-10 w-auto object-contain" />}
                <form onSubmit={(e) => { e.preventDefault(); logo.post(route('admin.settings.logo'), { forceFormData: true }); }} className="flex flex-wrap items-center gap-3">
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" onChange={(e) => logo.setData('logo', e.target.files?.[0] || null)} />
                    <button className="button-primary">Upload</button>
                </form>
                {branding.logo_url && (
                    <form onSubmit={(e) => { e.preventDefault(); logo.delete(route('admin.settings.logo.destroy')); }}>
                        <button className="button-secondary !text-rose-600">Remove</button>
                    </form>
                )}
                <form onSubmit={(e) => { e.preventDefault(); display.put(route('admin.settings.branding')); }} className="space-y-3">
                    <label className="check-row">
                        <input type="checkbox" name="logo_image_only" checked={display.data.logo_image_only} onChange={(e) => display.setData('logo_image_only', e.target.checked)} disabled={! branding.logo_url} />
                        Show logo image only
                    </label>
                    <button className="button-secondary" disabled={! branding.logo_url}>Save logo display</button>
                </form>
            </section>
            <section className="panel space-y-5">
                <h2 className="section-title">Favicon</h2>
                <p className="field-hint">Shown in the browser tab. Prefer a square .ico; PNG, JPEG, and SVG are also accepted.</p>
                {branding.favicon_url && <img src={branding.favicon_url} alt="" className="h-8 w-8 object-contain" />}
                <form onSubmit={(e) => { e.preventDefault(); favicon.post(route('admin.settings.favicon'), { forceFormData: true }); }} className="flex flex-wrap items-center gap-3">
                    <input type="file" name="favicon" accept=".ico,image/x-icon,image/vnd.microsoft.icon,image/png,image/jpeg,image/svg+xml,image/webp" onChange={(e) => favicon.setData('favicon', e.target.files?.[0] || null)} />
                    <button className="button-primary">Upload</button>
                </form>
                {branding.favicon_url && (
                    <form onSubmit={(e) => { e.preventDefault(); favicon.delete(route('admin.settings.favicon.destroy')); }}>
                        <button className="button-secondary !text-rose-600">Remove</button>
                    </form>
                )}
            </section>
        </AdminLayout>
    );
}
