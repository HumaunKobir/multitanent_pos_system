import { Calendar, ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';

import { Label } from '@/components/ui/label';
import {
    addMonthsDate,
    buildMonthCells,
    compareDay,
    isDayInClosedRange,
    isSameDay,
    parseISODate,
    startOfMonth,
    todayDate,
    toISODateLocal,
} from '@/lib/date-kit-core';
import { cn } from '@/lib/utils';

const dateInputClassName = cn(
    'flex h-11 w-full min-w-0 rounded-none border-2 border-input bg-background py-2 pr-3 pl-10 text-sm shadow-xs transition-[color,box-shadow,border-color]',
    'text-foreground placeholder:text-muted-foreground',
    'focus-visible:border-blue-800 focus-visible:ring-2 focus-visible:ring-blue-950/15 focus-visible:outline-none',
    'dark:focus-visible:border-blue-500 dark:focus-visible:ring-blue-400/20',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
    '[color-scheme:light] dark:[color-scheme:dark]',
    '[&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:inset-0 [&::-webkit-calendar-picker-indicator]:h-full [&::-webkit-calendar-picker-indicator]:w-full [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-0',
);

const WEEK_MON = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const WEEK_SUN = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

/**
 * @param {object} props
 * @param {string} [props.label]
 * @param {string} [props.description]
 * @param {(value: string) => void} [props.onValueChange]
 */
export const DateInput = React.forwardRef(function DateInput(
    { className, label, description, id, onValueChange, onChange, value, defaultValue, ...props },
    ref,
) {
    const autoId = React.useId();
    const inputId = id ?? autoId;

    function handleChange(/** @type {React.ChangeEvent<HTMLInputElement>} */ e) {
        onChange?.(e);
        onValueChange?.(e.target.value);
    }

    return (
        <div className="space-y-2" data-slot="date-input">
            {label ? (
                <Label htmlFor={inputId} className="text-foreground">
                    {label}
                </Label>
            ) : null}
            <div className="relative">
                <Calendar
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-blue-900/70 dark:text-blue-300/80"
                    aria-hidden
                />
                <input
                    ref={ref}
                    id={inputId}
                    type="date"
                    data-slot="date-input-field"
                    value={value}
                    defaultValue={defaultValue}
                    onChange={handleChange}
                    className={cn(dateInputClassName, className)}
                    {...props}
                />
            </div>
            {description ? <p className="text-[0.7rem] text-muted-foreground">{description}</p> : null}
        </div>
    );
});

DateInput.displayName = 'DateInput';

/**
 * @param {object} props
 * @param {string} [props.fromLabel]
 * @param {string} [props.toLabel]
 * @param {string} [props.fromId]
 * @param {string} [props.toId]
 * @param {string} [props.fromValue]
 * @param {string} [props.toValue]
 * @param {(v: string) => void} [props.onFromChange]
 * @param {(v: string) => void} [props.onToChange]
 */
export function DateRangeFields({
    fromLabel = 'From',
    toLabel = 'To',
    fromId,
    toId,
    fromValue,
    toValue,
    onFromChange,
    onToChange,
    className,
}) {
    return (
        <div className={cn('grid gap-4 sm:grid-cols-2', className)} data-slot="date-range-fields">
            <DateInput id={fromId} label={fromLabel} value={fromValue} onValueChange={onFromChange} />
            <DateInput id={toId} label={toLabel} value={toValue} onValueChange={onToChange} />
        </div>
    );
}

/**
 * @param {object} props
 * @param {'single' | 'range'} [props.mode]
 * @param {number} [props.numberOfMonths] 1–4
 * @param {0 | 1} [props.weekStartsOn]
 * @param {string} [props.value] yyyy-mm-dd (single, controlled)
 * @param {string} [props.defaultValue]
 * @param {(v: string) => void} [props.onValueChange]
 * @param {string} [props.from]
 * @param {string} [props.to]
 * @param {string} [props.defaultFrom]
 * @param {string} [props.defaultTo]
 * @param {(r: { from: string; to: string }) => void} [props.onRangeChange]
 * @param {string} [props.className]
 */
export function CalendarMonths({
    mode = 'single',
    numberOfMonths = 2,
    weekStartsOn = 1,
    value,
    defaultValue,
    onValueChange,
    from,
    to,
    defaultFrom,
    defaultTo,
    onRangeChange,
    className,
}) {
    const count = Math.min(4, Math.max(1, numberOfMonths));
    const weekLabels = weekStartsOn === 1 ? WEEK_MON : WEEK_SUN;

    const [viewStart, setViewStart] = React.useState(() => startOfMonth(todayDate()));

    const [innerSingle, setInnerSingle] = React.useState(defaultValue ?? '');
    const single = value !== undefined ? value : innerSingle;

    const [innerFrom, setInnerFrom] = React.useState(defaultFrom ?? '');
    const [innerTo, setInnerTo] = React.useState(defaultTo ?? '');
    const effFrom = from !== undefined ? from : innerFrom;
    const effTo = to !== undefined ? to : innerTo;

    const [hoverDate, setHoverDate] = React.useState(/** @type {Date | null} */ (null));

    const today = todayDate();

    function setRange(next) {
        if (from === undefined) {
            setInnerFrom(next.from);
        }

        if (to === undefined) {
            setInnerTo(next.to);
        }

        onRangeChange?.(next);
    }

    function handleDayClick(/** @type {Date} */ day) {
        const s = toISODateLocal(day);

        if (mode === 'single') {
            if (value === undefined) {
                setInnerSingle(s);
            }

            onValueChange?.(s);

            return;
        }

        if (!effFrom || (effFrom && effTo)) {
            setRange({ from: s, to: '' });

            return;
        }

        const fd = parseISODate(effFrom);

        if (!fd) {
            setRange({ from: s, to: '' });

            return;
        }

        if (compareDay(day, fd) < 0) {
            setRange({ from: s, to: effFrom });
        } else {
            setRange({ from: effFrom, to: s });
        }
    }

    function isRangePreview(/** @type {Date} */ d) {
        if (mode !== 'range' || !effFrom) {
            return false;
        }

        if (effTo) {
            return isDayInClosedRange(d, effFrom, effTo);
        }

        if (hoverDate) {
            return isDayInClosedRange(d, effFrom, toISODateLocal(hoverDate));
        }

        return isSameDay(d, parseISODate(effFrom));
    }

    function isEndpoint(/** @type {Date} */ d) {
        if (mode === 'single') {
            const pd = parseISODate(single);

            return Boolean(pd && isSameDay(d, pd));
        }

        const pf = parseISODate(effFrom);

        if (pf && isSameDay(d, pf)) {
            return true;
        }

        const pt = parseISODate(effTo);

        return Boolean(pt && isSameDay(d, pt));
    }

    function dayButtonClass(/** @type {Date} */ d) {
        const inRange = mode === 'range' && isRangePreview(d);
        const endpoint = isEndpoint(d);
        const isToday = isSameDay(d, today);

        return cn(
            'relative flex h-9 w-full items-center justify-center rounded-none text-xs font-medium transition-colors',
            'focus-visible:ring-2 focus-visible:ring-blue-950/25 focus-visible:outline-none',
            'dark:focus-visible:ring-blue-400/30',
            endpoint && 'bg-blue-950 text-white shadow-sm dark:bg-blue-800',
            !endpoint && inRange && 'bg-blue-950/12 text-foreground dark:bg-blue-950/35',
            !endpoint && !inRange && 'text-foreground hover:bg-blue-950/8 dark:hover:bg-blue-950/25',
            isToday && !endpoint && 'ring-1 ring-blue-800/40 ring-inset dark:ring-blue-500/50',
        );
    }

    return (
        <div
            data-slot="calendar-months"
            className={cn(
                'rounded-none border-2 border-blue-950/15 bg-card p-4 shadow-sm ring-1 ring-blue-950/8 dark:border-blue-500/20 dark:ring-blue-400/10',
                className,
            )}
            onMouseLeave={() => setHoverDate(null)}
        >
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-blue-950/10 pb-3 dark:border-blue-500/15">
                <p className="text-xs font-semibold tracking-wide text-blue-950 uppercase dark:text-blue-200">
                    {mode === 'range' ? 'Range calendar' : 'Single date'}
                </p>
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        className="inline-flex size-9 items-center justify-center rounded-none border border-input bg-background text-foreground transition-colors hover:bg-accent"
                        aria-label="Previous month"
                        onClick={() => setViewStart((v) => addMonthsDate(v, -1))}
                    >
                        <ChevronLeft className="size-4" />
                    </button>
                    <button
                        type="button"
                        className="inline-flex size-9 items-center justify-center rounded-none border border-input bg-background text-foreground transition-colors hover:bg-accent"
                        aria-label="Next month"
                        onClick={() => setViewStart((v) => addMonthsDate(v, 1))}
                    >
                        <ChevronRight className="size-4" />
                    </button>
                    <button
                        type="button"
                        className="ml-1 border border-blue-900/25 bg-blue-950/5 px-3 py-1.5 text-xs font-medium text-blue-950 transition-colors hover:bg-blue-950/10 dark:border-blue-500/30 dark:bg-blue-950/30 dark:text-blue-100 dark:hover:bg-blue-950/45"
                        onClick={() => setViewStart(startOfMonth(todayDate()))}
                    >
                        Today
                    </button>
                </div>
            </div>

            <div
                className={cn(
                    'grid gap-8',
                    count === 1 && 'grid-cols-1',
                    count === 2 && 'grid-cols-1 sm:grid-cols-2',
                    count === 3 && 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3',
                    count >= 4 && 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
                )}
            >
                {Array.from({ length: count }, (_, i) => {
                    const monthAnchor = addMonthsDate(viewStart, i);
                    const y = monthAnchor.getFullYear();
                    const m = monthAnchor.getMonth();
                    const cells = buildMonthCells(y, m, weekStartsOn);
                    const title = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(monthAnchor);

                    return (
                        <div key={`${y}-${m}`} className="min-w-0 space-y-2">
                            <h3 className="border-b border-border pb-2 text-center text-sm font-semibold text-foreground">
                                {title}
                            </h3>
                            <div className="grid grid-cols-7 gap-px text-[0.65rem] font-medium text-muted-foreground">
                                {weekLabels.map((w) => (
                                    <div key={w} className="flex h-7 items-center justify-center">
                                        {w}
                                    </div>
                                ))}
                            </div>
                            <div className="grid grid-cols-7 gap-px">
                                {cells.map((cell, idx) => {
                                    if (!cell) {
                                        return <div key={`e-${idx}`} className="h-9" />;
                                    }

                                    return (
                                        <button
                                            key={toISODateLocal(cell)}
                                            type="button"
                                            className={dayButtonClass(cell)}
                                            onClick={() => handleDayClick(cell)}
                                            onMouseEnter={() => mode === 'range' && effFrom && !effTo && setHoverDate(cell)}
                                        >
                                            {cell.getDate()}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>

            {mode === 'range' ? (
                <p className="mt-4 border-t border-border pt-3 text-[0.7rem] text-muted-foreground">
                    First click sets start; second sets end. Click again to reset. Hover previews the span when only
                    start is set.
                </p>
            ) : (
                <p className="mt-4 border-t border-border pt-3 text-[0.7rem] text-muted-foreground">
                    Choose a day — use with <span className="font-mono text-foreground">onValueChange</span> to sync a
                    native <span className="font-mono text-foreground">DateInput</span>.
                </p>
            )}
        </div>
    );
}

export { DatePicker } from './date-picker';
