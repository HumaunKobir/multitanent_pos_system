import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { Plus, X } from 'lucide-react';
import { useId, useMemo, useRef, useState } from 'react';

import { Label as FieldLabel } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * @typedef {{ value: string; label: string }} SmartMultiSelectOption
 */

/**
 * @param {{
 *   id?: string;
 *   label?: string;
 *   options: SmartMultiSelectOption[];
 *   value: string[];
 *   onValueChange: (values: string[]) => void;
 *   placeholder?: string;
 *   disabled?: boolean;
 *   creatable?: boolean;
 *   onCreateOption?: (query: string) => Promise<void>;
 *   className?: string;
 *   triggerClassName?: string;
 * }} props
 */
export function SmartMultiSelect({
    id: idProp,
    label,
    options,
    value,
    onValueChange,
    placeholder = 'Search and select…',
    disabled = false,
    creatable = false,
    onCreateOption,
    className,
    triggerClassName,
}) {
    const values = Array.isArray(value) ? value : value ? [String(value)] : [];
    const reactId = useId();
    const listboxId = idProp ?? `smart-multi-select-${reactId}`;
    const inputId = `${listboxId}-input`;
    const inputRef = useRef(/** @type {HTMLInputElement | null} */ (null));
    const [query, setQuery] = useState('');

    const selectedSet = useMemo(() => new Set(values), [values]);

    const selectedOptions = useMemo(() => {
        const known = options.filter((option) => selectedSet.has(option.value));

        const knownValues = new Set(known.map((option) => option.value));

        const legacy = values
            .filter((item) => !knownValues.has(item))
            .map((item) => ({ value: item, label: item }));

        return [...known, ...legacy];
    }, [options, values, selectedSet]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return options.filter((option) => {
            if (selectedSet.has(option.value)) {
                return false;
            }

            if (!q) {
                return true;
            }

            return option.label.toLowerCase().includes(q);
        });
    }, [options, query, selectedSet]);

    function addValue(optionValue) {
        if (!selectedSet.has(optionValue)) {
            onValueChange([...values, optionValue]);
        }

        setQuery('');
    }

    function removeValue(optionValue) {
        onValueChange(values.filter((item) => item !== optionValue));
    }

    return (
        <div className={cn('w-full max-w-full min-w-0', className)}>
            {label ? (
                <FieldLabel htmlFor={inputId} className="mb-1.5 block">
                    {label}
                </FieldLabel>
            ) : null}

            <Combobox
                value={null}
                onChange={(option) => {
                    if (!option?.value) return;
                    if (option.isCreate) {
                        if (onCreateOption) void onCreateOption(option.createQuery);
                        return;
                    }
                    addValue(option.value);
                }}
                onClose={() => setQuery('')}
                disabled={disabled}
                immediate
            >
                {({ open }) => (
                    <div className="relative w-full max-w-full min-w-0">
                        <div
                            className={cn(
                                'relative w-full max-w-full min-w-0 border border-input bg-background shadow-xs transition-shadow',
                                'has-[[data-slot=combobox-input]:focus-visible]:ring-[3px] has-[[data-slot=combobox-input]:focus-visible]:ring-ring/50',
                                disabled && 'pointer-events-none opacity-50',
                                triggerClassName,
                            )}
                            onClick={() => inputRef.current?.focus()}
                        >
                            <div className="flex min-h-8 flex-wrap items-center gap-1 px-2 py-1.5">
                                {selectedOptions.map((option) => (
                                    <span
                                        key={option.value}
                                        className="inline-flex items-center gap-1 bg-indigo-600 px-2 py-0.5 text-[11px] leading-4 font-medium text-white shadow-sm shadow-indigo-500/40"
                                    >
                                        {option.label}
                                        <button
                                            type="button"
                                            onMouseDown={(event) => {
                                                event.preventDefault();
                                                event.stopPropagation();
                                                removeValue(option.value);
                                            }}
                                            onClick={(event) => {
                                                // Keyboard activation (Enter/Space) fires click without a
                                                // preceding mousedown — detail === 0 identifies that case.
                                                if (event.detail === 0) {
                                                    event.stopPropagation();
                                                    removeValue(option.value);
                                                }
                                            }}
                                            className="opacity-80 hover:opacity-100"
                                            aria-label={`Remove ${option.label}`}
                                        >
                                            <X className="size-2.5" />
                                        </button>
                                    </span>
                                ))}
                                <ComboboxInput
                                    ref={inputRef}
                                    id={inputId}
                                    data-slot="combobox-input"
                                    className="min-w-16 flex-1 border-0 bg-transparent py-0.5 text-xs outline-none"
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    onMouseDown={(event) => {
                                        if (open || document.activeElement !== inputRef.current) {
                                            return;
                                        }

                                        event.preventDefault();
                                        inputRef.current?.blur();
                                        requestAnimationFrame(() => inputRef.current?.focus());
                                    }}
                                    placeholder={values.length === 0 ? placeholder : 'Search…'}
                                    autoComplete="off"
                                />
                            </div>
                        </div>

                        <ComboboxOptions
                            anchor="bottom start"
                            transition
                            className={cn(
                                'z-50 w-(--input-width) [--anchor-gap:4px] max-h-60 overflow-auto border border-border bg-popover py-1 text-popover-foreground shadow-md',
                                'transition duration-100 ease-out data-closed:opacity-0 data-leave:data-closed:opacity-0',
                            )}
                        >
                            {filtered.map((option) => (
                                <ComboboxOption
                                    key={option.value}
                                    value={option}
                                    className={({ focus }) =>
                                        cn(
                                            'flex w-full min-w-0 cursor-pointer border-l-2 border-transparent py-2 pr-3 pl-2 text-sm',
                                            focus && 'border-primary bg-accent/80 text-accent-foreground',
                                        )
                                    }
                                >
                                    <span className="min-w-0 flex-1 truncate">{option.label}</span>
                                </ComboboxOption>
                            ))}
                            {creatable && query.trim() && filtered.length === 0 && (
                                <ComboboxOption
                                    value={{ value: `__create__`, label: query.trim(), isCreate: true, createQuery: query.trim() }}
                                    className={({ focus }) =>
                                        cn(
                                            'flex w-full min-w-0 cursor-pointer items-center gap-1.5 border-l-2 border-transparent py-2 pr-3 pl-2 text-sm font-medium text-primary',
                                            focus && 'border-primary bg-accent/80',
                                        )
                                    }
                                >
                                    <Plus className="size-3.5 shrink-0" />
                                    Add &ldquo;{query.trim()}&rdquo;
                                </ComboboxOption>
                            )}
                            {filtered.length === 0 && !query.trim() && (
                                <p className="px-3 py-2 text-xs text-muted-foreground">All options selected.</p>
                            )}
                            {filtered.length === 0 && query.trim() && !creatable && (
                                <p className="px-3 py-2 text-xs text-muted-foreground">No matches.</p>
                            )}
                        </ComboboxOptions>
                    </div>
                )}
            </Combobox>
        </div>
    );
}
