import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { setting } from '../../lib/ui';

const GROUPS: { title: string; hint: string; fields: { key: string; label: string; rows?: number }[] }[] = [
    {
        title: 'Hero',
        hint: 'Appears at the top of the public homepage.',
        fields: [
            { key: 'landing_hero_eyebrow', label: 'Eyebrow' },
            { key: 'landing_hero_headline', label: 'Headline' },
            { key: 'landing_hero_subcopy', label: 'Subcopy', rows: 3 },
            { key: 'landing_hero_cta_primary', label: 'Primary button (register)' },
            { key: 'landing_hero_cta_secondary', label: 'Secondary button (how it works)' },
            { key: 'landing_hero_microcopy', label: 'Microcopy under buttons' },
        ],
    },
    {
        title: 'How it works',
        hint: 'Section intro plus the three step cards on the homepage.',
        fields: [
            { key: 'landing_steps_eyebrow', label: 'Eyebrow' },
            { key: 'landing_steps_headline', label: 'Headline' },
            { key: 'landing_steps_subcopy', label: 'Subcopy', rows: 3 },
            { key: 'landing_step_1_title', label: 'Step 1 title' },
            { key: 'landing_step_1_body', label: 'Step 1 body', rows: 2 },
            { key: 'landing_step_2_title', label: 'Step 2 title' },
            { key: 'landing_step_2_body', label: 'Step 2 body', rows: 2 },
            { key: 'landing_step_3_title', label: 'Step 3 title' },
            { key: 'landing_step_3_body', label: 'Step 3 body', rows: 2 },
        ],
    },
    {
        title: 'Closing CTA',
        hint: 'Used on the homepage and repeated at the bottom of About, Features, Use cases, Contact, and Blog.',
        fields: [
            { key: 'landing_cta_headline', label: 'Headline' },
            { key: 'landing_cta_subcopy', label: 'Subcopy', rows: 3 },
            { key: 'landing_cta_button', label: 'Button (register)' },
        ],
    },
];

export default function Pages({ settings }: any) {
    const keys = GROUPS.flatMap((group) => group.fields.map((field) => field.key));
    const initial = Object.fromEntries(keys.map((key) => [key, setting(settings, key)]));
    const form = useForm(initial);

    return (
        <AdminLayout
            title="Pages"
            description="Public marketing copy. Empty fields fall back to the built-in defaults on the live site."
            actions={<button form="landing-copy" className="button-primary">Save landing copy</button>}
        >
            <form id="landing-copy" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.landing')); }} className="space-y-6">
                {GROUPS.map((group) => (
                    <section key={group.title} className="panel grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <h2 className="section-title">{group.title}</h2>
                            <p className="field-hint">{group.hint}</p>
                        </div>
                        {group.fields.map((field) => (
                            <label key={field.key} className={`field-label ${field.rows || field.key.includes('headline') || field.key.includes('subcopy') || field.key.includes('title') ? 'sm:col-span-2' : ''}`}>
                                {field.label}
                                {field.rows
                                    ? <textarea className="field" name={field.key} rows={field.rows} value={(form.data as any)[field.key]} onChange={(e) => form.setData(field.key as any, e.target.value)} placeholder="Built-in default when blank" />
                                    : <input className="field" name={field.key} value={(form.data as any)[field.key]} onChange={(e) => form.setData(field.key as any, e.target.value)} placeholder="Built-in default when blank" />}
                            </label>
                        ))}
                    </section>
                ))}
            </form>
        </AdminLayout>
    );
}
