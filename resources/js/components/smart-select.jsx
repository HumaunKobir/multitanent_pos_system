import { useAppToast } from '@/contexts/app-toast-context';
import { Check, Plus } from 'lucide-react';
import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

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
 *   optionsClassName?: string;
 *   autoComplete?: string;
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
    optionsClassName,
    autoComplete = 'off',
}) {
    const toast = useAppToast();
    const reactId = useId();
    const listboxId = idProp ?? `smart-select-${reactId}`;
    const inputId = `${listboxId}-input`;
    const inputName = `${listboxId}-combobox`;
    const containerRef = useRef(/** @type {HTMLDivElement | null} */ (null));
    const inputRef = useRef(/** @type {HTMLInputElement | null} */ (null));
    const listboxRef = useRef(/** @type {HTMLDivElement | null} */ (null));

    const [open, setOpen] = useState(false);
    const [dropdownStyle, setDropdownStyle] = useState({
        top: 0,
        left: 0,
        width: 0,
        position: 'fixed',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [modalSku, setModalSku] = useState('');
    const [modalNotes, setModalNotes] = useState('');
    const [pendingQuery, setPendingQuery] = useState('');
    const [instantLoading, setInstantLoading] = useState(false);
    const [autofillGuard, setAutofillGuard] = useState(searchable);

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

    const resolvePortalContainer = useCallback(() => {
        const dialogContent = containerRef.current?.closest('[data-slot="dialog-content"]');

        if (dialogContent instanceof HTMLElement) {
            return dialogContent;
        }

        return document.body;
    }, []);

    const updateDropdownPosition = useCallback(() => {
        const input = inputRef.current;

        if (!input) {
            return;
        }

        const rect = input.getBoundingClientRect();
        const portalTarget = resolvePortalContainer();
        const dropdownWidth = Math.min(
            Math.max(rect.width, 240),
            Math.max(160, window.innerWidth - Math.max(rect.left, 8) - 12),
        );

        if (portalTarget !== document.body) {
            const containerRect = portalTarget.getBoundingClientRect();

            setDropdownStyle({
                top: rect.bottom - containerRect.top + 4,
                left: rect.left - containerRect.left,
                width: dropdownWidth,
                position: 'absolute',
            });

            return;
        }

        setDropdownStyle({
            top: rect.bottom + 4,
            left: rect.left,
            width: dropdownWidth,
            position: 'fixed',
        });
    }, [resolvePortalContainer]);

    useEffect(() => {
        if (!open) {
            return;
        }

        updateDropdownPosition();

        window.addEventListener('scroll', updateDropdownPosition, true);
        window.addEventListener('resize', updateDropdownPosition);

        return () => {
            window.removeEventListener('scroll', updateDropdownPosition, true);
            window.removeEventListener('resize', updateDropdownPosition);
        };
    }, [open, updateDropdownPosition]);

    useEffect(() => {
        function handlePointerDown(event) {
            const target = event.target;

            if (!(target instanceof Node)) {
                return;
            }

            if (containerRef.current?.contains(target)) {
                return;
            }

            if (listboxRef.current?.contains(target)) {
                return;
            }

            if (target instanceof Element && target.closest('[data-slot="smart-select-listbox"]')) {
                return;
            }

            setOpen(false);
            setQuery('');
        }

        document.addEventListener('mousedown', handlePointerDown);

        return () => document.removeEventListener('mousedown', handlePointerDown);
    }, []);

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

    function openDropdown() {
        if (disabled) {
            return;
        }

        setAutofillGuard(false);
        setOpen(true);
        setQuery('');
        requestAnimationFrame(() => {
            inputRef.current?.focus();
            updateDropdownPosition();
        });
    }

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
            setOpen(false);
        },
        [createMode, newValueFromLabel, onModalCreate, onOptionsChange, onValueChange, options],
    );

    const handleOptionSelect = useCallback(
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
                    setOpen(false);
                    setQuery('');

                    return;
                }

                if (createMode === 'instant' && onModalCreate) {
                    setInstantLoading(true);
                    try {
                        const next = await onModalCreate({ label: q, sku: '', notes: '' });
                        onOptionsChange?.([...options, next]);
                        onValueChange(next.value);
                        setQuery('');
                        setOpen(false);
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
            setOpen(false);
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

    function handleInputChange(event) {
        const next = event.target.value;

        if (!searchable) {
            return;
        }

        setQuery(next);

        if (!open) {
            setOpen(true);
        }
    }

    function handleInputKeyDown(event) {
        if (event.key === 'Enter') {
            if (open) {
                event.preventDefault();
                event.stopPropagation();
                if (listOptions.length > 0) {
                    void handleOptionSelect(listOptions[0]);
                }
            }
        } else if (event.key === 'Escape') {
            if (open) {
                event.preventDefault();
                event.stopPropagation();
                setOpen(false);
                setQuery('');
            }
        }
    }

    return (
        <div ref={containerRef} className={cn('relative w-full max-w-full min-w-0', className)}>
            {label ? (
                <FieldLabel htmlFor={inputId} className="mb-1.5 block">
                    {label}
                </FieldLabel>
            ) : null}

            <Input
                ref={inputRef}
                id={inputId}
                name={inputName}
                type={searchable ? 'search' : 'text'}
                data-slot="smart-select-input"
                data-1p-ignore="true"
                data-lpignore="true"
                data-form-type="other"
                value={open ? query : (selected?.label ?? '')}
                onChange={handleInputChange}
                onKeyDown={handleInputKeyDown}
                onFocus={() => setAutofillGuard(false)}
                onPointerDown={() => openDropdown()}
                placeholder={placeholder}
                readOnly={!searchable || autofillGuard}
                disabled={disabled}
                autoComplete={autoComplete}
                enterKeyHint="search"
                className={cn(
                    'bg-background',
                    (!searchable || autofillGuard) && 'cursor-pointer',
                    !searchable && open && 'cursor-pointer',
                    triggerClassName,
                )}
                aria-expanded={open}
                aria-haspopup="listbox"
                role="combobox"
            />

            {open &&
                typeof document !== 'undefined' &&
                createPortal(
                    <div
                        ref={listboxRef}
                        role="listbox"
                        data-slot="smart-select-listbox"
                        style={{
                            position: dropdownStyle.position,
                            top: dropdownStyle.top,
                            left: dropdownStyle.left,
                            width: dropdownStyle.width,
                        }}
                        className={cn(
                            'pointer-events-auto z-100 max-h-48 overflow-y-auto overscroll-contain border border-border bg-popover py-1 text-popover-foreground shadow-md',
                            optionsClassName,
                        )}
                        onMouseDown={(event) => event.preventDefault()}
                        onWheel={handleListWheel}
                        onTouchMove={(event) => event.stopPropagation()}
                    >
                        {listOptions.length === 0 ? (
                            <p className="px-3 py-2 text-sm text-muted-foreground">No matches.</p>
                        ) : (
                            listOptions.map((opt) => {
                                const isSelected = opt.value === value;

                                return (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        role="option"
                                        aria-selected={isSelected}
                                        className={cn(
                                            'flex w-full min-w-0 cursor-pointer items-center justify-between gap-2 border-l-2 border-transparent py-2 pr-3 pl-2 text-left text-sm hover:bg-accent/80',
                                            isSelected && 'border-primary bg-accent/50',
                                            opt.isCreate && 'font-medium',
                                        )}
                                        onMouseDown={(event) => {
                                            event.preventDefault();
                                            event.stopPropagation();
                                            void handleOptionSelect(opt);
                                        }}
                                    >
                                        <div className="flex min-w-0 flex-1 items-center gap-2">
                                            {opt.isCreate ? (
                                                <Plus className="size-4 shrink-0 text-primary" aria-hidden />
                                            ) : null}
                                            <span className="min-w-0 flex-1 whitespace-normal break-words">
                                                {opt.isCreate && instantLoading ? 'Creating…' : opt.label}
                                            </span>
                                        </div>
                                        {opt.isCreate || isSelected ? (
                                            <Check className="size-4 shrink-0 text-primary" strokeWidth={2.5} aria-hidden />
                                        ) : null}
                                    </button>
                                );
                            })
                        )}
                    </div>,
                    resolvePortalContainer(),
                )}

            <Dialog
                open={modalOpen}
                onOpenChange={(nextOpen) => {
                    setModalOpen(nextOpen);

                    if (!nextOpen) {
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
