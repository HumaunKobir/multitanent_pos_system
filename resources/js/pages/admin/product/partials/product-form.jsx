import { SmartMultiSelect } from '@/components/smart-multi-select';
import { SmartSelect } from '@/components/smart-select';
import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Link } from '@inertiajs/react';
import { AlignLeft, DollarSign, GitBranch, ImagePlus, Images, Info, Plus, Settings, Trash2, X } from 'lucide-react';
import { forwardRef, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';
import {
    allCombinationsHavePrices,
    buildCombinationsFromVariantRows,
    buildVariationBuilderState,
    getSharedCombinationPrices,
    someCombinationsMissingPrices,
} from '@/lib/variation-utils';
import { calculateInitialStockTotal } from '@/lib/initial-stock-settlement';

function mapSupplierOptions(suppliers = []) {
    return suppliers.map((supplier) => ({
        value: String(supplier.id),
        label: supplier.company_name
            ? `${supplier.company_name} · ${supplier.name} · ${supplier.phone ?? ''}`
            : `${supplier.name}${supplier.phone ? ` · ${supplier.phone}` : ''}`,
    }));
}

function resolveDefaultPaymentAccountId(paymentAccountOptions = [], currentValue = '') {
    if (currentValue) {
        return String(currentValue);
    }

    return paymentAccountOptions[0]?.value ?? '';
}

function getXsrf() {
    return decodeURIComponent(document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}

async function quickCreate(routeName, name, branchId = null) {
    const payload = { name, status: 1 };

    if (branchId) {
        payload.branch_id = Number(branchId);
    }

    const res = await fetch(route(routeName), {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrf(),
        },
        body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message ?? 'Failed to create');
    return { value: String(json.value), label: json.label };
}

function mapSelectOptions(records = {}) {
    return Object.entries(records).map(([value, label]) => ({ value: String(value), label }));
}

function syncSingleSelectValue(form, field, options) {
    const selectedValue = form.data[field];

    if (selectedValue == null || selectedValue === '') {
        return;
    }

    const exists = options.some((option) => String(option.value) === String(selectedValue));

    if (!exists) {
        form.setData(field, '');
    }
}

function mergePresetOptions(options, selected = []) {
    const merged = [...options];

    selected.forEach((item) => {
        const id = String(item.id);

        if (!merged.some((option) => String(option.id) === id)) {
            merged.push({
                id,
                value: item.label,
                label: item.label,
            });
        }
    });

    return merged;
}

function withSelectedOption(options, selectedId, selectedLabel) {
    if (!selectedId || !selectedLabel) {
        return options;
    }

    const value = String(selectedId);

    if (options.some((option) => option.value === value)) {
        return options;
    }

    return [{ value, label: selectedLabel }, ...options];
}

function mapSelectOptionsWithSelected(records = {}, selected = null) {
    return withSelectedOption(mapSelectOptions(records), selected?.id, selected?.label);
}

function syncMultiSelectValues(form, field, options) {
    const currentValues = form.data[field] || [];
    const optionIds = new Set(options.map((option) => String(option.id ?? option.value)));
    const nextValues = currentValues.filter((value) => optionIds.has(String(value)));

    if (nextValues.length !== currentValues.length) {
        form.setData(field, nextValues);
    }
}

function CKEditorField({ id, value, onChange }) {
    const textareaRef = useRef(null);
    const editorRef = useRef(null);

    useEffect(() => {
        if (!textareaRef.current || typeof window.CKEDITOR === 'undefined') return;

        if (window.CKEDITOR.instances[id]) {
            window.CKEDITOR.instances[id].destroy(true);
        }

        editorRef.current = window.CKEDITOR.replace(textareaRef.current, {
            height: 200,
            versionCheck: false,
            removePlugins: 'elementspath',
            resize_enabled: false,
        });

        editorRef.current.on('instanceReady', () => {
            editorRef.current.setData(value || '');
        });

        editorRef.current.on('change', () => {
            onChange(editorRef.current.getData());
        });

        editorRef.current.on('blur', () => {
            onChange(editorRef.current.getData());
        });

        return () => {
            if (window.CKEDITOR.instances[id]) {
                window.CKEDITOR.instances[id].destroy(true);
                editorRef.current = null;
            }
        };
    }, []);

    return <textarea id={id} ref={textareaRef} defaultValue={value} className="hidden" />;
}

function Card({ title, icon: Icon, children }) {
    return (
        <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
            <div className="flex items-center gap-2.5 bg-blue-950 px-4 py-2.5">
                {Icon && (
                    <div className="flex size-6 items-center justify-center rounded bg-white/15">
                        <Icon className="size-3.5 text-white" />
                    </div>
                )}
                <h2 className="text-sm font-semibold uppercase tracking-wide text-white">{title}</h2>
            </div>
            <div className="p-2">{children}</div>
        </div>
    );
}

function Field({ label, required, error, children }) {
    return (
        <div>
            <Label className="mb-1 block text-xs font-medium">
                {label}
                {required && <RequiredMark />}
            </Label>
            {children}
            {error && <p className="mt-0.5 text-xs text-destructive">{error}</p>}
        </div>
    );
}

function MultiToggle({ options, selected, onChange, colorMode = false }) {
    const selectedNums = (selected || []).map(Number);

    function toggle(id) {
        const num = Number(id);
        onChange(selectedNums.includes(num) ? selectedNums.filter((v) => v !== num) : [...selectedNums, num]);
    }

    if (!options.length) return <p className="text-xs text-muted-foreground">No options available.</p>;

    return (
        <div className="flex flex-wrap gap-1.5">
            {options.map((opt) => {
                const id = opt.value ?? opt.id;
                const label = opt.label ?? opt.name;
                const isOn = selectedNums.includes(Number(id));
                return (
                    <button
                        key={id}
                        type="button"
                        onClick={() => toggle(id)}
                        className={[
                            'flex items-center gap-1 border px-2.5 py-1 text-xs font-medium transition-colors',
                            isOn
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background text-muted-foreground hover:border-primary/60 hover:text-foreground',
                        ].join(' ')}
                    >
                        {colorMode && opt.code && (
                            <span className="size-2.5 border border-white/30" style={{ backgroundColor: opt.code }} />
                        )}
                        {label}
                    </button>
                );
            })}
        </div>
    );
}

function SingleImageUpload({ existingPath, onChange }) {
    const inputRef = useRef(null);
    const [preview, setPreview] = useState(null);
    const [cleared, setCleared] = useState(false);

    const displaySrc = preview ?? (!cleared && existingPath ? `/storage/${existingPath}` : null);

    function handleFile(file) {
        if (!file) return;
        setPreview(URL.createObjectURL(file));
        setCleared(false);
        onChange(file);
        if (inputRef.current) inputRef.current.value = '';
    }

    function clear(e) {
        e.stopPropagation();
        setPreview(null);
        setCleared(true);
        onChange(null);
        if (inputRef.current) inputRef.current.value = '';
    }

    return (
        <div>
            {displaySrc ? (
                <div
                    className="group relative cursor-pointer border"
                    onClick={() => inputRef.current?.click()}
                >
                    <img src={displaySrc} alt="" className="h-40 w-full object-cover" />
                    <div className="absolute inset-0 flex items-center justify-center bg-black/0 transition-colors group-hover:bg-black/20">
                        <span className="hidden rounded bg-black/60 px-2 py-1 text-[11px] text-white group-hover:block">
                            Click to replace
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={clear}
                        className="absolute top-1.5 right-1.5 bg-red-500/90 p-0.5 text-white hover:bg-red-600"
                    >
                        <X className="size-3" />
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    onDrop={(e) => { e.preventDefault(); handleFile(e.dataTransfer.files[0]); }}
                    onDragOver={(e) => e.preventDefault()}
                    className="flex w-full flex-col items-center justify-center gap-1.5 border border-dashed border-border bg-muted/20 py-4 text-xs text-muted-foreground transition-colors hover:bg-muted/40"
                >
                    <ImagePlus className="size-5 opacity-40" />
                    <span>Click or drop image</span>
                    <span className="text-[10px] opacity-60">JPG, PNG, WebP — max 3MB</span>
                </button>
            )}
            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(e) => handleFile(e.target.files[0])}
            />
        </div>
    );
}

function MultiImageUpload({ onChange }) {
    const inputRef = useRef(null);
    const [items, setItems] = useState([]);

    function handleFiles(newFiles) {
        if (!newFiles.length) return;
        const added = Array.from(newFiles).map((f) => ({ file: f, url: URL.createObjectURL(f) }));
        setItems((prev) => {
            const next = [...prev, ...added];
            onChange(next.map((i) => i.file));
            return next;
        });
        if (inputRef.current) inputRef.current.value = '';
    }

    function removeItem(index) {
        setItems((prev) => {
            URL.revokeObjectURL(prev[index].url);
            const next = prev.filter((_, i) => i !== index);
            onChange(next.map((i) => i.file));
            return next;
        });
    }

    return (
        <div>
            {items.length > 0 && (
                <div className="mb-2 flex flex-wrap gap-1.5">
                    {items.map((item, i) => (
                        <div key={item.url} className="relative">
                            <img src={item.url} alt="" className="h-14 w-14 border object-cover" />
                            <button
                                type="button"
                                onClick={() => removeItem(i)}
                                className="absolute -top-1 -right-1 flex size-4 items-center justify-center bg-red-500 text-white hover:bg-red-600"
                            >
                                <X className="size-2.5" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                onDrop={(e) => { e.preventDefault(); handleFiles(e.dataTransfer.files); }}
                onDragOver={(e) => e.preventDefault()}
                className="flex w-full flex-col items-center justify-center gap-1.5 border border-dashed border-border bg-muted/20 py-4 text-xs text-muted-foreground transition-colors hover:bg-muted/40"
            >
                <ImagePlus className="size-5 opacity-40" />
                <span>{items.length > 0 ? 'Add more photos' : 'Click or drop photos'}</span>
                <span className="text-[10px] opacity-60">JPG, PNG, WebP — max 3MB</span>
            </button>
            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                className="hidden"
                onChange={(e) => handleFiles(e.target.files)}
            />
        </div>
    );
}

function TagInput({ tags, onChange, placeholder = 'Type value, press Space or Enter…', disabled = false }) {
    const [input, setInput] = useState('');

    function commit() {
        if (disabled) {
            return;
        }

        const v = input.trim();
        if (v && !tags.includes(v)) {
            onChange([...tags, v]);
        }
        setInput('');
    }

    function handleKeyDown(e) {
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            commit();
        } else if (e.key === 'Backspace' && !input && tags.length > 0) {
            onChange(tags.slice(0, -1));
        }
    }

    return (
        <div
            className={`flex min-h-9.5 max-w-full min-w-0 flex-wrap items-center gap-1 overflow-hidden border border-input bg-background px-3 py-2 shadow-xs ${disabled ? 'cursor-not-allowed opacity-70' : 'cursor-text focus-within:ring-[3px] focus-within:ring-ring/50'}`}
            onClick={(e) => !disabled && e.currentTarget.querySelector('input')?.focus()}
        >
            {tags.map((tag) => (
                <span
                    key={tag}
                    title={tag}
                    className="flex max-w-full min-w-0 items-center gap-1 bg-blue-600 px-2 py-0.5 text-[11px] leading-4 text-white shadow shadow-blue-500/50"
                >
                    <span className="min-w-0 max-w-[6.5rem] truncate sm:max-w-[9rem]">{tag}</span>
                    {!disabled && (
                        <button type="button" onClick={() => onChange(tags.filter((t) => t !== tag))} className="shrink-0 opacity-80 hover:opacity-100">
                            <X className="size-2.5" />
                        </button>
                    )}
                </span>
            ))}
            {!disabled && (
                <input
                    className="min-w-12 max-w-full flex-1 bg-transparent text-xs outline-none"
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onBlur={commit}
                    placeholder={tags.length === 0 ? placeholder : ''}
                />
            )}
        </div>
    );
}

const DEFAULT_VARIANT_ROWS = [
    { id: 1, name: 'Color', values: [] },
    { id: 2, name: 'Size', values: [] },
];

const VARIANT_NAME_OPTIONS = [
    { value: 'Color', label: 'Color' },
    { value: 'Size', label: 'Size' },
];

function formatVariantSummary(variationData = {}) {
    return Object.entries(variationData)
        .filter(([key]) => key !== 'label')
        .map(([key, value]) => `${key}: ${value}`)
        .join(' · ');
}

const VariationBuilder = forwardRef(function VariationBuilder({
    colorOptions = [],
    sizeOptions = [],
    initialVariations = [],
    locked = false,
    defaultCombinationPrices = null,
    onChange,
    onEnabledChange,
    errors = {},
}, ref) {
    const initialState = useMemo(() => buildVariationBuilderState(initialVariations), []);
    const [enabled, setEnabled] = useState(initialState.enabled);
    const [varOptions, setVarOptions] = useState(() => {
        const names = new Set(VARIANT_NAME_OPTIONS.map((option) => option.value));
        initialState.rows.forEach((row) => {
            if (row.name) {
                names.add(row.name);
            }
        });

        return [...names].map((name) => ({ value: name, label: name }));
    });
    const [rows, setRows] = useState(() => initialState.rows);
    const [combinations, setCombinations] = useState(() => initialState.combinations);
    const [buildError, setBuildError] = useState('');
    const combinationsRef = useRef(combinations);

    useEffect(() => {
        combinationsRef.current = combinations;
    }, [combinations]);

    useEffect(() => {
        if (initialVariations.length > 0) {
            onEnabledChange?.(true);
            onChange(initialState.combinations);
        }
    }, []);

    function applyBuiltCombinations(next, error = '') {
        setBuildError(error);
        setCombinations(next);
        onChange(next);
    }

    function syncCombinationsFromRows(currentRows, existingCombinations = combinationsRef.current) {
        const { combinations: next, error } = buildCombinationsFromVariantRows(
            currentRows,
            existingCombinations,
            defaultCombinationPrices,
        );

        if (error) {
            setBuildError(error);
            return { ok: false, error };
        }

        applyBuiltCombinations(next);
        setBuildError('');

        return { ok: true, combinations: next };
    }

    useImperativeHandle(ref, () => ({
        validateBeforeSubmit() {
            if (!enabled) {
                return { ok: true };
            }

            const namedRows = rows.filter((row) => String(row.name ?? '').trim());

            if (namedRows.length === 0) {
                return {
                    ok: false,
                    error: 'Add at least one variation name or turn off "Item has variants".',
                };
            }

            const missingValues = namedRows.filter((row) => (row.values ?? []).length === 0);

            if (missingValues.length > 0) {
                return {
                    ok: false,
                    error: `Add values for: ${missingValues.map((row) => row.name).join(', ')}`,
                };
            }

            if (combinationsRef.current.length === 0) {
                return {
                    ok: false,
                    error: 'Click "Build Combinations" before saving.',
                };
            }

            return { ok: true };
        },
    }));

    function toggleEnabled(val) {
        if (locked) {
            return;
        }

        setEnabled(val);
        onEnabledChange?.(val);
        if (val) {
            setRows(DEFAULT_VARIANT_ROWS.map((row) => ({ ...row, values: [] })));
        }
        if (!val) {
            setBuildError('');
            setCombinations([]);
            onChange([]);
        }
    }

    function addRow() {
        setRows((prev) => [...prev, { id: Date.now(), name: '', values: [] }]);
    }

    function removeRow(id) {
        setRows((prev) => prev.filter((r) => r.id !== id));
    }

    function setRowName(id, name) {
        setRows((prev) => prev.map((r) => (r.id === id ? { ...r, name: name ?? '', values: [] } : r)));
    }

    function setRowValues(id, values) {
        setRows((prev) => prev.map((r) => (r.id === id ? { ...r, values } : r)));
    }

    function getValueOptions(name) {
        const source = name === 'Color' ? colorOptions : name === 'Size' ? sizeOptions : [];

        return source.map((option) => ({
            value: String(option.value ?? option.id ?? option.label),
            label: option.label ?? String(option.value ?? option.id),
        }));
    }

    function usesPresetValues(name) {
        return name === 'Color' || name === 'Size';
    }

    function buildCombinations() {
        if (locked) {
            return;
        }

        const result = syncCombinationsFromRows(rows);

        if (!result.ok) {
            return;
        }

        if ((result.combinations ?? []).length === 0) {
            setBuildError('Add at least one variation row with values.');
        }
    }

    function updateCombo(idx, field, val) {
        setCombinations((prev) => {
            const next = prev.map((c, i) => (i === idx ? { ...c, [field]: val } : c));
            onChange(next);
            return next;
        });
    }

    function removeCombo(idx) {
        setCombinations((prev) => {
            const next = prev.filter((_, i) => i !== idx);
            onChange(next);
            return next;
        });
    }

    return (
        <div>
            {locked && (
                <p className="mb-3 text-xs text-amber-600">
                    This product has sales or purchase history — variants cannot be changed.
                </p>
            )}

            <label className={`flex items-center gap-3 ${locked ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'}`}>
                <div className="relative">
                    <input type="checkbox" className="sr-only" checked={enabled} disabled={locked} onChange={(e) => toggleEnabled(e.target.checked)} />
                    <div className={`h-6 w-11 rounded-full transition-colors ${enabled ? 'bg-green-600' : 'bg-muted'}`} />
                    <div className={`absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${enabled ? 'translate-x-5' : ''}`} />
                </div>
                <div>
                    <p className="text-sm font-semibold">Item has variants</p>
                    <p className="text-xs text-muted-foreground">Such as size, color, or material</p>
                </div>
            </label>

            {enabled && (
                <div className="mt-4 space-y-4">
                    {/* Column headers — desktop only */}
                    <div className="hidden items-center gap-2 px-3 sm:flex">
                        <Label className="w-1/3 text-xs">Variation Name</Label>
                        <Label className="min-w-0 flex-1 text-xs">Value (Tags)</Label>
                        <div className="w-16 shrink-0" />
                    </div>

                    <div className="space-y-2">
                        {rows.map((row, idx) => (
                            <div
                                key={row.id}
                                className="flex flex-col gap-2 border bg-muted/30 p-3 sm:flex-row sm:items-center sm:gap-2"
                            >
                                <div className="w-full min-w-0 sm:w-1/3">
                                    <Label className="mb-1 block text-xs sm:hidden">Variation Name</Label>
                                    <SmartSelect
                                        options={varOptions}
                                        value={row.name}
                                        onValueChange={(v) => setRowName(row.id, v ?? '')}
                                        onOptionsChange={setVarOptions}
                                        placeholder="e.g. Color, Size"
                                        creatable={!locked}
                                        createMode="inline"
                                        createRowLabel={(q) => `Add "${q}"`}
                                        triggerClassName="h-8 w-full max-w-full text-xs"
                                        disabled={locked}
                                    />
                                </div>

                                <div className="w-full min-w-0 sm:flex-1">
                                    <Label className="mb-1 block text-xs sm:hidden">Value (Tags)</Label>
                                    {usesPresetValues(row.name) ? (
                                        <SmartMultiSelect
                                            options={getValueOptions(row.name)}
                                            value={row.values}
                                            onValueChange={(v) => setRowValues(row.id, v)}
                                            placeholder={`Select ${row.name.toLowerCase()} values…`}
                                            triggerClassName="min-h-8 w-full max-w-full overflow-hidden text-xs"
                                            disabled={locked}
                                        />
                                    ) : (
                                        <TagInput
                                            tags={row.values}
                                            onChange={(v) => setRowValues(row.id, v)}
                                            disabled={locked}
                                        />
                                    )}
                                </div>

                                <div className="flex shrink-0 items-center justify-end gap-1 sm:justify-start">
                                    {!locked && idx === rows.length - 1 && (
                                        <button
                                            type="button"
                                            onClick={addRow}
                                            className="flex size-7 items-center justify-center border border-green-600 text-green-600 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-green-600 hover:text-white hover:shadow-md hover:shadow-green-600/30"
                                            title="Add row"
                                        >
                                            <Plus className="size-3.5" />
                                        </button>
                                    )}
                                    {!locked && rows.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeRow(row.id)}
                                            className="flex size-7 items-center justify-center border border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                                            title="Remove row"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {!locked && (
                        <Button
                            type="button"
                            size="sm"
                            className="bg-green-600 text-white hover:bg-green-700"
                            onClick={buildCombinations}
                        >
                            Build Combinations
                        </Button>
                    )}

                    {buildError && (
                        <p className="text-xs text-destructive">{buildError}</p>
                    )}

                    {errors.combinations && (
                        <p className="text-xs text-destructive">{errors.combinations}</p>
                    )}

                    {combinations.length > 0 && (
                        <div className="overflow-x-auto rounded border">
                            <table className="w-full text-xs">
                                <thead className="border-b bg-muted/40 text-left">
                                    <tr>
                                        <th className="p-2">#</th>
                                        <th className="p-2">Variant</th>
                                        <th className="p-2">Sale Price</th>
                                        <th className="p-2">Purchase Price</th>
                                        <th className="p-2">SKU / Barcode</th>
                                        <th className="p-2">Stock</th>
                                        <th className="p-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {combinations.map((combo, idx) => (
                                        <tr key={idx} className="border-b last:border-0">
                                            <td className="p-2 text-muted-foreground">{idx + 1}</td>
                                            <td className="p-2 font-medium">
                                                <div>{combo.variant}</div>
                                                {formatVariantSummary(combo.variation_data) && (
                                                    <p className="mt-0.5 text-[10px] font-normal text-muted-foreground">
                                                        {formatVariantSummary(combo.variation_data)}
                                                    </p>
                                                )}
                                                {errors[`combinations.${idx}.variant`] && <p className="mt-0.5 text-[10px] text-destructive">{errors[`combinations.${idx}.variant`]}</p>}
                                            </td>
                                            <td className="p-2">
                                                <Input type="number" min="0" step="0.01" className="h-7 w-24 text-xs" value={combo.sale_price} onChange={(e) => updateCombo(idx, 'sale_price', e.target.value)} disabled={locked} />
                                                {errors[`combinations.${idx}.sale_price`] && <p className="mt-0.5 text-[10px] text-destructive">{errors[`combinations.${idx}.sale_price`]}</p>}
                                            </td>
                                            <td className="p-2">
                                                <Input type="number" min="0" step="0.01" className="h-7 w-24 text-xs" value={combo.purchase_price} onChange={(e) => updateCombo(idx, 'purchase_price', e.target.value)} disabled={locked} />
                                                {errors[`combinations.${idx}.purchase_price`] && <p className="mt-0.5 text-[10px] text-destructive">{errors[`combinations.${idx}.purchase_price`]}</p>}
                                            </td>
                                            <td className="p-2">
                                                <Input
                                                    className="h-7 w-32 text-xs"
                                                    value={combo.sku}
                                                    onChange={(e) => updateCombo(idx, 'sku', e.target.value.slice(0, 8))}
                                                    disabled={locked}
                                                    maxLength={8}
                                                    placeholder="Auto"
                                                />
                                                <p className="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                                                    Max 8 chars — used as barcode. Leave empty to auto-generate.
                                                </p>
                                                {errors[`combinations.${idx}.sku`] && <p className="mt-0.5 text-[10px] text-destructive">{errors[`combinations.${idx}.sku`]}</p>}
                                            </td>
                                            <td className="p-2">
                                                <Input type="number" min="0" className="h-7 w-20 text-xs" value={combo.stock} onChange={(e) => updateCombo(idx, 'stock', e.target.value)} disabled={locked} />
                                                {errors[`combinations.${idx}.stock`] && <p className="mt-0.5 text-[10px] text-destructive">{errors[`combinations.${idx}.stock`]}</p>}
                                            </td>
                                            <td className="p-2">
                                                {!locked && (
                                                    <button type="button" onClick={() => removeCombo(idx)} className="text-destructive hover:text-destructive/80">
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
});

const ProductForm = forwardRef(function ProductForm({
    form,
    categories,
    brands,
    units,
    warranties,
    branches,
    colorOptions = [],
    sizeOptions = [],
    tagOptions = [],
    suppliers = [],
    paymentAccounts = [],
    ecommerceBranchId = null,
    defaultCatalogBranchId = null,
    showBranchField = false,
    sourceBranchId = null,
    selectedCatalog = {},
    selectedColors = [],
    selectedSizes = [],
    initialVariations = [],
    variantsLocked = false,
    initialStockValue = 0,
    hasExistingSettlement = false,
    isEditing = false,
    processing = false,
    cancelHref = '',
}, ref) {
    const { can } = useCan();

    const branchSelectOptions = useMemo(
        () => [
            { value: '__none', label: 'All branches' },
            ...Object.entries(branches || {}).map(([value, label]) => ({ value, label })),
        ],
        [branches],
    );

    const [localCategoryOptions, setLocalCategoryOptions] = useState(() => mapSelectOptionsWithSelected(categories, selectedCatalog.category));
    const [localBrandOptions, setLocalBrandOptions] = useState(() => mapSelectOptionsWithSelected(brands, selectedCatalog.brand));
    const [localUnitOptions, setLocalUnitOptions] = useState(() => mapSelectOptionsWithSelected(units, selectedCatalog.unit));
    const [localWarrantyOptions, setLocalWarrantyOptions] = useState(() => mapSelectOptionsWithSelected(warranties, selectedCatalog.warranty));
    const [localTagOptions, setLocalTagOptions] = useState(() => tagOptions);
    const [branchColorOptions, setBranchColorOptions] = useState(() => mergePresetOptions(colorOptions, selectedColors));
    const [branchSizeOptions, setBranchSizeOptions] = useState(() => mergePresetOptions(sizeOptions, selectedSizes));

    const toast = useAppToast();
    const variationBuilderRef = useRef(null);
    const [hasVariations, setHasVariations] = useState(isEditing && initialVariations.length > 0);

    const colorSelectOptions = useMemo(
        () => branchColorOptions.map((opt) => ({ value: String(opt.id), label: opt.label })),
        [branchColorOptions],
    );

    const sizeSelectOptions = useMemo(
        () => branchSizeOptions.map((opt) => ({ value: String(opt.id), label: opt.label })),
        [branchSizeOptions],
    );

    const combinations = form.data.combinations || [];
    const sharedComboPrices = useMemo(() => getSharedCombinationPrices(combinations), [combinations]);
    const allCombosHavePrices = hasVariations && allCombinationsHavePrices(combinations);
    const someCombosMissingPrices = hasVariations && someCombinationsMissingPrices(combinations);

    const allCombosHaveStock =
        hasVariations &&
        combinations.length > 0 &&
        combinations.every((c) => String(c.stock ?? '').trim() !== '');

    const priceFieldsDisabled = hasVariations && allCombosHavePrices && sharedComboPrices === null;
    const priceFieldsRequired = hasVariations && combinations.length > 0 && (someCombosMissingPrices || !allCombosHavePrices);
    const stockFieldsDisabled = allCombosHaveStock;
    const showInitialStockField = !hasVariations || hasVariations;

    const initialStockTotal = useMemo(
        () => calculateInitialStockTotal({
            hasVariations,
            combinations,
            mainInitialStock: form.data.initial_stock,
            mainPurchasePrice: form.data.purchase_price,
        }),
        [hasVariations, combinations, form.data.initial_stock, form.data.purchase_price],
    );

    const resolvedInitialStockTotal = variantsLocked && initialStockValue > 0
        ? initialStockValue
        : initialStockTotal;

    const showInitialStockSettlement = resolvedInitialStockTotal > 0
        || (isEditing && hasExistingSettlement);
    const initialStockPaidAmount = parseFloat(form.data.initial_stock_paid_amount || 0) || 0;
    const initialStockDueAmount = Math.max(0, resolvedInitialStockTotal - initialStockPaidAmount);

    const supplierSelectOptions = useMemo(() => mapSupplierOptions(suppliers), [suppliers]);

    const paymentAccountOptions = useMemo(
        () => paymentAccounts.map((account) => ({
            value: String(account.id),
            label: account.label ?? `${account.code} — ${account.name}`,
        })),
        [paymentAccounts],
    );

    useEffect(() => {
        if (!form.data.initial_stock_supplier_id || initialStockPaidAmount <= 0) {
            return;
        }

        if (form.data.initial_stock_payment_account_id || paymentAccountOptions.length === 0) {
            return;
        }

        form.setData(
            'initial_stock_payment_account_id',
            resolveDefaultPaymentAccountId(paymentAccountOptions),
        );
    }, [
        form.data.initial_stock_supplier_id,
        form.data.initial_stock_payment_account_id,
        initialStockPaidAmount,
        paymentAccountOptions,
    ]);

    function handleInitialStockPaidAmountChange(value) {
        const paidAmount = parseFloat(value || 0) || 0;

        form.setData((data) => ({
            ...data,
            initial_stock_paid_amount: value,
            initial_stock_payment_account_id: paidAmount <= 0
                ? ''
                : resolveDefaultPaymentAccountId(paymentAccountOptions, data.initial_stock_payment_account_id),
        }));
    }

    useEffect(() => {
        if (showInitialStockSettlement) {
            return;
        }

        if (
            form.data.initial_stock_supplier_id
            || form.data.initial_stock_paid_amount
            || form.data.initial_stock_payment_account_id
        ) {
            form.setData((data) => ({
                ...data,
                initial_stock_supplier_id: '',
                initial_stock_paid_amount: '',
                initial_stock_payment_account_id: '',
            }));
        }
    }, [showInitialStockSettlement]);

    const defaultCombinationPrices = useMemo(() => {
        const mainPurchase = String(form.data.purchase_price ?? '').trim();
        const mainSale = String(form.data.sale_price ?? '').trim();

        if (mainPurchase !== '' || mainSale !== '') {
            return {
                purchase_price: mainPurchase,
                sale_price: mainSale,
            };
        }

        return sharedComboPrices;
    }, [form.data.purchase_price, form.data.sale_price, sharedComboPrices]);

    useEffect(() => {
        if (!hasVariations || combinations.length === 0 || !sharedComboPrices) {
            return;
        }

        const nextPurchase = sharedComboPrices.purchase_price;
        const nextSale = sharedComboPrices.sale_price;

        if (form.data.purchase_price === nextPurchase && form.data.sale_price === nextSale) {
            return;
        }

        form.setData((data) => ({
            ...data,
            purchase_price: nextPurchase,
            sale_price: nextSale,
        }));
    }, [hasVariations, combinations, sharedComboPrices]);

    function handleMainPurchasePriceChange(value) {
        form.setData((data) => {
            const combos = data.combinations ?? [];
            const shared = getSharedCombinationPrices(combos);

            return {
                ...data,
                purchase_price: value,
                combinations: hasVariations && combos.length > 0
                    ? combos.map((combo) => (
                        shared !== null || String(combo.purchase_price ?? '').trim() === ''
                            ? { ...combo, purchase_price: value }
                            : combo
                    ))
                    : combos,
            };
        });
    }

    function handleMainSalePriceChange(value) {
        form.setData((data) => {
            const combos = data.combinations ?? [];
            const shared = getSharedCombinationPrices(combos);

            return {
                ...data,
                sale_price: value,
                combinations: hasVariations && combos.length > 0
                    ? combos.map((combo) => (
                        shared !== null || String(combo.sale_price ?? '').trim() === ''
                            ? { ...combo, sale_price: value }
                            : combo
                    ))
                    : combos,
            };
        });
    }

    const effectiveBranchId = form.data.branch_id != null && form.data.branch_id !== ''
        ? String(form.data.branch_id)
        : null;

    const quickCreateBranchId = effectiveBranchId
        ?? (defaultCatalogBranchId != null ? String(defaultCatalogBranchId) : null);

    const showVisibleOnStore = can('product.visible-on-store')
        && ecommerceBranchId != null
        && effectiveBranchId === String(ecommerceBranchId);

    useEffect(() => {
        if (!showVisibleOnStore && form.data.visible !== 'no') {
            form.setData('visible', 'no');
        }
    }, [showVisibleOnStore]);

    function handleVariationsToggle(val) {
        setHasVariations(val);
        form.setData('has_variants', val);

        if (val) {
            form.setData((data) => ({
                ...data,
                code: '',
                color_ids: [],
                size_ids: [],
            }));
        } else {
            form.setData('combinations', []);
        }
    }

    useImperativeHandle(ref, () => ({
        validateBeforeSubmit() {
            const variationResult = variationBuilderRef.current?.validateBeforeSubmit?.() ?? { ok: true };

            if (!variationResult.ok) {
                toast.error(variationResult.error ?? 'Complete the variation setup before saving.');
                return variationResult;
            }

            if (hasVariations && combinations.length > 0 && someCombosMissingPrices) {
                const mainPurchase = String(form.data.purchase_price ?? '').trim();
                const mainSale = String(form.data.sale_price ?? '').trim();

                if (mainPurchase === '' || mainSale === '') {
                    const message = 'Set purchase & sale price in the main fields, or enter a price for each combination.';
                    toast.error(message);
                    return { ok: false, error: message };
                }
            }

            if (showInitialStockSettlement) {
                const paidAmount = parseFloat(form.data.initial_stock_paid_amount || 0) || 0;

                if (paidAmount > resolvedInitialStockTotal + 0.001) {
                    const message = 'Paid amount cannot exceed the initial stock value.';
                    toast.error(message);
                    return { ok: false, error: message };
                }

                if (paidAmount > 0 && !form.data.initial_stock_payment_account_id) {
                    const message = 'Select a payment account when recording a paid amount.';
                    form.setError('initial_stock_payment_account_id', message);
                    toast.error(message);
                    return { ok: false, error: message };
                }
            }

            return { ok: true };
        },
    }));

    const visibleOn = form.data.visible === 'yes';

    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {/* ── LEFT ── */}
            <div className="space-y-4 lg:col-span-2">

                {/* Basic Info */}
                <Card title="Basic Information" icon={Info}>
                    <div className="grid grid-cols-3 gap-3">
                        {showBranchField && (
                            <Field label="Branch" error={form.errors.branch_id}>
                                <SmartSelect
                                    options={branchSelectOptions}
                                    value={form.data.branch_id ? String(form.data.branch_id) : '__none'}
                                    onValueChange={(v) => form.setData('branch_id', v === '__none' ? '' : v)}
                                    placeholder="Search branch…"
                                    triggerClassName="h-8 text-xs"
                                    optionsClassName="max-h-52"
                                />
                            </Field>
                        )}

                        <Field label="Category" required error={form.errors.category_id}>
                            <SmartSelect
                                options={localCategoryOptions}
                                value={form.data.category_id ? String(form.data.category_id) : null}
                                onValueChange={(v) => form.setData('category_id', v ?? '')}
                                onOptionsChange={setLocalCategoryOptions}
                                placeholder="Select or search…"
                                triggerClassName="h-8 text-xs"
                                creatable
                                createMode="instant"
                                createRowLabel={(q) => `Add "${q}"`}
                                onModalCreate={({ label }) => quickCreate('setting.category.store', label, quickCreateBranchId)}
                            />
                        </Field>

                        <Field label="Brand" required error={form.errors.brand_id}>
                            <SmartSelect
                                options={localBrandOptions}
                                value={form.data.brand_id ? String(form.data.brand_id) : null}
                                onValueChange={(v) => form.setData('brand_id', v ?? '')}
                                onOptionsChange={setLocalBrandOptions}
                                placeholder="Select or search…"
                                triggerClassName="h-8 text-xs"
                                creatable
                                createMode="instant"
                                createRowLabel={(q) => `Add "${q}"`}
                                onModalCreate={({ label }) => quickCreate('setting.brand.store', label, quickCreateBranchId)}
                            />
                        </Field>

                        <Field label="Unit" required error={form.errors.unit_id}>
                            <SmartSelect
                                options={localUnitOptions}
                                value={form.data.unit_id ? String(form.data.unit_id) : null}
                                onValueChange={(v) => form.setData('unit_id', v ?? '')}
                                onOptionsChange={setLocalUnitOptions}
                                placeholder="Select or search…"
                                triggerClassName="h-8 text-xs"
                                creatable
                                createMode="instant"
                                createRowLabel={(q) => `Add "${q}"`}
                                onModalCreate={({ label }) => quickCreate('setting.unit.store', label, quickCreateBranchId)}
                            />
                        </Field>

                        <Field label="Warranty" error={form.errors.warranty_id}>
                            <SmartSelect
                                options={localWarrantyOptions}
                                value={form.data.warranty_id ? String(form.data.warranty_id) : null}
                                onValueChange={(v) => form.setData('warranty_id', v ?? null)}
                                onOptionsChange={setLocalWarrantyOptions}
                                placeholder="No warranty"
                                triggerClassName="h-8 text-xs"
                                creatable
                                createMode="instant"
                                createRowLabel={(q) => `Add "${q}"`}
                                onModalCreate={({ label }) => quickCreate('setting.warranty.store', label, quickCreateBranchId)}
                            />
                        </Field>

                        <Field label="Product Name / Code" required error={form.errors.name}>
                            <Input className="h-8 text-xs" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Product name" />
                        </Field>

                        {!hasVariations && (
                            <Field label="Barcode" error={form.errors.code}>
                                <Input
                                    className="h-8 text-xs"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value.slice(0, 12))}
                                    onKeyDown={(e) => { if (e.key === 'Enter') e.preventDefault(); }}
                                    placeholder="Leave empty for auto code"
                                    maxLength={12}
                                />
                                <p className="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                                    Max 12 characters — keeps the barcode short and scannable. Leave empty to auto-generate a numeric barcode.
                                </p>
                            </Field>
                        )}
                    </div>
                </Card>

                {/* Extra Options */}
                <Card title="Options" icon={Settings}>
                    <div className="grid grid-cols-3 gap-3">
                        {!hasVariations && (
                            <>
                                <Field label="Color" error={form.errors.color_ids}>
                                    <SmartMultiSelect
                                        options={colorSelectOptions}
                                        value={form.data.color_ids || []}
                                        onValueChange={(values) => form.setData('color_ids', values)}
                                        placeholder="Select colors…"
                                        triggerClassName="min-h-8 text-xs"
                                    />
                                </Field>

                                <Field label="Size" error={form.errors.size_ids}>
                                    <SmartMultiSelect
                                        options={sizeSelectOptions}
                                        value={form.data.size_ids || []}
                                        onValueChange={(values) => form.setData('size_ids', values)}
                                        placeholder="Select sizes…"
                                        triggerClassName="min-h-8 text-xs"
                                    />
                                </Field>
                            </>
                        )}

                        {showInitialStockField && (
                            <Field label="Initial Stock" error={form.errors.initial_stock}>
                                <Input
                                    className="h-8 text-xs"
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={form.data.initial_stock}
                                    onChange={(e) => form.setData('initial_stock', e.target.value)}
                                    placeholder="0"
                                    disabled={stockFieldsDisabled}
                                />
                            </Field>
                        )}

                        {showInitialStockSettlement && (
                            <>
                                {variantsLocked && (
                                    <p className="col-span-3 text-xs text-amber-600">
                                        Stock and variants are locked, but you can still update supplier payment details.
                                    </p>
                                )}

                                <Field label="Supplier (optional)" error={form.errors.initial_stock_supplier_id}>
                                    <SmartSelect
                                        options={supplierSelectOptions}
                                        value={form.data.initial_stock_supplier_id ? String(form.data.initial_stock_supplier_id) : null}
                                        onValueChange={(value) => {
                                            form.setData((data) => {
                                                const hasSupplier = Boolean(value);

                                                return {
                                                    ...data,
                                                    initial_stock_supplier_id: value ?? '',
                                                    initial_stock_paid_amount: hasSupplier ? data.initial_stock_paid_amount : '',
                                                    initial_stock_payment_account_id: hasSupplier
                                                        ? resolveDefaultPaymentAccountId(
                                                            paymentAccountOptions,
                                                            parseFloat(data.initial_stock_paid_amount || 0) > 0
                                                                ? data.initial_stock_payment_account_id
                                                                : '',
                                                        )
                                                        : '',
                                                };
                                            });
                                        }}
                                        placeholder="No supplier — opening balance"
                                        triggerClassName="h-8 text-xs"
                                        optionsClassName="max-h-52"
                                    />
                                    <p className="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                                        Leave empty to record initial stock against opening balance instead of a supplier payable.
                                    </p>
                                </Field>

                                <Field label="Stock Value">
                                    <div className="flex h-8 items-center rounded-md border border-input bg-muted/20 px-3 text-xs font-medium tabular-nums">
                                        ৳{resolvedInitialStockTotal.toFixed(2)}
                                    </div>
                                </Field>

                                <Field label="Paid Amount" error={form.errors.initial_stock_paid_amount}>
                                    <Input
                                        className="h-8 text-xs"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.initial_stock_paid_amount}
                                        onChange={(e) => handleInitialStockPaidAmountChange(e.target.value)}
                                        placeholder="0.00"
                                        disabled={!form.data.initial_stock_supplier_id}
                                    />
                                    {!form.data.initial_stock_supplier_id && (
                                        <p className="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                                            Select a supplier to record payment and due.
                                        </p>
                                    )}
                                </Field>

                                {initialStockPaidAmount > 0 && (
                                    <Field
                                        label={<>Payment Account <RequiredMark /></>}
                                        error={form.errors.initial_stock_payment_account_id}
                                    >
                                        <SmartSelect
                                            options={paymentAccountOptions}
                                            value={form.data.initial_stock_payment_account_id ? String(form.data.initial_stock_payment_account_id) : null}
                                            onValueChange={(value) => form.setData('initial_stock_payment_account_id', value ?? '')}
                                            placeholder={paymentAccountOptions.length > 0 ? 'Select cash / bank account' : 'No payment accounts configured'}
                                            triggerClassName="h-8 text-xs"
                                            optionsClassName="max-h-52"
                                            disabled={paymentAccountOptions.length === 0}
                                        />
                                        <p className="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                                            Required when paid amount is entered — records which cash/bank account was used.
                                        </p>
                                    </Field>
                                )}

                                {form.data.initial_stock_supplier_id && (
                                    <Field label="Due Amount">
                                        <div className="flex h-8 items-center rounded-md border border-destructive/30 bg-destructive/5 px-3 text-xs font-semibold text-destructive tabular-nums">
                                            ৳{initialStockDueAmount.toFixed(2)}
                                        </div>
                                    </Field>
                                )}
                            </>
                        )}

                        <Field label="YouTube Link" error={form.errors.youtube_link}>
                            <Input className="h-8 text-xs" value={form.data.youtube_link} onChange={(e) => form.setData('youtube_link', e.target.value)} placeholder="https://youtube.com/..." />
                        </Field>

                        <Field label="Tags" error={form.errors.tags}>
                            <SmartMultiSelect
                                options={localTagOptions}
                                value={form.data.tags || []}
                                onValueChange={(tags) => form.setData('tags', tags)}
                                placeholder="Search tags…"
                                triggerClassName="min-h-8"
                                creatable
                                onCreateOption={async (name) => {
                                    try {
                                        const opt = await quickCreate('setting.tag.store', name, quickCreateBranchId);
                                        setLocalTagOptions((prev) => [...prev, opt]);
                                        form.setData('tags', [...(form.data.tags || []), opt.value]);
                                        toast.success(`"${opt.label}" created.`);
                                    } catch {
                                        toast.error('Failed to create tag. Try again.');
                                    }
                                }}
                            />
                        </Field>

                        {showVisibleOnStore && (
                            <Field label="Visible on Store">
                                <button
                                    type="button"
                                    onClick={() => form.setData('visible', visibleOn ? 'no' : 'yes')}
                                    className="relative mt-1"
                                >
                                    <div className={`h-5 w-9 rounded-full transition-colors ${visibleOn ? 'bg-green-600' : 'bg-muted'}`} />
                                    <div className={`absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform ${visibleOn ? 'translate-x-4' : ''}`} />
                                </button>
                            </Field>
                        )}
                    </div>
                </Card>

                {/* Variations */}
                <Card title="Variations" icon={GitBranch}>
                    <VariationBuilder
                        ref={variationBuilderRef}
                        colorOptions={branchColorOptions}
                        sizeOptions={branchSizeOptions}
                        initialVariations={initialVariations}
                        locked={variantsLocked}
                        defaultCombinationPrices={defaultCombinationPrices}
                        onChange={(combos) => form.setData('combinations', combos)}
                        onEnabledChange={handleVariationsToggle}
                        errors={form.errors}
                    />
                </Card>

                {/* Price & Stock */}
                <Card title="Price & Stock" icon={DollarSign}>
                    <div className="grid grid-cols-3 gap-3">
                        <Field label="Purchase Price" required={priceFieldsRequired || (!hasVariations && !priceFieldsDisabled)} error={form.errors.purchase_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.purchase_price} onChange={(e) => handleMainPurchasePriceChange(e.target.value)} placeholder="0.00" disabled={priceFieldsDisabled} />
                        </Field>

                        <Field label="Sale Price" required={priceFieldsRequired || (!hasVariations && !priceFieldsDisabled)} error={form.errors.sale_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.sale_price} onChange={(e) => handleMainSalePriceChange(e.target.value)} placeholder="0.00" disabled={priceFieldsDisabled} />
                        </Field>

                        <Field label="Discount Price" error={form.errors.discount_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.discount_price} onChange={(e) => form.setData('discount_price', e.target.value)} placeholder="0.00" />
                        </Field>
                    </div>
                    {hasVariations && someCombosMissingPrices && combinations.length > 0 && (
                        <p className="mt-2 text-xs text-amber-600">
                            Some combinations are missing prices — set purchase &amp; sale price above, or enter a price on each combination row.
                        </p>
                    )}
                    {hasVariations && sharedComboPrices && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            All combinations share the same price — shown above. Change it here to update every combination.
                        </p>
                    )}
                    {priceFieldsDisabled && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            Each combination has its own price — use the combination rows below.
                        </p>
                    )}
                    {hasVariations && !allCombosHaveStock && combinations.length > 0 && (
                        <p className="mt-2 text-xs text-amber-600">
                            Some combinations are missing stock — this initial stock will be applied to those.
                        </p>
                    )}
                    {hasVariations && allCombosHaveStock && combinations.length > 0 && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            All combinations have their own stock — initial stock is not required.
                        </p>
                    )}
                </Card>

            </div>

            {/* ── RIGHT ── */}
            <div className="space-y-4">

                <Card title="Product Image" icon={ImagePlus}>
                    <SingleImageUpload
                        existingPath={isEditing ? form.data._existing_image : null}
                        onChange={(file) => form.setData('image', file)}
                    />
                    {form.errors.image && <p className="mt-1 text-xs text-destructive">{form.errors.image}</p>}
                </Card>

                <Card title="Gallery Photos" icon={Images}>
                    <MultiImageUpload onChange={(files) => form.setData('photos', files)} />
                    {form.errors.photos && <p className="mt-1 text-xs text-destructive">{form.errors.photos}</p>}
                </Card>

                <Card title="Status" icon={Settings}>
                    <div className="space-y-3">
                        <Field label="Status" error={form.errors.status}>
                            <Select value={String(form.data.status ?? '1')} onValueChange={(v) => form.setData('status', v)}>
                                <SelectTrigger className="h-8 w-full text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>

                        <div className="flex gap-2 pt-1">
                            {cancelHref && (
                                <Button type="button" variant="outline" size="sm" className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30" asChild>
                                    <Link href={cancelHref}>Cancel</Link>
                                </Button>
                            )}
                            <Button type="submit" disabled={processing} size="sm" className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:bg-emerald-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/50">
                                {processing ? 'Saving...' : 'Save'}
                            </Button>
                        </div>
                    </div>
                </Card>
            </div>

            {/* ── CONTENT (full width) ── */}
            <div className="lg:col-span-3">
                <Card title="Content" icon={AlignLeft}>
                    <div className="space-y-3">
                        <Field label="Description" error={form.errors.description}>
                            <CKEditorField id="ck_description" value={form.data.description} onChange={(v) => form.setData('description', v)} />
                        </Field>
                        <Field label="Delivery Info" error={form.errors.delivery_info}>
                            <CKEditorField id="ck_delivery_info" value={form.data.delivery_info} onChange={(v) => form.setData('delivery_info', v)} />
                        </Field>
                    </div>
                </Card>
            </div>
        </div>
    );
});

export default ProductForm;
