import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Calendar, ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';

import { Label } from '@/components/ui/label';
import {
    addMonthsDate,
    buildMonthMatrix,
    compareDay,
    formatDisplayRange,
    formatDisplaySingle,
    isSameDay,
    parseISODate,
    startOfMonth,
    todayDate,
    toISODateLocal,
} from '@/lib/date-kit-core';
import { cn } from '@/lib/utils';

const WEEK_SUN = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const WEEK_MON = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

/**
 * @param {Date} d
 * @param {'single' | 'range'} mode
 * @param {string} singleStr
 * @param {string} fromStr
 * @param {string} toStr
 * @param {Date | null} hoverDate
 */
function getRangeRole(d, mode, singleStr, fromStr, toStr, hoverDate) {
    if (mode === 'single') {
        const p = parseISODate(singleStr);

        if (!p) {
            return 'none';
        }

        return isSameDay(d, p) ? 'single' : 'none';
    }

    if (!fromStr) {
        return 'none';
    }

    const loBound = parseISODate(fromStr);

    if (!loBound) {
        return 'none';
    }

    let hiStr = toStr;

    if (!hiStr && hoverDate) {
        hiStr = toISODateLocal(hoverDate);
    }

    if (!hiStr) {
        return isSameDay(d, loBound) ? 'single' : 'none';
    }

    const hiBound = parseISODate(hiStr);

    if (!hiBound) {
        return isSameDay(d, loBound) ? 'single' : 'none';
    }

    const lo = compareDay(loBound, hiBound) <= 0 ? loBound : hiBound;
    const hi = compareDay(loBound, hiBound) <= 0 ? hiBound : loBound;

    if (compareDay(d, lo) < 0 || compareDay(d, hi) > 0) {
        return 'none';
    }

    if (isSameDay(d, lo) && isSameDay(d, hi)) {
        return 'single';
    }

    if (isSameDay(d, lo)) {
        return 'start';
    }

    if (isSameDay(d, hi)) {
        return 'end';
    }

    return 'middle';
}

/**
 * Professional sheet date picker (popover + calendar). Use `panels={1}` or `panels={2}` for one or two months.
 *
 * @param {object} props
 * @param {'single' | 'range'} [props.mode]
 * @param {1 | 2} [props.panels]
 * @param {0 | 1} [props.weekStartsOn] — default 0 (Sunday) to match common range pickers
 * @param {string} [props.label]
 * @param {string} [props.placeholder]
 * @param {boolean} [props.disabled]
 * @param {string} [props.className]
 * @param {string} [props.value]
 * @param {string} [props.defaultValue]
 * @param {(v: string) => void} [props.onValueChange]
 * @param {string} [props.from]
 * @param {string} [props.to]
 * @param {string} [props.defaultFrom]
 * @param {string} [props.defaultTo]
 * @param {(r: { from: string; to: string }) => void} [props.onRangeChange]
 */
export function DatePicker({
    mode = 'range',
    panels = 2,
    weekStartsOn = 0,
    label,
    placeholder = 'Select date',
    disabled = false,
    className,
    value,
    defaultValue,
    onValueChange,
    from,
    to,
    defaultFrom,
    defaultTo,
    onRangeChange,
}) {
    const panelCount = panels === 1 ? 1 : 2;
    const weekLabels = weekStartsOn === 1 ? WEEK_MON : WEEK_SUN;

    const [viewStart, setViewStart] = React.useState(() => {
        const seedIso = mode === 'single' ? (value ?? defaultValue ?? '') : (from ?? defaultFrom ?? '');
        const seed = parseISODate(seedIso);

        return seed ? startOfMonth(seed) : startOfMonth(todayDate());
    });

    const [innerSingle, setInnerSingle] = React.useState(defaultValue ?? '');
    const single = value !== undefined ? value : innerSingle;

    const [innerFrom, setInnerFrom] = React.useState(defaultFrom ?? '');
    const [innerTo, setInnerTo] = React.useState(defaultTo ?? '');
    const effFrom = from !== undefined ? from : innerFrom;
    const effTo = to !== undefined ? to : innerTo;

    const [hoverDate, setHoverDate] = React.useState(/** @type {Date | null} */ (null));

    const today = todayDate();

    const displayText =
        mode === 'single'
            ? formatDisplaySingle(single)
            : formatDisplayRange(effFrom, effTo);

    function setRange(next) {
        if (from === undefined) {
            setInnerFrom(next.from);
        }

        if (to === undefined) {
            setInnerTo(next.to);
        }

        onRangeChange?.(next);
    }

    function handleDayClick(/** @type {Date} */ day, /** @type {(() => void) | undefined} */ close) {
        const s = toISODateLocal(day);

        if (mode === 'single') {
            if (value === undefined) {
                setInnerSingle(s);
            }

            onValueChange?.(s);
            close?.();

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

        close?.();
    }

    function shiftMonths(delta) {
        const step = panelCount === 2 ? 2 : 1;

        setViewStart((v) => addMonthsDate(v, delta * step));
    }

    function dayCellClass(/** @type {Date} */ date, /** @type {boolean} */ inMonth) {
        const role = getRangeRole(date, mode, single, effFrom, effTo, hoverDate);
        const isToday = isSameDay(date, today);

        return cn(
            'flex h-9 w-full min-w-[2.25rem] items-center justify-center text-xs font-medium transition-colors',
            !inMonth && 'text-muted-foreground/55',
            inMonth && role === 'none' && 'text-foreground hover:bg-muted/80',
            !inMonth && role === 'none' && 'hover:bg-muted/40',
            role === 'middle' && cn('bg-muted/90', inMonth ? 'text-foreground' : 'text-muted-foreground'),
            (role === 'start' || role === 'end' || role === 'single') &&
                'bg-foreground text-background rounded-md shadow-sm dark:bg-foreground dark:text-background',
            isToday && role === 'none' && 'ring-1 ring-border',
        );
    }

    return (
        <Popover className={cn('relative', className)}>
            {label ? (
                <Label className="mb-2 block text-foreground" data-slot="date-picker-label">
                    {label}
                </Label>
            ) : null}

            <PopoverButton
                type="button"
                disabled={disabled}
                data-slot="date-picker-trigger"
                className={cn(
                    'flex h-11 w-full items-center gap-2.5 rounded-lg border border-input bg-background px-3.5 text-left text-sm shadow-sm transition-[box-shadow,background-color,border-color]',
                    'text-foreground hover:bg-muted/40',
                    'focus:outline-none focus:ring-2 focus:ring-ring/40',
                    'disabled:pointer-events-none disabled:opacity-50',
                )}
            >
                <Calendar className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                <span className={cn('min-w-0 flex-1 truncate', !displayText && 'text-muted-foreground')}>
                    {displayText || placeholder}
                </span>
            </PopoverButton>

            <PopoverPanel
                transition
                anchor="bottom start"
                modal={false}
                className={cn(
                    'z-50 rounded-xl border border-border bg-card p-4 shadow-xl outline-none',
                    'data-closed:scale-95 data-closed:opacity-0 data-enter:duration-100 data-enter:ease-out',
                    'data-leave:duration-75 data-leave:ease-in',
                    panelCount === 1 ? 'w-[min(100vw-1.5rem,19rem)]' : 'w-[min(100vw-1.5rem,38.5rem)]',
                )}
                data-slot="date-picker-panel"
            >
                {({ close }) => (
                    <div onMouseLeave={() => setHoverDate(null)}>
                        <div className="flex items-stretch gap-2">
                            <button
                                type="button"
                                className="inline-flex size-9 shrink-0 items-center justify-center self-center rounded-lg border border-transparent text-foreground transition-colors hover:bg-muted"
                                aria-label="Previous months"
                                onClick={() => shiftMonths(-1)}
                            >
                                <ChevronLeft className="size-4" />
                            </button>

                            <div
                                className={cn(
                                    'min-w-0 flex-1',
                                    panelCount === 2 && 'grid grid-cols-1 gap-8 sm:grid-cols-2',
                                )}
                            >
                                {Array.from({ length: panelCount }, (_, i) => {
                                    const monthAnchor = addMonthsDate(viewStart, i);
                                    const y = monthAnchor.getFullYear();
                                    const mo = monthAnchor.getMonth();
                                    const matrix = buildMonthMatrix(y, mo, weekStartsOn);
                                    const title = new Intl.DateTimeFormat(undefined, {
                                        month: 'long',
                                        year: 'numeric',
                                    }).format(monthAnchor);

                                    return (
                                        <div key={`${y}-${mo}`} className="min-w-0 space-y-2">
                                            <h3 className="text-center text-sm font-semibold text-foreground">{title}</h3>
                                            <div className="grid grid-cols-7 gap-px text-[0.65rem] font-medium text-muted-foreground">
                                                {weekLabels.map((w) => (
                                                    <div key={w} className="flex h-7 items-center justify-center">
                                                        {w}
                                                    </div>
                                                ))}
                                            </div>
                                            <div className="grid grid-cols-7 gap-px">
                                                {matrix.map((cell, idx) => (
                                                    <button
                                                        key={`${toISODateLocal(cell.date)}-${idx}`}
                                                        type="button"
                                                        className={dayCellClass(cell.date, cell.inMonth)}
                                                        onClick={() => handleDayClick(cell.date, close)}
                                                        onMouseEnter={() =>
                                                            mode === 'range' &&
                                                            effFrom &&
                                                            !effTo &&
                                                            setHoverDate(cell.date)
                                                        }
                                                    >
                                                        {cell.date.getDate()}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <button
                                type="button"
                                className="inline-flex size-9 shrink-0 items-center justify-center self-center rounded-lg border border-transparent text-foreground transition-colors hover:bg-muted"
                                aria-label="Next months"
                                onClick={() => shiftMonths(1)}
                            >
                                <ChevronRight className="size-4" />
                            </button>
                        </div>
                    </div>
                )}
            </PopoverPanel>
        </Popover>
    );
}
