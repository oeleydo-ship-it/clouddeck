import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { checked, setting } from '../../lib/ui';

export default function InsertCode({ settings }: any) {
    const form = useForm({
        insert_code_head: setting(settings, 'insert_code_head'),
        insert_code_body: setting(settings, 'insert_code_body'),
        insert_code_on_marketing: checked(settings, 'insert_code_on_marketing', true),
        insert_code_on_console: checked(settings, 'insert_code_on_console', false),
    });

    return (
        <AdminLayout
            title="Insert code"
            description="Raw HTML/JS snippets injected into every matching page. Use for pixels and chat widgets."
            actions={<button form="insert-code-form" className="button-primary">Save</button>}
        >
            <form id="insert-code-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.insert-code')); }} className="space-y-6">
                <section className="panel grid gap-4 lg:grid-cols-2">
                    <label className="field-label">Head
                        <textarea className="field mt-1 min-h-48 font-mono text-xs" name="insert_code_head" rows={10} value={form.data.insert_code_head} onChange={(e) => form.setData('insert_code_head', e.target.value)} />
                    </label>
                    <label className="field-label">Body
                        <textarea className="field mt-1 min-h-48 font-mono text-xs" name="insert_code_body" rows={10} value={form.data.insert_code_body} onChange={(e) => form.setData('insert_code_body', e.target.value)} />
                    </label>
                </section>
                <section className="panel space-y-3">
                    <h2 className="section-title">Where it injects</h2>
                    <label className="check-row">
                        <input type="checkbox" name="insert_code_on_marketing" checked={form.data.insert_code_on_marketing} onChange={(e) => form.setData('insert_code_on_marketing', e.target.checked)} />
                        Inject on marketing and other public pages
                    </label>
                    <label className="check-row">
                        <input type="checkbox" name="insert_code_on_console" checked={form.data.insert_code_on_console} onChange={(e) => form.setData('insert_code_on_console', e.target.checked)} />
                        Inject in the signed-in console (including Admin)
                    </label>
                </section>
            </form>
        </AdminLayout>
    );
}
