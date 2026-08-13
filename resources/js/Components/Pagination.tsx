import { Link } from '@inertiajs/react';

type LinkItem = { url: string | null; label: string; active: boolean };

export function Pagination({ links }: { links?: LinkItem[] }) {
    if (! links || links.length <= 3) return null;

    return (
        <div className="mt-6 flex flex-wrap gap-1.5">
            {links.map((link, i) => link.url ? (
                <Link key={i} href={link.url} className={`page-link ${link.active ? 'page-link-active' : 'page-link-idle'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
            ) : (
                <span key={i} className="page-link muted" dangerouslySetInnerHTML={{ __html: link.label }} />
            ))}
        </div>
    );
}
