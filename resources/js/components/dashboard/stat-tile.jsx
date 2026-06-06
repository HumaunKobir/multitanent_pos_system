import { cn } from '@/lib/utils';

export function StatTile({ label, value, sub, accentClass = 'border-l-emerald-600', className }) {
    return (
        <div
            className={cn(
                'border border-border border-l-4 bg-card px-4 py-3 shadow-none',
                accentClass,
                className,
            )}
        >
            <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">{label}</p>
            <p className="mt-1 font-mono text-2xl font-bold tabular-nums tracking-tight">{value}</p>
            {sub ? <p className="mt-1 text-xs text-muted-foreground">{sub}</p> : null}
        </div>
    );
}
