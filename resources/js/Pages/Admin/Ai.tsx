import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { route } from '../../lib/route';
import { checked, setting } from '../../lib/ui';

export default function Ai({ settings }: any) {
    const form = useForm({
        ai_provider: setting(settings, 'ai_provider', 'openai'),
        openai_api_key: '',
        openai_model: setting(settings, 'openai_model'),
        ai_base_url: setting(settings, 'ai_base_url'),
        ai_guide_enabled: checked(settings, 'ai_guide_enabled', false),
        ai_guide_system_prompt: setting(settings, 'ai_guide_system_prompt'),
        ai_blog_enabled: checked(settings, 'ai_blog_enabled', false),
        ai_blog_avoid_phrases: setting(settings, 'ai_blog_avoid_phrases'),
        ai_blog_insert_words: setting(settings, 'ai_blog_insert_words'),
        ai_blog_style_notes: setting(settings, 'ai_blog_style_notes'),
    });

    return (
        <AdminLayout
            title="AI"
            description="Provider credentials, the in-console guide, and superadmin blog drafting."
            actions={<button form="ai-form" className="button-primary">Save AI</button>}
        >
            <form id="ai-form" onSubmit={(e) => { e.preventDefault(); form.put(route('admin.settings.ai')); }} className="space-y-6">
                <section className="panel grid gap-4 sm:grid-cols-2">
                    <h2 className="section-title sm:col-span-2">Provider</h2>
                    <label className="field-label">Provider
                        <select className="field" name="ai_provider" value={form.data.ai_provider} onChange={(e) => form.setData('ai_provider', e.target.value)}>
                            <option value="openai">OpenAI</option>
                            <option value="groq">Groq</option>
                            <option value="openrouter">OpenRouter</option>
                        </select>
                    </label>
                    <label className="field-label">Model<input className="field" name="openai_model" value={form.data.openai_model} onChange={(e) => form.setData('openai_model', e.target.value)} /></label>
                    <label className="field-label sm:col-span-2">API key<input className="field font-mono text-xs" type="password" name="openai_api_key" placeholder="Saved — leave blank to keep it" value={form.data.openai_api_key} onChange={(e) => form.setData('openai_api_key', e.target.value)} autoComplete="off" /></label>
                    <label className="field-label sm:col-span-2">Base URL<input className="field font-mono text-xs" name="ai_base_url" value={form.data.ai_base_url} onChange={(e) => form.setData('ai_base_url', e.target.value)} placeholder="Optional override" /></label>
                </section>
                <section className="panel space-y-4">
                    <h2 className="section-title">In-console guide</h2>
                    <label className="check-row">
                        <input type="checkbox" name="ai_guide_enabled" checked={form.data.ai_guide_enabled} onChange={(e) => form.setData('ai_guide_enabled', e.target.checked)} />
                        Enable AI guide for signed-in users
                    </label>
                    <label className="field-label">System prompt<textarea className="field font-mono text-xs" name="ai_guide_system_prompt" rows={6} value={form.data.ai_guide_system_prompt} onChange={(e) => form.setData('ai_guide_system_prompt', e.target.value)} /></label>
                </section>
                <section className="panel grid gap-4 sm:grid-cols-2">
                    <h2 className="section-title sm:col-span-2">Blog generation</h2>
                    <label className="check-row sm:col-span-2">
                        <input type="checkbox" name="ai_blog_enabled" checked={form.data.ai_blog_enabled} onChange={(e) => form.setData('ai_blog_enabled', e.target.checked)} />
                        Enable AI blog generation for superadmins
                    </label>
                    <label className="field-label">Avoid phrases<textarea className="field font-mono text-xs" name="ai_blog_avoid_phrases" rows={6} value={form.data.ai_blog_avoid_phrases} onChange={(e) => form.setData('ai_blog_avoid_phrases', e.target.value)} /></label>
                    <label className="field-label">Insert words<textarea className="field font-mono text-xs" name="ai_blog_insert_words" rows={6} value={form.data.ai_blog_insert_words} onChange={(e) => form.setData('ai_blog_insert_words', e.target.value)} /></label>
                    <label className="field-label sm:col-span-2">Style notes<textarea className="field" name="ai_blog_style_notes" rows={4} value={form.data.ai_blog_style_notes} onChange={(e) => form.setData('ai_blog_style_notes', e.target.value)} /></label>
                </section>
            </form>
        </AdminLayout>
    );
}
