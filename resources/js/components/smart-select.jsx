import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { useAppToast } from '@/contexts/app-toast-context';
import { Check, Plus } from 'lucide-react';
import { useCallback, useId, useMemo, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label as FieldLabel } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const CREATE_PREFIX = '__smart_select_create__:';

/**
 * @typedef {{ value: string; label: string }} SmartSelectOption
 */

/**
 * @typedef {{ label: string; sku: string; notes: string }} SmartSelectModalPayload
 */

/**
 * @param {{
 *   id?: string;
 *   label?: string;
 *   options: SmartSelectOption[];
 *   value: string | null;
 *   onValueChange: (value: string | null) => void;
 *   onOptionsChange?: (next: SmartSelectOption[]) => void;
 *   placeholder?: string;
 *   disabled?: boolean;
 *   searchable?: boolean;
 *   creatable?: boolean;
 *   createMode?: 'inline' | 'modal';
 *   createRowLabel?: (query: string) => string;
 *   modalTitle?: string;
 *   modalDescription?: string;
 *   onModalCreate?: (payload: SmartSelectModalPayload) => SmartSelectOption | Promise<SmartSelectOption>;
 *   className?: string;
 *   triggerClassName?: string;
 * }} props
 */
export function SmartSelect({
    id: idProp,
    label,
    options,
    value,
    onValueChange,
    onOptionsChange,
    placeholder = 'Select or search…',
    disabled = false,
    searchable = true,
    creatable = false,
    createMode = 'inline',
    createRowLabel = (query) => `Add "${query.trim()}"`,
    modalTitle = 'Add new item',
    modalDescription = 'Provide details. The item will be saved and selected.',
    onModalCreate,
    className,
    triggerClassName,
}) {
    const toast = useAppToast();
    const reactId = useId();
    const listboxId = idProp ?? `smart-select-${reactId}`;
    const inputId = `${listboxId}-input`;
    const inputRef = useRef(/** @type {HTMLInputElement | null} */ (null));

    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [modalSku, setModalSku] = useState('');
    const [modalNotes, setModalNotes] = useState('');
    const [pendingQuery, setPendingQuery] = useState('');
    const [instantLoading, setInstantLoading] = useState(false);

    const selected = useMemo(() => options.find((o) => o.value === value) ?? null, [options, value]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        if (!searchable || !q) {
            return options;
        }

        return options.filter((o) => o.label.toLowerCase().includes(q));
    }, [options, query, searchable]);

    const exactExists =
        query.trim().length > 0 &&
        options.some((o) => o.label.toLowerCase() === query.trim().toLowerCase());

    const canShowCreateRow =
        creatable &&
        searchable &&
        Boolean(onOptionsChange) &&
        query.trim().length > 0 &&
        filtered.length === 0 &&
        !exactExists;

    const createOption = useMemo(() => {
        if (!canShowCreateRow) {
            return null;
        }

        const q = query.trim();

        return {
            value: `${CREATE_PREFIX}${q}`,
            label: createRowLabel(q),
            isCreate: true,
            createQuery: q,
        };
    }, [canShowCreateRow, query, createRowLabel]);

    const listOptions = useMemo(() => {
        if (createOption) {
            return [...filtered, createOption];
        }

        return filtered;
    }, [filtered, createOption]);

    const newValueFromLabel = useCallback((lbl) => {
        const base = lbl
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '');
        const slug = base.length > 0 ? base : 'item';

        return `${slug}-${Date.now().toString(36)}`;
    }, []);

    const commitNewOption = useCallback(
        async (lbl, extra) => {
            let next = { value: newValueFromLabel(lbl), label: lbl.trim() };

            if (createMode === 'modal' && onModalCreate) {
                next = await onModalCreate({
                    label: lbl.trim(),
                    sku: extra?.sku ?? '',
                    notes: extra?.notes ?? '',
                });
            }

            onOptionsChange?.([...options, next]);
            onValueChange(next.value);
            setQuery('');
        },
        [createMode, newValueFromLabel, onModalCreate, onOptionsChange, onValueChange, options],
    );

    const handleComboboxChange = useCallback(
        async (opt) => {
            if (!opt) {
                return;
            }

            if (opt.isCreate && opt.createQuery) {
                const q = opt.createQuery;

                if (createMode === 'modal') {
                    setPendingQuery(q);
                    setModalSku('');
                    setModalNotes('');
                    setModalOpen(true);

                    return;
                }

                if (createMode === 'instant' && onModalCreate) {
                    setInstantLoading(true);
                    try {
                        const next = await onModalCreate({ label: q, sku: '', notes: '' });
                        onOptionsChange?.([...options, next]);
                        onValueChange(next.value);
                        setQuery('');
                        toast.success(`"${next.label}" created.`);
                    } catch {
                        toast.error('Failed to create. Try again.');
                    } finally {
                        setInstantLoading(false);
                    }
                    return;
                }

                if (!onOptionsChange) {
                    return;
                }

                await commitNewOption(q);

                return;
            }

            onValueChange(opt.value);
            setQuery('');
        },
        [commitNewOption, createMode, onModalCreate, onOptionsChange, onValueChange, options, toast],
    );

    const handleModalSave = useCallback(async () => {
        if (!pendingQuery.trim() || !onOptionsChange) {
            return;
        }

        await commitNewOption(pendingQuery, { sku: modalSku, notes: modalNotes });
        setModalOpen(false);
        setPendingQuery('');
    }, [commitNewOption, modalNotes, modalSku, onOptionsChange, pendingQuery]);

    return (
        <div className={cn('w-full max-w-full min-w-0', className)}>
            {label ? (
                <FieldLabel htmlFor={inputId} className="mb-1.5 block">
                    {label}
                </FieldLabel>
            ) : null}

            <Combobox
                value={selected}
                onChange={handleComboboxChange}
                onClose={() => setQuery('')}
                disabled={disabled}
                immediate
                by={(a, b) => a?.value === b?.value}
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
                        >
                            <ComboboxInput
                                ref={inputRef}
                                id={inputId}
                                data-slot="combobox-input"
                                className={cn(
                                    'block w-full max-w-full min-w-0 overflow-x-auto border-0 bg-transparent py-2 pr-10 pl-3 text-sm outline-none',
                                    !searchable && 'cursor-default',
                                )}
                                displayValue={(opt) => opt?.label ?? ''}
                                onChange={(e) => setQuery(e.target.value)}
                                onMouseDown={(event) => {
                                    if (open || document.activeElement !== inputRef.current) {
                                        return;
                                    }

                                    event.preventDefault();
                                    inputRef.current?.blur();
                                    requestAnimationFrame(() => inputRef.current?.focus());
                                }}
                                placeholder={placeholder}
                                readOnly={!searchable}
                                autoComplete="off"
                            />
                        </div>

                        <ComboboxOptions
                            anchor="bottom start"
                            transition
                            className={cn(
                                'z-50 w-(--input-width) [--anchor-gap:4px] max-h-60 overflow-auto border border-border bg-popover py-1 text-popover-foreground shadow-md',
                                'transition duration-100 ease-out data-closed:opacity-0 data-leave:data-closed:opacity-0',
                            )}
                        >
                            {listOptions.length === 0 ? (
                                <p className="px-3 py-2 text-sm text-muted-foreground">No matches.</p>
                            ) : (
                                listOptions.map((opt) => (
                                    <ComboboxOption
                                        key={opt.value}
                                        value={opt}
                                        className={({ focus }) =>
                                            cn(
                                                'flex w-full min-w-0 cursor-pointer border-l-2 border-transparent py-2 pr-3 pl-2 text-sm',
                                                focus && 'border-primary bg-accent/80 text-accent-foreground',
                                                opt.isCreate && 'font-medium',
                                            )
                                        }
                                    >
                                        {({ selected }) => (
                                            <div className="flex w-full min-w-0 items-center justify-between gap-2">
                                                <div className="flex min-w-0 flex-1 items-center gap-2">
                                                    {opt.isCreate ? (
                                                        <Plus className="size-4 shrink-0 text-primary" aria-hidden />
                                                    ) : null}
                                                    <span className="min-w-0 flex-1 truncate">
                                                        {opt.isCreate && instantLoading ? 'Creating…' : opt.label}
                                                    </span>
                                                </div>
                                                <span className="flex size-5 shrink-0 items-center justify-center text-primary">
                                                    {opt.isCreate || selected ? (
                                                        <Check className="size-4" strokeWidth={2.5} aria-hidden />
                                                    ) : null}
                                                </span>
                                            </div>
                                        )}
                                    </ComboboxOption>
                                ))
                            )}
                        </ComboboxOptions>
                    </div>
                )}
            </Combobox>

            <Dialog
                open={modalOpen}
                onOpenChange={(open) => {
                    setModalOpen(open);

                    if (!open) {
                        setPendingQuery('');
                        setModalSku('');
                        setModalNotes('');
                    }
                }}
            >
                <DialogContent className="rounded-none sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{modalTitle}</DialogTitle>
                        <DialogDescription>{modalDescription}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 py-2">
                        <div className="space-y-1.5">
                            <FieldLabel htmlFor={`${listboxId}-modal-name`}>Name</FieldLabel>
                            <Input
                                id={`${listboxId}-modal-name`}
                                value={pendingQuery}
                                onChange={(e) => setPendingQuery(e.target.value)}
                                className="rounded-none"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <FieldLabel htmlFor={`${listboxId}-modal-sku`}>SKU / code</FieldLabel>
                            <Input
                                id={`${listboxId}-modal-sku`}
                                value={modalSku}
                                onChange={(e) => setModalSku(e.target.value)}
                                placeholder="e.g. PRD-2048"
                                className="rounded-none"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <FieldLabel htmlFor={`${listboxId}-modal-notes`}>Notes</FieldLabel>
                            <Input
                                id={`${listboxId}-modal-notes`}
                                value={modalNotes}
                                onChange={(e) => setModalNotes(e.target.value)}
                                placeholder="Supplier, lead time, …"
                                className="rounded-none"
                            />
                        </div>
                    </div>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" className="rounded-none" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            className="rounded-none"
                            disabled={!pendingQuery.trim() || !onOptionsChange}
                            onClick={() => void handleModalSave()}
                        >
                            Save & select
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
