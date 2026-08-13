import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { setting } from '../../lib/ui';

export default function Seo({ settings }: any) {
    const form = useForm({
        seo_default_title: setting(settings, 'seo_default_title'),
        seo_title_template: setting(settings, 'seo_title_template', '{page} | {site}'),
        seo_default_description: setting(settings, 'seo_default_description'),
        seo_keywords: setting(settings, 'seo_keywords'),
        seo_og_image: setting(settings, 'seo_og_image'),
        seo_robots: setting(settings, 'seo_robots', 'index,follow'),
        seo_home_title: setting(settings, 'seo_home_title'),
        seo_home_description: setting(settings, 'seo_home_description'),
        seo_home_og_image: setting(settings, 'seo_home_og_image'),
        seo_robots_txt: setting(settings, 'seo_robots_txt'),
    });

    return (
        <AdminLayout
            title="SEO"
            description="Default metadata, homepage overrides, and robots.txt."
            actions={<button form="seo-form" className="button-primary">Save SEO</button>}
        >
            <form id="seo-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.seo')); }} className="space-y-6">
                <section className="panel grid gap-4 sm:grid-cols-2">
                    <h2 className="section-title sm:col-span-2">Defaults</h2>
                    <label className="field-label">Default title<input className="field" name="seo_default_title" value={form.data.seo_default_title} onChange={(e) => form.setData('seo_default_title', e.target.value)} /></label>
                    <label className="field-label">Title template<input className="field font-mono text-xs" name="seo_title_template" value={form.data.seo_title_template} onChange={(e) => form.setData('seo_title_template', e.target.value)} /></label>
                    <label className="field-label sm:col-span-2">Default meta description<textarea className="field" name="seo_default_description" rows={3} value={form.data.seo_default_description} onChange={(e) => form.setData('seo_default_description', e.target.value)} /></label>
                    <label className="field-label">Keywords<input className="field" name="seo_keywords" value={form.data.seo_keywords} onChange={(e) => form.setData('seo_keywords', e.target.value)} /></label>
                    <label className="field-label">Robots (meta)<input className="field" name="seo_robots" value={form.data.seo_robots} onChange={(e) => form.setData('seo_robots', e.target.value)} /></label>
                    <label className="field-label sm:col-span-2">Open Graph image URL<input className="field" name="seo_og_image" value={form.data.seo_og_image} onChange={(e) => form.setData('seo_og_image', e.target.value)} /></label>
                </section>
                <section className="panel grid gap-4 sm:grid-cols-2">
                    <h2 className="section-title sm:col-span-2">Homepage</h2>
                    <label className="field-label sm:col-span-2">Home title<input className="field" name="seo_home_title" value={form.data.seo_home_title} onChange={(e) => form.setData('seo_home_title', e.target.value)} /></label>
                    <label className="field-label sm:col-span-2">Home description<textarea className="field" name="seo_home_description" rows={3} value={form.data.seo_home_description} onChange={(e) => form.setData('seo_home_description', e.target.value)} /></label>
                    <label className="field-label sm:col-span-2">Home Open Graph image<input className="field" name="seo_home_og_image" value={form.data.seo_home_og_image} onChange={(e) => form.setData('seo_home_og_image', e.target.value)} /></label>
                </section>
                <section className="panel space-y-3">
                    <h2 className="section-title">robots.txt</h2>
                    <label className="field-label">Full robots.txt body<textarea className="field font-mono text-xs" name="seo_robots_txt" rows={8} value={form.data.seo_robots_txt} onChange={(e) => form.setData('seo_robots_txt', e.target.value)} /></label>
                </section>
            </form>
        </AdminLayout>
    );
}
