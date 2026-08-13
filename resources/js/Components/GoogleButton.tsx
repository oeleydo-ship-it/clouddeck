import { route } from '../lib/route';

export function GoogleButton({ enabled, label, href }: { enabled: boolean; label?: string | null; href?: string | null }) {
    if (! enabled) return null;

    return (
        <div className="mt-6">
            <div className="relative flex items-center gap-3">
                <div className="h-px flex-1 bg-slate-200 dark:bg-white/10" />
                <span className="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">or</span>
                <div className="h-px flex-1 bg-slate-200 dark:bg-white/10" />
            </div>
            <a href={href || route('auth.google.redirect')} className="button-secondary mt-5 flex w-full items-center justify-center gap-2.5 !py-2.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" className="size-4" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1Z" />
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z" />
                    <path fill="#FBBC05" d="M5.84 14.09A6.6 6.6 0 0 1 5.5 12c0-.72.13-1.43.34-2.09V7.07H2.18A11 11 0 0 0 1 12c0 1.78.43 3.45 1.18 4.93l3.66-2.84Z" />
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53Z" />
                </svg>
                {label || 'Continue with Google'}
            </a>
        </div>
    );
}
