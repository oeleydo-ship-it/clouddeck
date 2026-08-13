import MarketingLayout from '../../Layouts/MarketingLayout';
import { MarketingCta } from '../../Components/MarketingCta';
import { MarketingHero } from '../../Components/MarketingHero';
import { BlogCard, BlogPostCard } from '../../Components/BlogCard';
import { Pagination } from '../../Components/Pagination';

type Landing = Record<string, string>;

export default function Index({ posts, landing }: { posts: { data: BlogPostCard[]; links?: any[] } | BlogPostCard[]; landing?: Landing }) {
    const items = Array.isArray(posts) ? posts : (posts.data || []);
    const links = Array.isArray(posts) ? undefined : posts.links;

    return (
        <MarketingLayout>
            <MarketingHero
                eyebrow="Journal"
                title="Blog"
                subtitle="Notes on deploys, servers, and running sites on infrastructure you own."
            />
            <section className="mx-auto max-w-7xl px-5 pb-16">
                {items.length === 0 ? (
                    <p className="muted">No posts yet.</p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {items.map((post) => <BlogCard key={post.id} post={post} />)}
                    </div>
                )}
                <Pagination links={links} />
            </section>
            <MarketingCta landing={landing} />
        </MarketingLayout>
    );
}
