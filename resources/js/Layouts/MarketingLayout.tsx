import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useState } from 'react';
import { PageProps } from '../types';
import { route } from '../lib/route';

export default function MarketingLayout({ children }: PropsWithChildren) {
    const { branding, auth, flash, chrome, supportEmail } = usePage<PageProps>().props;
    const [menu, setMenu] = useState(false);
    const [dark, setDark] = useState(() => {
        try { return localStorage.theme === 'dark'; } catch { return false; }
    });
    const nav = [
        { label: 'Home', href: route('home') },
        { label: 'Features', href: route('features') },
        { label: 'Pricing', href: route('home') + '#pricing' },
        { label: 'Use cases', href: route('use-cases') },
        { label: 'About', href: route('about') },
        { label: 'Blog', href: route('blog') },
        { label: 'Contact', href: route('contact') },
    ];
    const productLinks = [
        { label: 'Features', href: route('features') },
        { label: 'Pricing', href: route('home') + '#pricing' },
        { label: 'Use cases', href: route('use-cases') },
        { label: 'How it works', href: route('home') + '#how-it-works' },
    ];
    const resourceLinks = [
        { label: 'Blog', href: route('blog') },
        { label: 'Contact', href: route('contact') },
        ...(auth.user ? [{ label: 'Documentation', href: route('docs') }] : []),
    ];
    const companyLinks = [
        { label: 'About', href: route('about') },
        { label: 'Blog', href: route('blog') },
        { label: 'Contact', href: route('contact') },
    ];

    const toggleDark = () => {
        const next = ! dark;
        setDark(next);
        document.documentElement.classList.toggle('dark', next);
        try { localStorage.theme = next ? 'dark' : 'light'; } catch { /* */ }
    };

    return (
        <div className="min-h-screen bg-[#f6f5f2] dark:bg-zinc-950">
            <header className="marketing-header">
                <div className="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-5">
                    <Link href={route('home')} className="font-display text-[15px] font-semibold tracking-[-0.02em] heading">
                        {branding.logo_url
                            ? <img src={branding.logo_url} alt={branding.name} className="h-7 w-auto" />
                            : branding.name}
                    </Link>
                    <nav className="hidden items-center gap-1 text-sm lg:flex" aria-label="Primary">
                        {nav.map((item) => (
                            <Link key={item.href + item.label} href={item.href} className="nav-link !px-2.5 !py-1.5">{item.label}</Link>
                        ))}
                    </nav>
                    <div className="flex items-center gap-1.5">
                        <button type="button" onClick={toggleDark} className="icon-button" aria-label="Toggle theme">
                            {dark ? (
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-4"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
                            ) : (
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-4"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5Z" /></svg>
                            )}
                        </button>
                        {auth.user ? (
                            <Link href={route('dashboard')} className="button-primary !px-3.5 !py-1.5 text-sm">{chrome.open_console}</Link>
                        ) : (
                            <>
                                <Link href={route('login')} className="button-ghost hidden sm:inline-flex">Sign in</Link>
                                <Link href={route('register')} className="button-primary !px-3.5 !py-1.5 text-sm">Get started</Link>
                            </>
                        )}
                        <button type="button" className="icon-button lg:hidden" onClick={() => setMenu(! menu)} aria-label={menu ? 'Close menu' : 'Open menu'} aria-expanded={menu}>
                            {menu ? (
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-5"><path d="M6 6l12 12M18 6L6 18" /></svg>
                            ) : (
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-5"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
                            )}
                        </button>
                    </div>
                </div>
                {menu && (
                    <div className="border-t border-slate-200/80 px-5 py-3 lg:hidden dark:border-white/10">
                        {nav.map((item) => (
                            <Link key={item.href + item.label} href={item.href} className="block py-2 text-sm heading" onClick={() => setMenu(false)}>{item.label}</Link>
                        ))}
                        {! auth.user && (
                            <Link href={route('login')} className="mt-2 block py-2 text-sm heading" onClick={() => setMenu(false)}>Sign in</Link>
                        )}
                    </div>
                )}
            </header>
            {flash.status && (
                <div className="mx-auto mt-6 max-w-3xl px-5">
                    <div className="flash-success">{flash.status}</div>
                </div>
            )}
            {children}
            <footer className="marketing-footer">
                <div className="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p className="font-display text-[15px] font-semibold tracking-[-0.02em] heading">{branding.name}</p>
                        <p className="mt-3 max-w-xs text-sm leading-relaxed muted">Laravel control plane for Ubuntu servers you own — deploy Laravel, WordPress, and React without giving up the VPS.</p>
                    </div>
                    <div>
                        <p className="landing-kicker">Product</p>
                        <div className="mt-3 flex flex-col gap-2 text-sm">
                            {productLinks.map((item) => <Link key={item.label} href={item.href} className="muted transition-colors duration-150 hover:text-slate-900 dark:hover:text-white">{item.label}</Link>)}
                        </div>
                    </div>
                    <div>
                        <p className="landing-kicker">Resources</p>
                        <div className="mt-3 flex flex-col gap-2 text-sm">
                            {resourceLinks.map((item) => <Link key={item.label} href={item.href} className="muted transition-colors duration-150 hover:text-slate-900 dark:hover:text-white">{item.label}</Link>)}
                        </div>
                    </div>
                    <div>
                        <p className="landing-kicker">Company</p>
                        <div className="mt-3 flex flex-col gap-2 text-sm">
                            {companyLinks.map((item) => <Link key={item.label} href={item.href} className="muted transition-colors duration-150 hover:text-slate-900 dark:hover:text-white">{item.label}</Link>)}
                            {auth.user
                                ? <Link href={route('dashboard')} className="muted transition-colors duration-150 hover:text-slate-900 dark:hover:text-white">{chrome.open_console}</Link>
                                : (
                                    <>
                                        <Link href={route('login')} className="muted transition-colors duration-150 hover:text-slate-900 dark:hover:text-white">Sign in</Link>
                                        <Link href={route('register')} className="muted transition-colors duration-150 hover:text-slate-900 dark:hover:text-white">Get started</Link>
                                    </>
                                )}
                            {supportEmail && (
                                <a href={`mailto:${supportEmail}`} className="muted transition-colors duration-150 hover:text-slate-900 dark:hover:text-white">{supportEmail}</a>
                            )}
                        </div>
                    </div>
                </div>
                <div className="border-t border-slate-200/80 dark:border-white/[0.07]">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-5 py-5 text-sm muted">
                        <p>&copy; {new Date().getFullYear()} {branding.name}.</p>
                        <p>Ubuntu servers · nginx · Let’s Encrypt</p>
                    </div>
                </div>
            </footer>
        </div>
    );
}
