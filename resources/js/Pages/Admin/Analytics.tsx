import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { setting } from '../../lib/ui';

export default function Analytics({ settings }: any) {
    const form = useForm({ ga_measurement_id: setting(settings, 'ga_measurement_id') });
    return (
        <AdminLayout
            title="Analytics"
            description="Google Analytics 4 measurement ID injected on public pages."
            actions={<button form="analytics-form" className="button-primary">Save</button>}
        >
            <form id="analytics-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.analytics')); }} className="panel space-y-4">
                <label className="field-label max-w-md">Measurement ID
                    <input className="field font-mono text-xs" name="ga_measurement_id" placeholder="G-XXXXXXXXXX" value={form.data.ga_measurement_id} onChange={(e) => form.setData('ga_measurement_id', e.target.value)} />
                    <span className="field-hint">Leave blank to disable the gtag snippet.</span>
                </label>
            </form>
        </AdminLayout>
    );
}
