import { ArrowDownAZ, ArrowDownWideNarrow, ArrowUpWideNarrow, ChevronDown, Sparkles } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

const sortOptions = [
    { value: 'newest', label: 'Newest', shortLabel: 'Newest', icon: Sparkles },
    { value: 'price_low', label: 'Price: Low to High', shortLabel: 'Low ↑', icon: ArrowUpWideNarrow },
    { value: 'price_high', label: 'Price: High to Low', shortLabel: 'High ↓', icon: ArrowDownWideNarrow },
    { value: 'name', label: 'Name A–Z', shortLabel: 'A–Z', icon: ArrowDownAZ },
];

export function ListingSortControl({ value, onChange, variant = 'pills' }) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef(null);
    const listId = useId();
    const active = sortOptions.find((option) => option.value === value) ?? sortOptions[0];
    const ActiveIcon = active.icon;

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const handlePointerDown = (event) => {
            if (!containerRef.current?.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handlePointerDown);
        return () => document.removeEventListener('mousedown', handlePointerDown);
    }, [open]);

    if (variant === 'dropdown') {
        return (
            <div ref={containerRef} className="relative shrink-0">
                <button
                    type="button"
                    onClick={() => setOpen((previous) => !previous)}
                    className="inline-flex items-center gap-1.5 rounded-full bg-white py-1.5 pl-2.5 pr-2 text-xs font-semibold text-store-primary ring-1 ring-gray-200 transition-all hover:ring-store-accent/40"
                    aria-expanded={open}
                    aria-haspopup="listbox"
                    aria-controls={listId}
                >
                    <span className="flex size-5 items-center justify-center rounded-full bg-store-primary/5">
                        <ActiveIcon className="size-3 text-store-accent" aria-hidden />
                    </span>
                    {active.shortLabel}
                    <ChevronDown
                        className={cn('size-3 text-store-muted transition-transform', open && 'rotate-180')}
                        aria-hidden
                    />
                </button>

                {open && (
                    <ul
                        id={listId}
                        role="listbox"
                        className="absolute right-0 top-[calc(100%+0.375rem)] z-30 min-w-38 overflow-hidden rounded-2xl bg-white p-1 shadow-xl ring-1 ring-gray-200"
                    >
                        {sortOptions.map((option) => {
                            const Icon = option.icon;
                            const selected = option.value === value;

                            return (
                                <li key={option.value} role="option" aria-selected={selected}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onChange(option.value);
                                            setOpen(false);
                                        }}
                                        className={cn(
                                            'flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-left text-xs font-medium transition-colors',
                                            selected
                                                ? 'bg-store-primary text-white'
                                                : 'text-store-primary hover:bg-store-surface',
                                        )}
                                    >
                                        <Icon className="size-3.5 shrink-0" aria-hidden />
                                        {option.label}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        );
    }

    return (
        <div
            role="group"
            aria-label="Sort products"
            className="inline-flex items-center gap-0.5 rounded-full bg-white p-0.5 ring-1 ring-gray-200"
        >
            {sortOptions.map((option) => {
                const Icon = option.icon;
                const selected = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange(option.value)}
                        aria-pressed={selected}
                        title={option.label}
                        className={cn(
                            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-all',
                            selected
                                ? 'bg-store-primary text-white shadow-sm'
                                : 'text-store-muted hover:bg-store-surface hover:text-store-primary',
                        )}
                    >
                        <Icon className="size-3 shrink-0" aria-hidden />
                        <span className="hidden sm:inline">{option.shortLabel}</span>
                    </button>
                );
            })}
        </div>
    );
}

export { sortOptions };
