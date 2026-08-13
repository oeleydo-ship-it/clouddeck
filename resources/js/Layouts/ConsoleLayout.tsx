import { Link, router, usePage } from '@inertiajs/react';
import { FormEvent, PropsWithChildren, ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { PageProps } from '../types';
import { route } from '../lib/route';
import { AiGuide } from '../Components/AiGuide';
import { isDarkTheme, persistTheme } from '../lib/theme';

type Section = { href: string; label: string; icon: string; match?: string; route?: string; locked?: boolean; admin?: boolean };
type NavGroup = { label: string; items: Section[] };

const CONSOLE_GROUPS: { label: string; matches: string[] }[] = [
    { label: 'Overview', matches: ['dashboard'] },
    { label: 'Infrastructure', matches: ['servers', 'sites'] },
    { label: 'Operations', matches: ['firewall', 'security', 'notifications'] },
    { label: 'Connections', matches: ['cloud-accounts', 'dns', 'ssh-keys'] },
    { label: 'Platform', matches: ['admin'] },
];

const ADMIN_GROUPS: { label: string; labels: string[] }[] = [
    { label: 'Workspace', labels: ['Overview', 'Users'] },
    { label: 'Commerce', labels: ['Plans', 'Managed servers', 'Billing review', 'Payments'] },
    { label: 'Content', labels: ['Blog', 'Pages', 'SEO'] },
    { label: 'Platform', labels: ['Feature flags', 'Storage', 'SMTP', 'Notifications', 'Analytics', 'Webmaster', 'Insert code', 'AI', 'Google Auth', 'Platform services'] },
    { label: 'System', labels: ['Settings', 'Audit'] },
];

function groupNav(sections: Section[], admin: boolean): NavGroup[] {
    const used = new Set<Section>();
    const groups: NavGroup[] = [];

    if (admin) {
        for (const bucket of ADMIN_GROUPS) {
            const items = sections.filter((section) => bucket.labels.includes(section.label));
            items.forEach((item) => used.add(item));
            if (items.length) groups.push({ label: bucket.label, items });
        }
    } else {
        for (const bucket of CONSOLE_GROUPS) {
            const items = sections.filter((section) => section.match && bucket.matches.includes(section.match));
            items.forEach((item) => used.add(item));
            if (items.length) groups.push({ label: bucket.label, items });
        }
    }

    const leftover = sections.filter((section) => ! used.has(section));
    if (leftover.length) groups.push({ label: 'More', items: leftover });

    return groups;
}

function HeaderPopover({
    open,
    anchor,
    onClose,
    widthClass = 'w-56',
    matchWidth = false,
    children,
}: {
    open: boolean;
    anchor: { current: HTMLElement | null };
    onClose: () => void;
    widthClass?: string;
    matchWidth?: boolean;
    children: ReactNode;
}) {
    const panelRef = useRef<HTMLDivElement>(null);
    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;
    const [pos, setPos] = useState({ top: 56, right: 16, left: 16, width: 224 });

    useEffect(() => {
        if (! open) {
            return;
        }

        const place = () => {
            const rect = anchor.current?.getBoundingClientRect();
            if (! rect) {
                return;
            }
            setPos({
                top: rect.bottom + 8,
                right: Math.max(12, window.innerWidth - rect.right),
                left: rect.left,
                width: rect.width,
            });
        };

        place();
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
        const onDoc = (event: MouseEvent) => {
            const target = event.target as Node;
            if (anchor.current?.contains(target) || panelRef.current?.contains(target)) {
                return;
            }
            onCloseRef.current();
        };
        document.addEventListener('mousedown', onDoc);

        return () => {
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
            document.removeEventListener('mousedown', onDoc);
        };
    }, [open, anchor]);

    if (! open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            ref={panelRef}
            className={`menu-panel !fixed !mt-0 z-[80] ${matchWidth ? '!w-auto' : widthClass}`}
            style={matchWidth ? { top: pos.top, left: pos.left, width: pos.width } : { top: pos.top, right: pos.right }}
        >
            {children}
        </div>,
        document.body,
    );
}

export default function ConsoleLayout({ children, crumb }: PropsWithChildren<{ crumb?: string }>) {
    const page = usePage<PageProps>();
    const { auth, branding, publicSiteEnabled, shellAlerts, impersonation, adminNav, consoleNav, chrome, csrf_token } = page.props;
    const user = auth.user!;
    const inAdmin = user.is_super_admin && window.location.pathname.startsWith('/admin');
    const [nav, setNav] = useState(false);
    const [menu, setMenu] = useState(false);
    const [alertsOpen, setAlertsOpen] = useState(false);
    const [q, setQ] = useState('');
    const searchRef = useRef<HTMLInputElement>(null);
    const searchWrapRef = useRef<HTMLDivElement>(null);
    const alertsBtnRef = useRef<HTMLButtonElement>(null);
    const menuBtnRef = useRef<HTMLButtonElement>(null);
    const alerts = Array.isArray(shellAlerts) ? shellAlerts : [];
    const notificationsHref = route('notifications.index');
    const [dark, setDark] = useState(() => isDarkTheme());
    const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

    const sections: Section[] = useMemo(() => {
        if (inAdmin) {
            return (adminNav || []).map((item) => ({ ...item, admin: true }));
        }
        return (consoleNav || []).map((item) => ({ ...item, locked: item.locked ?? false }));
    }, [inAdmin, adminNav, consoleNav]);

    const groups = useMemo(() => groupNav(sections, inAdmin), [sections, inAdmin]);

    const path = window.location.pathname;
    const isActive = (section: Section) => {
        if (section.admin && section.route) {
            if (section.route === 'admin.dashboard') {
                return path === '/admin' || path === '/admin/';
            }
            return path.startsWith('/admin') && path.includes(section.route.replace('admin.', '').replace('.', '/'));
        }
        if (section.match === 'dashboard') return path === '/dashboard';
        if (section.match === 'admin') return path.startsWith('/admin');
        return section.match ? path.startsWith('/' + section.match.replace('*', '')) : path === section.href;
    };

    const current = sections.find(isActive);
    const inAccountArea = path.startsWith('/account') || path.startsWith('/teams');
    const displayCrumb = crumb || current?.label || (path.startsWith('/billing') ? 'Billing' : 'Overview');

    const searchItems = [
        ...sections.map((s) => ({ label: s.label, href: s.href })),
        { label: 'Incidents', href: route('notifications.index') + '?tab=incidents' },
        { label: 'Provision server', href: route('servers.create') },
        { label: 'Add existing server', href: route('servers.custom') },
        { label: 'Add site', href: route('sites.create') },
        { label: 'Account settings', href: '/account' },
        { label: 'Teams', href: '/teams' },
        { label: 'Billing', href: '/billing' },
        { label: 'Documentation', href: route('docs') },
        ...(publicSiteEnabled ? [{ label: 'Contact support', href: route('contact') }] : []),
    ];
    const results = q === '' ? [] : searchItems.filter((i) => i.label.toLowerCase().includes(q.toLowerCase()));

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                searchRef.current?.focus();
            }
            if (e.key === 'Escape') {
                setQ('');
                setMenu(false);
                setAlertsOpen(false);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const toggleDark = () => {
        const next = ! dark;
        setDark(next);
        persistTheme(next);
    };

    const logout = (e: FormEvent) => {
        e.preventDefault();
        router.post('/logout');
    };

    const markNotificationRead = (id?: string) => {
        if (! id) {
            return;
        }
        fetch(route('notifications.read', id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf_token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).catch(() => {});
    };

    const markAllRead = () => {
        fetch(route('notifications.read-all'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf_token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).then(() => router.reload({ only: ['shellAlerts'] })).catch(() => {});
    };

    return (
        <div className="app-shell">
            {nav && <div className="fixed inset-0 z-50 bg-zinc-950/50 lg:hidden" onClick={() => setNav(false)} />}
            <aside className={`app-sidebar ${inAdmin ? 'app-sidebar-admin' : ''} ${nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}>
                <div className={`mb-5 flex items-center px-4 ${branding.logo_image_only && branding.logo_url ? 'justify-center' : 'justify-between'}`}>
                    <Link href={inAdmin ? route('admin.dashboard') : route('dashboard')} className={`sidebar-brand ${branding.logo_image_only && branding.logo_url ? 'w-full justify-center' : ''}`}>
                        {branding.logo_url ? (
                            <img src={branding.logo_url} alt={branding.name} className={`h-8 w-auto max-w-[11rem] object-contain ${branding.logo_image_only ? '' : 'sidebar-brand-mark'}`} />
                        ) : (
                            <span className={`sidebar-brand-mark font-display text-sm font-bold text-white ${inAdmin ? 'bg-amber-500' : 'bg-sky-500'}`}>{branding.name.slice(0, 1).toUpperCase()}</span>
                        )}
                        {! branding.logo_image_only && (
                            <span className="min-w-0" data-brand-name>
                                <span className="sidebar-brand-name">{branding.name}</span>
                                <span className="sidebar-brand-subtitle">{inAdmin ? 'Super administrator' : 'Cloud management'}</span>
                            </span>
                        )}
                    </Link>
                    <button type="button" onClick={() => setNav(false)} className="icon-button !text-zinc-400 hover:!bg-white/10 hover:!text-white lg:hidden" aria-label="Close navigation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-4"><path d="M18 6 6 18M6 6l12 12" /></svg>
                    </button>
                </div>
                <nav className="flex-1 space-y-3 overflow-y-auto pb-3">
                    {groups.map((group) => (
                        <div key={group.label} className="sidebar-group">
                            <p className="sidebar-section-label">{group.label}</p>
                            {group.items.map((section) => {
                                const active = isActive(section);
                                return (
                                    <Link key={section.href + section.label} href={section.href} className={`side-link ${active && ! inAdmin ? 'side-link-active' : ''} ${active && inAdmin ? 'side-link-admin-active' : ''} ${section.locked ? 'opacity-80' : ''}`} aria-current={active ? 'page' : undefined}>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="size-3.5 shrink-0"><path d={section.icon} /></svg>
                                        <span className="min-w-0 flex-1 truncate">{section.label}</span>
                                        {section.locked && (
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-3 shrink-0 opacity-70"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                                        )}
                                    </Link>
                                );
                            })}
                        </div>
                    ))}
                </nav>
                <div className="mt-auto space-y-1 border-t border-white/[0.07] px-2 pt-2">
                    {inAdmin ? (
                        <Link href={route('dashboard')} className="side-mini-link">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-3.5 shrink-0"><path d="m15 18-6-6 6-6" /></svg>
                            Back to console
                        </Link>
                    ) : (
                        <>
                            <a href={chrome.home_href || route('home')} target="_blank" rel="noopener noreferrer" className="side-mini-link">{chrome.view_website}</a>
                            <Link href={route('docs')} className={`side-mini-link ${path.startsWith('/docs') ? 'bg-white/[0.08] text-white' : ''}`}>Documentation</Link>
                            {publicSiteEnabled && <Link href={route('contact')} className="side-mini-link">Contact</Link>}
                            <Link href={chrome.billing_href || '/billing'} className={`side-mini-link ${path.startsWith('/billing') ? 'bg-white/[0.08] text-white' : ''}`}>{chrome.billing}</Link>
                        </>
                    )}
                </div>
            </aside>
            <div className="app-content">
                {impersonation.active && impersonation.target && (
                    <div className="border-b border-amber-600/30 bg-amber-500">
                        <div className="mx-auto flex w-full max-w-[1280px] flex-wrap items-center justify-between gap-3 px-4 py-2.5 lg:px-7">
                            <div className="min-w-0 text-sm text-slate-950">
                                <p className="font-semibold">{impersonation.banner || `You are impersonating ${impersonation.target.name}`}</p>
                                <p className="truncate text-xs text-slate-900/80">{impersonation.target.email}{impersonation.support_mode === 'read_only' ? ` · Support mode: ${impersonation.support_mode_label || 'read only'}` : ''}</p>
                            </div>
                            <form onSubmit={(e) => { e.preventDefault(); router.post(route('impersonation.exit')); }}>
                                <button type="submit" className="button-secondary !min-h-8 !px-3 !py-1.5 !text-xs">{impersonation.exit_label || 'Exit impersonation'}</button>
                            </form>
                        </div>
                    </div>
                )}
                <header className="app-topbar">
                    <div className="flex min-w-0 items-center gap-3">
                        <button type="button" onClick={() => setNav(true)} className="icon-button lg:hidden" aria-label="Open navigation">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-5"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
                        </button>
                        <nav className="hidden min-w-0 items-center sm:flex" aria-label="Breadcrumb">
                            <Link href={route('dashboard')} className="breadcrumb hover:text-slate-700 dark:hover:text-zinc-200">Home</Link>
                            <span className="breadcrumb-sep mx-2 text-xs">/</span>
                            <span className="breadcrumb breadcrumb-current truncate">{displayCrumb}</span>
                        </nav>
                    </div>
                    <div className="flex items-center gap-1.5 sm:gap-2">
                        <div ref={searchWrapRef} className="relative hidden md:block">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-slate-400 dark:text-zinc-500"><circle cx="11" cy="11" r="7" /><path d="m20 20-3-3" /></svg>
                            <input ref={searchRef} type="search" value={q} onChange={(e) => setQ(e.target.value)} className="search-input w-52 lg:w-64" placeholder="Search sites, servers…" onKeyDown={(e) => { if (e.key === 'Enter' && results[0]) window.location.href = results[0].href; }} />
                            <kbd className="search-kbd">{isMac ? '⌘K' : 'Ctrl K'}</kbd>
                            <HeaderPopover open={results.length > 0} anchor={searchWrapRef} onClose={() => setQ('')} matchWidth>
                                {results.map((item) => <Link key={item.href} href={item.href} className="menu-item" onClick={() => setQ('')}>{item.label}</Link>)}
                            </HeaderPopover>
                        </div>
                        <div className="relative">
                            <button ref={alertsBtnRef} type="button" onClick={() => { setAlertsOpen(! alertsOpen); setMenu(false); }} className="icon-button relative" aria-label="Notifications" aria-expanded={alertsOpen}>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-4.5"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0" /></svg>
                                {alerts.length > 0 && <span className="absolute right-1.5 top-1.5 size-1.5 rounded-full bg-rose-500 ring-2 ring-[#f6f5f2] dark:ring-zinc-950" />}
                            </button>
                            <HeaderPopover open={alertsOpen} anchor={alertsBtnRef} onClose={() => setAlertsOpen(false)} widthClass="!w-80">
                                <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-white/5">
                                    <p className="text-card font-semibold heading">Notifications</p>
                                    {alerts.some((alert) => alert.id) && (
                                        <button type="button" className="text-xs font-medium text-sky-700 dark:text-sky-300" onClick={markAllRead}>Mark all read</button>
                                    )}
                                </div>
                                {alerts.length === 0 ? (
                                    <p className="px-4 py-6 text-sm muted">Nothing needs attention.</p>
                                ) : alerts.map((alert) => (
                                    <Link
                                        key={(alert.id || '') + alert.href + alert.title}
                                        href={alert.href || notificationsHref}
                                        className="menu-item block"
                                        onClick={() => { markNotificationRead(alert.id); setAlertsOpen(false); }}
                                    >
                                        <span className="block text-sm font-medium heading">{alert.title}</span>
                                        <span className="block text-xs muted">{alert.description}</span>
                                    </Link>
                                ))}
                                <Link href={notificationsHref} className="menu-item block border-t border-slate-100 font-medium text-sky-700 dark:border-white/5 dark:text-sky-300" onClick={() => setAlertsOpen(false)}>
                                    View all notifications
                                </Link>
                            </HeaderPopover>
                        </div>
                        <button type="button" onClick={toggleDark} className="icon-button" aria-label="Toggle theme">
                            {dark ? (
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-4"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
                            ) : (
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="size-4"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5Z" /></svg>
                            )}
                        </button>
                        <div className="relative">
                            <button ref={menuBtnRef} type="button" onClick={() => { setMenu(! menu); setAlertsOpen(false); }} className={`flex items-center gap-2 rounded-[10px] py-1 pl-1 pr-1.5 transition-colors duration-150 hover:bg-slate-200/60 dark:hover:bg-white/10 ${inAccountArea ? 'bg-slate-200/50 dark:bg-white/10' : ''}`} aria-label="Account menu" aria-expanded={menu}>
                                <span className={`grid size-7 shrink-0 place-items-center rounded-full text-[10px] font-semibold uppercase ${inAdmin ? 'bg-amber-200 text-amber-950' : 'bg-sky-100 text-sky-800 dark:bg-sky-400/20 dark:text-sky-100'}`}>{user.name.slice(0, 2).toUpperCase()}</span>
                            </button>
                            <HeaderPopover open={menu} anchor={menuBtnRef} onClose={() => setMenu(false)}>
                                <div className="border-b border-slate-100 px-3.5 py-3 dark:border-white/5">
                                    <p className="truncate text-sm font-medium heading">{user.name}</p>
                                    <p className="truncate text-xs muted">{user.email}</p>
                                </div>
                                <Link href={chrome.account_href || '/account'} className="menu-item" onClick={() => setMenu(false)}>{chrome.account}</Link>
                                <Link href={chrome.teams_href || '/teams'} className="menu-item" onClick={() => setMenu(false)}>{chrome.teams}</Link>
                                <form onSubmit={logout} className="border-t border-slate-100 dark:border-white/5">
                                    <button className="menu-item !text-rose-600 hover:!bg-rose-50 dark:!text-rose-300 dark:hover:!bg-rose-400/10">{chrome.sign_out}</button>
                                </form>
                            </HeaderPopover>
                        </div>
                    </div>
                </header>
                <main className="relative flex-1">
                    {children}
                </main>
            </div>
            {page.props.aiGuideEnabled && <AiGuide />}
        </div>
    );
}
