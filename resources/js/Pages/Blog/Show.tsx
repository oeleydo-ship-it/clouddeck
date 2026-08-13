import { Link } from '@inertiajs/react';
import MarketingLayout from '../../Layouts/MarketingLayout';
import { MarketingCta } from '../../Components/MarketingCta';
import { BlogCard, BlogPostCard } from '../../Components/BlogCard';
import { route } from '../../lib/route';

type Landing = Record<string, string>;
type Post = BlogPostCard & { body: string; reading_time?: number };

function paragraphs(body: string): string[] {
    return body.replace(/\r\n/g, '\n').split(/\n{2,}/).map((chunk) => chunk.trim()).filter(Boolean);
}

function published(value?: string | null): string {
    if (! value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}

export default function Show({ post, related, landing }: { post: Post; related?: BlogPostCard[]; landing?: Landing }) {
    return (
        <MarketingLayout>
            <article className="marketing-page">
                <div className="landing-hero-wash opacity-50" aria-hidden="true" />
                <div className="relative mx-auto max-w-3xl px-5 py-16 md:py-20">
                    <Link href={route('blog')} className="text-sm muted hover:text-slate-900 dark:hover:text-white">← Blog</Link>
                    <h1 className="page-title mt-4">{post.title}</h1>
                    <p className="mt-3 text-sm muted">
                        {[published(post.published_at), post.author?.name, post.reading_time ? `${post.reading_time} min read` : ''].filter(Boolean).join(' · ')}
                    </p>
                    {post.cover_url && <img src={post.cover_url} alt="" className="mt-8 w-full rounded-2xl object-cover" />}
                    <div className="mt-8 space-y-4 text-[16px] leading-7 text-slate-700 dark:text-zinc-300">
                        {paragraphs(post.body).map((chunk, index) => (
                            <p key={index}>{chunk}</p>
                        ))}
                    </div>
                </div>
            </article>
            {(related || []).length > 0 && (
                <section className="mx-auto max-w-7xl px-5 pb-16">
                    <h2 className="page-title">More from the journal</h2>
                    <div className="mt-6 grid gap-4 md:grid-cols-3">
                        {related!.map((item) => <BlogCard key={item.id} post={item} />)}
                    </div>
                </section>
            )}
            <MarketingCta landing={landing} />
        </MarketingLayout>
    );
}
