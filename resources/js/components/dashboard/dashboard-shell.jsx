import { formatBdDate } from '@/lib/format-bd-date';
import { cn } from '@/lib/utils';
import { LayoutDashboard } from 'lucide-react';

export function DashboardShell({ title, subtitle, today, children }) {
    return (
        <div
            className="min-h-full bg-background px-2 py-1"
            style={{
                backgroundImage:
                    'linear-gradient(to right, hsl(var(--border) / 0.35) 1px, transparent 1px), linear-gradient(to bottom, hsl(var(--border) / 0.35) 1px, transparent 1px)',
                backgroundSize: '32px 32px',
            }}
        >
            <div className="mb-4 flex items-center justify-between border border-blue-950 bg-blue-950 px-5 py-3 shadow-none">
                <div className="flex items-center gap-3">
                    <div className="flex size-8 items-center justify-center border border-white/20 bg-white/10">
                        <LayoutDashboard className="size-4 text-white" />
                    </div>
                    <div>
                        <h1 className="text-base font-semibold tracking-tight text-white">{title}</h1>
                        {subtitle ? <p className="text-xs text-white/60">{subtitle}</p> : null}
                    </div>
                </div>
                {today ? (
                    <div className="border border-white/15 bg-white/5 px-3 py-1.5 text-right">
                        <p className="text-[10px] font-semibold uppercase tracking-widest text-white/50">Today</p>
                        <p className="font-mono text-sm tabular-nums text-white">{formatBdDate(today)}</p>
                    </div>
                ) : null}
            </div>

            <div className={cn('space-y-4')}>{children}</div>
        </div>
    );
}
