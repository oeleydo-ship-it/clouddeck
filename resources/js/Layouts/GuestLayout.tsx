import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useEffect } from 'react';
import { PageProps } from '../types';
import { route } from '../lib/route';
import { persistTheme, isDarkTheme } from '../lib/theme';

export default function GuestLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    const { branding, flash, errors } = usePage<PageProps>().props;
    const errorList = Object.values(errors || {});
    useEffect(() => {
        persistTheme(isDarkTheme());
    }, []);

    return (
        <div className="auth-shell">
            <div className="auth-mesh" aria-hidden="true" />
            <header className="relative z-10 px-5 py-5">
                <div className="mx-auto flex max-w-md items-center justify-between">
                    <Link href={route('home')} className="font-display text-[15px] font-semibold tracking-[-0.02em] heading">
                        {branding.logo_url
                            ? <img src={branding.logo_url} alt={branding.name} className="h-7 w-auto" />
                            : branding.name}
                    </Link>
                    <Link href={route('home')} className="text-sm muted transition-colors duration-150 hover:text-slate-900 dark:hover:text-white">Back to site</Link>
                </div>
            </header>
            <main className="relative z-10 mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-5 pb-16 pt-6">
                {title && <h1 className="page-title mb-6">{title}</h1>}
                {title && flash.status && <div className="flash-success mb-4">{flash.status}</div>}
                {title && errorList.length > 0 && (
                    <div className="flash-danger mb-4">
                        <ul className="list-inside list-disc space-y-1">{errorList.map((e) => <li key={e}>{e}</li>)}</ul>
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
