type Props = {
    eyebrow: string;
    title: string;
    subtitle: string;
};

export function MarketingHero({ eyebrow, title, subtitle }: Props) {
    return (
        <section className="landing-hero">
            <div className="landing-hero-wash" aria-hidden="true" />
            <div className="landing-hero-grid" aria-hidden="true" />
            <div className="relative mx-auto max-w-3xl px-5 py-16 text-center md:py-24">
                <p className="page-eyebrow">{eyebrow}</p>
                <h1 className="mt-4 font-display text-4xl font-extrabold leading-[1.08] tracking-[-0.04em] text-slate-950 sm:text-5xl dark:text-white">{title}</h1>
                <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-slate-600 dark:text-zinc-300">{subtitle}</p>
            </div>
        </section>
    );
}
