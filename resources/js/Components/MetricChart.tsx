type Sample = {
    cpu_percent?: number | null;
    memory_percent?: number | null;
    disk_percent?: number | null;
};

const SERIES = [
    { key: 'cpu_percent' as const, color: '#0891b2', label: 'CPU' },
    { key: 'memory_percent' as const, color: '#7c3aed', label: 'Memory' },
    { key: 'disk_percent' as const, color: '#d97706', label: 'Disk' },
];

export function MetricChart({ metrics }: { metrics: Sample[] }) {
    const points = [...(metrics || [])].reverse();
    const width = 720;
    const height = 160;
    const pad = 10;

    if (points.length === 0) {
        return <p className="mt-5 text-sm muted">Waiting for the first signed sample.</p>;
    }

    const plot = (key: keyof Sample) => {
        if (points.length === 1) {
            const value = Math.min(100, Math.max(0, Number(points[0][key]) || 0));
            const x = width / 2;
            const y = height - pad - (value / 100) * (height - pad * 2);
            return `${x},${y}`;
        }

        return points.map((sample, index) => {
            const x = pad + (index / (points.length - 1)) * (width - pad * 2);
            const y = height - pad - (Math.min(100, Math.max(0, Number(sample[key]) || 0)) / 100) * (height - pad * 2);
            return `${x},${y}`;
        }).join(' ');
    };

    const latest = points[points.length - 1];

    return (
        <div>
            <svg viewBox={`0 0 ${width} ${height}`} className="mt-4 h-40 w-full overflow-visible" role="img" aria-label="CPU, memory, and disk over time">
                {[0, 25, 50, 75, 100].map((tick) => {
                    const y = height - pad - (tick / 100) * (height - pad * 2);
                    return <line key={tick} x1={pad} x2={width - pad} y1={y} y2={y} stroke="currentColor" className="text-slate-100 dark:text-white/10" />;
                })}
                {SERIES.map((series) => (
                    <g key={series.key}>
                        <polyline fill="none" stroke={series.color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" points={plot(series.key)} />
                        {points.length === 1 && (
                            <circle cx={width / 2} cy={height - pad - (Math.min(100, Math.max(0, Number(latest[series.key]) || 0)) / 100) * (height - pad * 2)} r="5" fill={series.color} />
                        )}
                    </g>
                ))}
            </svg>
            {points.length === 1
                ? <p className="mt-3 text-sm muted">One sample so far. The agent reports every minute, so the trend fills in shortly.</p>
                : <p className="mt-3 text-sm muted">The latest {points.length} one-minute samples</p>}
        </div>
    );
}
