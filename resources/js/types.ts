export type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_super_admin: boolean;
    email_verified_at: string | null;
};

export type Branding = {
    name: string;
    logo_url: string | null;
    favicon_url: string | null;
    logo_image_only: boolean;
};

export type NavItem = {
    href: string;
    label: string;
    icon: string;
    match?: string;
    route?: string;
    locked?: boolean;
    admin?: boolean;
};

export type Flash = {
    status?: string | null;
    download_key?: string | null;
    database_password?: string | null;
    monitoring_secret?: string | null;
    recovery_codes?: string[] | null;
};

export type PageProps = {
    auth: { user: AuthUser | null };
    branding: Branding;
    features: Record<string, boolean>;
    flash: Flash;
    errors: Record<string, string>;
    csrf_token: string;
    dnsEnabled: boolean;
    publicSiteEnabled: boolean;
    managedServersReady: boolean;
    supportEmail?: string | null;
    seo: Record<string, string | null>;
    analytics: { ga_measurement_id?: string | null; gsc_verification?: string | null };
    aiGuideEnabled: boolean;
    insertCode: Record<string, unknown>;
    onMarketing: boolean;
    shellAlerts: Array<{ id?: string; title: string; description: string; href: string; tone: string; unread?: boolean }>;
    impersonation: {
        active: boolean;
        support_mode: string | null;
        support_mode_label?: string | null;
        banner?: string | null;
        exit_label?: string | null;
        target: { name: string; email: string } | null;
    };
    chrome: {
        billing: string;
        sign_out: string;
        teams: string;
        account: string;
        documentation: string;
        contact: string;
        open_console: string;
        view_website: string;
        primary_nav: string;
        billing_href?: string;
        teams_href?: string;
        account_href?: string;
        home_href?: string;
    };
    consoleNav: NavItem[];
    adminNav: NavItem[];
    title?: string;
    metaDescription?: string;
    ziggy?: unknown;
};

declare global {
    function route(name: string, params?: unknown, absolute?: boolean): string;
    interface Window {
        Echo?: {
            private: (channel: string) => {
                listen: (event: string, cb: (payload: unknown) => void) => unknown;
                stopListening: (event: string) => unknown;
            };
        };
        route: typeof route;
    }
}

export {};
