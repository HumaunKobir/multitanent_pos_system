import { cn } from '@/lib/utils';

export function ModuleWidget({ title, icon: Icon, accentClass = 'border-l-emerald-600', children, className }) {
    return (
        <div className={cn('border border-border bg-card shadow-none', className)}>
            <div className={cn('flex items-center gap-2 border-b border-border border-l-4 px-4 py-2.5', accentClass)}>
                {Icon ? <Icon className="size-4 text-muted-foreground" /> : null}
                <h2 className="text-xs font-semibold uppercase tracking-widest">{title}</h2>
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}
