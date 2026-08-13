import { Link } from '@inertiajs/react';
import { route } from '../lib/route';

export type BlogPostCard = {
    id: number | string;
    title: string;
    slug: string;
    excerpt?: string | null;
    published_at?: string | null;
    cover_url?: string | null;
    author?: { name: string } | null;
};

function published(value?: string | null): string {
    if (! value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function BlogCard({ post }: { post: BlogPostCard }) {
    return (
        <Link href={route('blog.show', post.slug)} className="panel panel-interactive flex h-full flex-col overflow-hidden !p-0">
            {post.cover_url && (
                <img src={post.cover_url} alt="" className="h-40 w-full object-cover" />
            )}
            <div className="flex flex-1 flex-col p-5">
                <p className="text-xs muted">{[published(post.published_at), post.author?.name].filter(Boolean).join(' · ')}</p>
                <h3 className="mt-2 font-semibold tracking-[-0.02em] heading">{post.title}</h3>
                {post.excerpt && <p className="mt-2 flex-1 text-sm leading-relaxed muted">{post.excerpt}</p>}
            </div>
        </Link>
    );
}
