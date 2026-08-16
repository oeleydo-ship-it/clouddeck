export function route(name: string, params?: unknown, absolute = false): string {
    if (typeof window !== 'undefined' && typeof window.route === 'function') {
        try {
            return window.route(name, params, absolute);
        } catch {
            // Fall through to known paths when Ziggy does not know the name.
        }
    }

    const id = paramId(params);
    if (name === 'notification-channels.destroy' && id) {
        return `/notification-channels/${id}`;
    }
    if (name === 'notifications.read' && id) {
        return `/notifications/${id}/read`;
    }
    if (name === 'security.incidents.status' && id) {
        return `/security/incidents/${id}/status`;
    }
    if (name === 'security.incidents.block' && id) {
        return `/security/incidents/${id}/block`;
    }
    if (name === 'security.incidents.unblock' && id) {
        return `/security/incidents/${id}/block`;
    }

    const known: Record<string, string> = {
        'notifications.index': '/notifications',
        'notification-channels.store': '/notification-channels',
        'notifications.read-all': '/notifications/read-all',
        'billing.index': '/billing',
        dashboard: '/dashboard',
        'site-logs.store': id ? `/sites/${id}/logs` : '/',
    };

    return known[name] || '/';
}

function paramId(params: unknown): string | null {
    if (params == null) {
        return null;
    }
    if (typeof params === 'string' || typeof params === 'number') {
        return String(params);
    }
    if (Array.isArray(params)) {
        return params[0] != null ? String(params[0]) : null;
    }
    if (typeof params === 'object') {
        const record = params as Record<string, unknown>;
        const value = record.id ?? record.notificationChannel ?? record.notification ?? record.securityIncident ?? Object.values(record)[0];

        return value != null ? String(value) : null;
    }

    return null;
}
