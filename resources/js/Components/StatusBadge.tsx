import { badge, enumValue } from '../lib/ui';

export function StatusBadge({ status }: { status?: string | null }) {
    const label = enumValue(status).replace(/_/g, ' ');
    return <span className={`badge ${badge(label)} capitalize`}><span className="badge-dot bg-current opacity-70" />{label || 'unknown'}</span>;
}
