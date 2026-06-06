import { Check } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

function buildGroups(contacts) {
    return [
        {
            label: 'Suppliers',
            items: (contacts.suppliers ?? []).map((supplier) => ({
                value: `supplier:${supplier.id}`,
                label: supplier.name,
            })),
        },
        {
            label: 'Customers',
            items: (contacts.customers ?? []).map((customer) => ({
                value: `customer:${customer.id}`,
                label: customer.name,
            })),
        },
    ].filter((group) => group.items.length > 0);
}

function handleListWheel(event) {
    const element = event.currentTarget;
    const delta = event.deltaY;
    const atTop = element.scrollTop <= 0;
    const atBottom = element.scrollTop + element.clientHeight >= element.scrollHeight - 1;

    if ((delta < 0 && atTop) || (delta > 0 && atBottom)) {
        return;
    }

    event.stopPropagation();
}

export function VoucherContactSelect({ value, onChange, contacts = {}, placeholder = 'Select contact...' }) {
    const containerRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const groups = useMemo(() => buildGroups(contacts), [contacts]);
    const flatOptions = useMemo(() => groups.flatMap((group) => group.items), [groups]);
    const selected = useMemo(() => flatOptions.find((option) => option.value === value) ?? null, [flatOptions, value]);

    const filteredGroups = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();

        if (!normalizedQuery) {
            return groups;
        }

        return groups
            .map((group) => ({
                ...group,
                items: group.items.filter((item) => item.label.toLowerCase().includes(normalizedQuery)),
            }))
            .filter((group) => group.items.length > 0);
    }, [groups, query]);

    const hasResults = filteredGroups.some((group) => group.items.length > 0);

    useEffect(() => {
        function handlePointerDown(event) {
            if (containerRef.current?.contains(event.target)) {
                return;
            }

            setOpen(false);
            setQuery('');
        }

        document.addEventListener('mousedown', handlePointerDown);

        return () => document.removeEventListener('mousedown', handlePointerDown);
    }, []);

    function handleFocus() {
        setOpen(true);
        setQuery('');
    }

    function handleChange(event) {
        setQuery(event.target.value);
        setOpen(true);
    }

    function selectOption(option) {
        onChange(option.value);
        setOpen(false);
        setQuery('');
    }

    function clearSelection() {
        onChange('');
        setQuery('');
        setOpen(false);
    }

    return (
        <div ref={containerRef} className="relative mt-1 w-full">
            <Input
                value={open ? query : (selected?.label ?? '')}
                onChange={handleChange}
                onFocus={handleFocus}
                placeholder={placeholder}
                autoComplete="off"
                className="bg-background"
            />

            {open && (
                <div className="absolute top-[calc(100%+4px)] right-0 left-0 z-100 border border-border bg-popover shadow-md">
                    <ul
                        role="listbox"
                        className="max-h-36 overflow-y-auto overscroll-contain py-1"
                        onWheel={handleListWheel}
                        onTouchMove={(event) => event.stopPropagation()}
                    >
                        {selected && (
                            <li>
                                <button
                                    type="button"
                                    className="flex w-full px-3 py-2 text-left text-sm text-muted-foreground hover:bg-accent/80"
                                    onClick={clearSelection}
                                >
                                    Clear selection
                                </button>
                            </li>
                        )}

                        {!hasResults ? (
                            <li className="px-3 py-2 text-sm text-muted-foreground">No matches.</li>
                        ) : (
                            filteredGroups.map((group) => (
                                <li key={group.label}>
                                    <div className="px-3 py-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        {group.label}
                                    </div>
                                    <ul>
                                        {group.items.map((item) => {
                                            const isSelected = item.value === value;

                                            return (
                                                <li key={item.value}>
                                                    <button
                                                        type="button"
                                                        role="option"
                                                        aria-selected={isSelected}
                                                        className={cn(
                                                            'flex w-full cursor-pointer items-center justify-between gap-2 border-l-2 border-transparent py-1.5 pr-3 pl-2 text-left text-sm hover:bg-accent/80',
                                                            isSelected && 'border-primary bg-accent/50',
                                                        )}
                                                        onClick={() => selectOption(item)}
                                                    >
                                                        <span className="min-w-0 flex-1 truncate">{item.label}</span>
                                                        {isSelected ? (
                                                            <Check className="size-4 shrink-0 text-primary" strokeWidth={2.5} aria-hidden />
                                                        ) : null}
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </li>
                            ))
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
