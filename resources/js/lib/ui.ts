export function badge(status?: string | null): string {
    const value = (status || '').toLowerCase();
    if (['ready', 'active', 'successful', 'secure', 'running'].includes(value)) return 'badge-success';
    if (['failed', 'danger', 'critical', 'deleting'].includes(value)) return 'badge-danger';
    if (['pending', 'provisioning', 'creating', 'deploying', 'configuring', 'issuing', 'awaiting_payment', 'warning'].includes(value)) return 'badge-warning';
    return 'badge-neutral';
}

export function money(cents: number, currency = 'USD'): string {
    const fraction = cents % 100 === 0 ? 0 : 2;
    return new Intl.NumberFormat('en-US', { style: 'currency', currency, minimumFractionDigits: fraction, maximumFractionDigits: fraction }).format(cents / 100);
}

export function setting(settings: Record<string, { value?: string | null } | string | undefined> | undefined, key: string, fallback = ''): string {
    const row = settings?.[key];
    if (typeof row === 'string') return row || fallback;
    return row?.value || fallback;
}

export function checked(settings: Record<string, { value?: string | null } | undefined> | undefined, key: string, fallback = true): boolean {
    const value = settings?.[key]?.value;
    if (value === undefined || value === null || value === '') return fallback;
    return value === '1' || value === 'true';
}

export function items<T = any>(value: any): T[] {
    if (Array.isArray(value)) return value;
    return value?.data ?? [];
}

export function enumValue(value: any): string {
    if (value && typeof value === 'object' && 'value' in value) return String(value.value);
    return String(value ?? '');
}

export function when(value?: string | null): string {
    if (! value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString();
}
