import { route } from '@/lib/route';
import { router } from '@inertiajs/react';
import { BarChart2, Calendar, SlidersHorizontal } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dateInputRightIconClassName } from '@/components/ui/date-kit';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { cn } from '@/lib/utils';

/**
 * Debounced Inertia visit when filter state changes (no Apply button).
 */
export function useLiveReportFilters(routeName, query, deps) {
    useDebouncedEffect(
        () => {
            const params = Object.fromEntries(
                Object.entries(query).filter(
                    ([, value]) => value !== '' && value != null && value !== 'all' && value !== '__all',
                ),
            );
            router.get(route(routeName), params, { preserveState: true, replace: true });
        },
        deps,
        350,
        { skipFirstRun: true },
    );
}

export function ReportPage({ title, description, filterBar, children }) {
    return (
        <div className="px-2 py-1">
            <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3 shadow-sm">
                <div className="flex items-center gap-3">
                    <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                        <BarChart2 className="size-4 text-white" />
                    </div>
                    <div>
                        <h1 className="text-base font-semibold text-white">{title}</h1>
                        <p className="text-xs text-white/60">{description}</p>
                    </div>
                </div>
            </div>

            {filterBar ? <ReportFilterPanel>{filterBar}</ReportFilterPanel> : null}

            {children}
        </div>
    );
}

export function ReportFilterPanel({ children }) {
    return (
        <div className="mb-4 overflow-hidden rounded-lg border border-blue-950/10 bg-card shadow-sm">
            <div className="flex items-center gap-2.5 bg-blue-950 px-4 py-2.5">
                <div className="flex size-6 items-center justify-center rounded bg-white/15">
                    <SlidersHorizontal className="size-3.5 text-white" />
                </div>
                <h2 className="text-sm font-semibold uppercase tracking-wide text-white">Report Filters</h2>
                <span className="ml-auto text-[10px] font-medium uppercase tracking-wider text-white/50">
                    Live update
                </span>
            </div>
            <div className="grid gap-4 bg-gradient-to-br from-slate-50 via-white to-blue-50/40 p-4 dark:from-slate-900/40 dark:via-card dark:to-blue-950/10 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {children}
            </div>
        </div>
    );
}

export function ReportFilterField({ label, icon: Icon, children, className = '' }) {
    return (
        <div className={['min-w-0', className].filter(Boolean).join(' ')}>
            <Label className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-blue-950 dark:text-blue-100">
                {Icon && (
                    <span className="flex size-5 items-center justify-center rounded bg-blue-950/10 text-blue-950 dark:bg-white/10 dark:text-blue-100">
                        <Icon className="size-3" />
                    </span>
                )}
                {label}
            </Label>
            <div className="rounded-md border border-blue-950/10 bg-white shadow-xs ring-1 ring-blue-950/5 dark:bg-slate-950/50">
                {children}
            </div>
        </div>
    );
}

const selectTriggerClassName =
    'h-9 w-full border-0 bg-transparent shadow-none focus-visible:ring-0 focus-visible:ring-offset-0';

export function ReportSelect({ value, onChange, placeholder, options = [], className = '' }) {
    const hasValue = value !== '' && value != null;

    return (
        <Select value={hasValue ? String(value) : undefined} onValueChange={onChange}>
            <SelectTrigger className={cn(selectTriggerClassName, className)}>
                <SelectValue placeholder={placeholder ?? 'Select…'} />
            </SelectTrigger>
            <SelectContent>
                {options.map((opt) => (
                    <SelectItem key={opt.value} value={String(opt.value)}>
                        {opt.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function ReportDateInput({ value, onChange, className = '' }) {
    return (
        <div className="relative">
            <Input
                type="date"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={cn('h-9 w-full border-0 bg-transparent pl-3 shadow-none focus-visible:ring-0', dateInputRightIconClassName, className)}
            />
            <Calendar
                className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-blue-900/70 dark:text-blue-300/80"
                aria-hidden
            />
        </div>
    );
}

export function ReportInfoBanner({ children }) {
    return (
        <div className="mb-4 rounded-lg border border-blue-950/15 bg-gradient-to-r from-blue-950/5 via-blue-50/50 to-transparent px-4 py-3 text-sm shadow-sm dark:from-blue-950/20 dark:via-blue-950/10">
            {children}
        </div>
    );
}

export function MoneyCell({ value, className = '' }) {
    return <span className={className}>৳{parseFloat(value ?? 0).toFixed(2)}</span>;
}
