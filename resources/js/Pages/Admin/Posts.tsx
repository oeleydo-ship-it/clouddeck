import { FormEvent, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Pagination } from '../../Components/Pagination';
import { route } from '../../lib/route';
import { items, when } from '../../lib/ui';

const emptyPost = { title: '', slug: '', excerpt: '', meta_title: '', meta_description: '', body: '', cover: null as File | null };

export default function Posts({ posts, aiBlogEnabled }: any) {
    const form = useForm(emptyPost);
    const [editingId, setEditingId] = useState<string | number | null>(null);
    const [topic, setTopic] = useState('');
    const [topics, setTopics] = useState<string[]>([]);
    const [aiError, setAiError] = useState('');
    const rows = items<any>(posts);

    const csrf = () => document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';

    const suggest = async () => {
        setAiError('');
        const res = await fetch(route('admin.posts.ai.suggest'), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ keyword: topic }) });
        const body = await res.json().catch(() => ({}));
        if (! res.ok) {
            setAiError(body.message || 'Could not suggest topics.');
            return;
        }
        setTopics(body.topics || []);
    };

    const generate = async () => {
        setAiError('');
        const res = await fetch(route('admin.posts.ai.generate'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: JSON.stringify({ topic }) });
        const body = await res.json().catch(() => ({}));
        if (! res.ok) {
            setAiError(body.message || 'Could not generate a draft.');
            return;
        }
        if (body.draft) {
            form.setData({ ...form.data, ...body.draft });
        }
    };

    const load = (post: any) => {
        setEditingId(post.id);
        form.setData({
            title: post.title || '',
            slug: post.slug || '',
            excerpt: post.excerpt || '',
            meta_title: post.meta_title || '',
            meta_description: post.meta_description || '',
            body: post.body || '',
            cover: null,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const cancel = () => {
        setEditingId(null);
        form.reset();
        form.setData(emptyPost);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { forceFormData: true, onSuccess: () => cancel() };
        if (editingId) {
            form.patch(route('admin.posts.update', editingId), options);
        } else {
            form.post(route('admin.posts.store'), options);
        }
    };

    return (
        <AdminLayout title="Blog" description="Write posts or generate a draft, then publish from the list.">
            {aiBlogEnabled ? (
                <div className="panel space-y-4">
                    <h2 className="section-title">Generate with AI</h2>
                    <div className="flex flex-wrap gap-2">
                        <input className="field mt-0 min-w-[16rem] flex-1" value={topic} onChange={(e) => setTopic(e.target.value)} placeholder="Topic or keyword" />
                        <button className="button-secondary" type="button" onClick={suggest}>Suggest topics</button>
                        <button className="button-primary" type="button" onClick={generate}>Generate draft</button>
                    </div>
                    {aiError && <p className="text-sm text-rose-600 dark:text-rose-300">{aiError}</p>}
                    {topics.length > 0 && (
                        <ul className="well space-y-1 text-sm">
                            {topics.map((item) => (
                                <li key={item}>
                                    <button type="button" className="link-action" onClick={() => setTopic(item)}>{item}</button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ) : (
                <p className="text-sm muted">Generate with AI — Enable in AI settings</p>
            )}

            <form onSubmit={submit} className="panel grid gap-4 sm:grid-cols-2">
                <div className="flex flex-wrap items-center justify-between gap-3 sm:col-span-2">
                    <h2 className="section-title">{editingId ? 'Edit post' : 'New post'}</h2>
                    {editingId && <button type="button" className="button-ghost" onClick={cancel}>Cancel</button>}
                </div>
                <label className="field-label">Title<input className="field" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required /></label>
                <label className="field-label">Slug<input className="field font-mono text-xs" name="slug" value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} placeholder="auto from title" /></label>
                <label className="field-label sm:col-span-2">Excerpt<textarea className="field min-h-20" name="excerpt" value={form.data.excerpt} onChange={(e) => form.setData('excerpt', e.target.value)} /></label>
                <label className="field-label">Meta title<input className="field" name="meta_title" value={form.data.meta_title} onChange={(e) => form.setData('meta_title', e.target.value)} /></label>
                <label className="field-label">Meta description<input className="field" name="meta_description" value={form.data.meta_description} onChange={(e) => form.setData('meta_description', e.target.value)} /></label>
                <label className="field-label sm:col-span-2">Body<textarea className="field min-h-48" name="body" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required /></label>
                <label className="field-label sm:col-span-2">Cover image
                    <input className="field" type="file" name="cover" accept="image/png,image/jpeg,image/webp" onChange={(e) => form.setData('cover', e.target.files?.[0] || null)} />
                </label>
                <div className="sm:col-span-2"><button className="button-primary">{editingId ? 'Save post' : 'Create post'}</button></div>
            </form>

            <div className="panel-flush">
                {rows.length === 0 && <p className="px-5 py-10 text-center text-sm muted">No posts yet.</p>}
                {rows.map((post: any) => (
                    <div key={post.id} className="data-row flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="font-medium heading">{post.title}</p>
                            <p className="text-xs muted">{post.slug}{post.published_at ? ` · Published ${when(post.published_at)}` : ' · Draft'}</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" className="button-secondary" onClick={() => load(post)}>Edit</button>
                            {post.id && (
                                <button type="button" className="button-secondary" onClick={() => router.patch(route('admin.posts.publish', post.id))}>{post.published_at ? 'Unpublish' : 'Publish'}</button>
                            )}
                            <button type="button" className="button-secondary !text-rose-600" onClick={() => router.delete(route('admin.posts.destroy', post.id))}>Delete</button>
                        </div>
                    </div>
                ))}
            </div>
            <Pagination links={posts.links} />
        </AdminLayout>
    );
}
