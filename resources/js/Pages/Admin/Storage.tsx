import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { setting } from '../../lib/ui';

export default function Storage({ settings, objectStorage, databaseBackupDisk, providerHint }: any) {
    const stored = objectStorage || {};
    const form = useForm({
        object_storage_provider: stored.provider || 'digitalocean',
        object_storage_key: '',
        object_storage_secret: '',
        object_storage_region: stored.region || setting(settings, 'object_storage_region'),
        object_storage_bucket: stored.bucket || setting(settings, 'object_storage_bucket'),
        object_storage_endpoint: stored.endpoint || setting(settings, 'object_storage_endpoint'),
        object_storage_url: stored.url || setting(settings, 'object_storage_url'),
        object_storage_path_style: Boolean(stored.path_style),
    });
    const price = useForm({ os_backup_gb_price: ((Number(setting(settings, 'os_backup_gb_price_cents', '100')) || 100) / 100).toString() });

    return (
        <AdminLayout
            title="Storage"
            description="S3-compatible object storage for backups, plus the OS backup add-on price."
            actions={<button form="storage-form" className="button-primary">Save storage</button>}
        >
            <form id="storage-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.object-storage')); }} className="panel grid gap-4 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <h2 className="section-title">Object storage</h2>
                    {providerHint && <p className="field-hint">{providerHint}</p>}
                </div>
                <label className="field-label">Provider
                    <select className="field" value={form.data.object_storage_provider} onChange={(e) => form.setData('object_storage_provider', e.target.value)}>
                        <option value="digitalocean">DigitalOcean Spaces</option>
                        <option value="hetzner">Hetzner</option>
                        <option value="wasabi">Wasabi</option>
                        <option value="custom">Custom S3</option>
                    </select>
                </label>
                <label className="field-label">Region<input className="field" name="object_storage_region" placeholder="nyc3" value={form.data.object_storage_region} onChange={(e) => form.setData('object_storage_region', e.target.value)} /></label>
                <label className="field-label">Access key<input className="field font-mono text-xs" name="object_storage_key" placeholder={stored.configured ? 'Saved — leave blank to keep it' : 'Access key'} value={form.data.object_storage_key} onChange={(e) => form.setData('object_storage_key', e.target.value)} autoComplete="off" /></label>
                <label className="field-label">Secret<input className="field font-mono text-xs" type="password" name="object_storage_secret" placeholder={stored.configured ? 'Saved — leave blank to keep it' : 'Secret'} value={form.data.object_storage_secret} onChange={(e) => form.setData('object_storage_secret', e.target.value)} autoComplete="off" /></label>
                <label className="field-label">Bucket<input className="field" name="object_storage_bucket" value={form.data.object_storage_bucket} onChange={(e) => form.setData('object_storage_bucket', e.target.value)} /></label>
                <label className="field-label">Public URL<input className="field font-mono text-xs" name="object_storage_url" value={form.data.object_storage_url} onChange={(e) => form.setData('object_storage_url', e.target.value)} /></label>
                <label className="field-label sm:col-span-2">Endpoint<input className="field font-mono text-xs" name="object_storage_endpoint" placeholder="https://nyc3.digitaloceanspaces.com" value={form.data.object_storage_endpoint} onChange={(e) => form.setData('object_storage_endpoint', e.target.value)} /></label>
                <label className="check-row sm:col-span-2">
                    <input type="checkbox" checked={form.data.object_storage_path_style} onChange={(e) => form.setData('object_storage_path_style', e.target.checked)} />
                    Use path-style URLs
                </label>
            </form>
            <form onSubmit={(e) => { e.preventDefault(); price.put(route('admin.settings.os-backup-pricing')); }} className="panel space-y-4">
                <h2 className="section-title">OS backup storage pricing</h2>
                <label className="field-label max-w-xs">USD per GB
                    <input className="field" value={price.data.os_backup_gb_price} onChange={(e) => price.setData('os_backup_gb_price', e.target.value)} />
                    <span className="field-hint">Billed to customers who add snapshot capacity.</span>
                </label>
                <button className="button-primary">Save</button>
            </form>
            <p className="text-sm muted">Database backup disk: {databaseBackupDisk || 'local'}</p>
        </AdminLayout>
    );
}
