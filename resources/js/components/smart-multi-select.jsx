import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { X } from 'lucide-react';
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
    className,
    triggerClassName,
}) {
    const reactId = useId();
    const listboxId = idProp ?? `smart-multi-select-${reactId}`;
    const inputId = `${listboxId}-input`;
    const inputRef = useRef(/** @type {HTMLInputElement | null} */ (null));
    const [query, setQuery] = useState('');

    const selectedSet = useMemo(() => new Set(value), [value]);

    const selectedOptions = useMemo(() => {
        const known = options.filter((option) => selectedSet.has(option.value));

        const knownValues = new Set(known.map((option) => option.value));

        const legacy = value
            .filter((item) => !knownValues.has(item))
            .map((item) => ({ value: item, label: item }));

        return [...known, ...legacy];
    }, [options, value, selectedSet]);

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
            onValueChange([...value, optionValue]);
        }

        setQuery('');
    }

    function removeValue(optionValue) {
        onValueChange(value.filter((item) => item !== optionValue));
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
                    if (option?.value) {
                        addValue(option.value);
                    }
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
                                        {option.value}
                                        <button
                                            type="button"
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                removeValue(option.value);
                                            }}
                                            className="opacity-80 hover:opacity-100"
                                            aria-label={`Remove ${option.value}`}
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
                                    placeholder={value.length === 0 ? placeholder : 'Search…'}
                                    autoComplete="off"
                                />
                            </div>
                        </div>

                        <ComboboxOptions
                            portal={false}
                            modal={false}
                            transition
                            className={cn(
                                'absolute top-full right-0 left-0 z-50 mt-1 max-h-60 min-w-0 overflow-auto border border-border bg-popover py-1 text-popover-foreground shadow-md',
                                'w-full max-w-full',
                                'transition duration-100 ease-out data-closed:opacity-0 data-leave:data-closed:opacity-0',
                                !open && 'pointer-events-none',
                            )}
                        >
                            {filtered.length === 0 ? (
                                <p className="px-3 py-2 text-xs text-muted-foreground">
                                    {query.trim() ? 'No matches.' : 'All tags selected.'}
                                </p>
                            ) : (
                                filtered.map((option) => (
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
                                ))
                            )}
                        </ComboboxOptions>
                    </div>
                )}
            </Combobox>
        </div>
    );
}
