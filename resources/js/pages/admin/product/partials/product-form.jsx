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
import { Link, usePage } from '@inertiajs/react';
import { AlignLeft, DollarSign, GitBranch, ImagePlus, Images, Info, Plus, Settings, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { buildVariationBuilderState, buildVariationDataFromRows } from '@/lib/variation-utils';

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
            className={`flex min-h-9.5 flex-wrap items-center gap-1 border border-input bg-background px-3 py-2 shadow-xs ${disabled ? 'cursor-not-allowed opacity-70' : 'cursor-text focus-within:ring-[3px] focus-within:ring-ring/50'}`}
            onClick={(e) => !disabled && e.currentTarget.querySelector('input')?.focus()}
        >
            {tags.map((tag) => (
                <span key={tag} className="flex items-center gap-1 bg-blue-600 px-2 py-0.5 text-[11px] leading-4 text-white shadow shadow-blue-500/50">
                    {tag}
                    {!disabled && (
                        <button type="button" onClick={() => onChange(tags.filter((t) => t !== tag))} className="opacity-80 hover:opacity-100">
                            <X className="size-2.5" />
                        </button>
                    )}
                </span>
            ))}
            {!disabled && (
                <input
                    className="min-w-20 flex-1 bg-transparent text-xs outline-none"
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

const VARIANT_ROW_ORDER = ['Color', 'Size'];

function sortParsedVariantRows(rows) {
    return [...rows].sort((a, b) => {
        const aIndex = VARIANT_ROW_ORDER.indexOf(a.name);
        const bIndex = VARIANT_ROW_ORDER.indexOf(b.name);

        if (aIndex === -1 && bIndex === -1) {
            return 0;
        }

        if (aIndex === -1) {
            return 1;
        }

        if (bIndex === -1) {
            return -1;
        }

        return aIndex - bIndex;
    });
}

function formatVariantSummary(variationData = {}) {
    return Object.entries(variationData)
        .filter(([key]) => key !== 'label')
        .map(([key, value]) => `${key}: ${value}`)
        .join(' · ');
}

function VariationBuilder({
    productCode,
    colorOptions = [],
    sizeOptions = [],
    initialVariations = [],
    locked = false,
    onChange,
    onEnabledChange,
    errors = {},
}) {
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

    useEffect(() => {
        if (initialVariations.length > 0) {
            onEnabledChange?.(true);
            onChange(initialState.combinations);
        }
    }, []);

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
        if (name === 'Color') {
            return colorOptions;
        }

        if (name === 'Size') {
            return sizeOptions;
        }

        return [];
    }

    function usesPresetValues(name) {
        return name === 'Color' || name === 'Size';
    }

    function buildCombinations() {
        if (locked) {
            return;
        }

        setBuildError('');

        const namedRows = rows.filter((r) => r.name.trim());
        const missingValues = namedRows.filter((r) => r.values.length === 0);

        if (missingValues.length > 0) {
            setBuildError(`Add values for: ${missingValues.map((r) => r.name).join(', ')}`);
            return;
        }

        const parsed = sortParsedVariantRows(
            namedRows.map((r) => ({ name: r.name.trim(), values: r.values })),
        );

        if (!parsed.length) {
            return;
        }

        const cartesian = parsed.map((r) => r.values).reduce((acc, cur) => {
            const res = [];
            acc.forEach((a) => cur.forEach((b) => res.push([...a, b])));

            return res;
        }, [[]]);

        const newCombos = cartesian.map((combo) => {
            const variation_data = buildVariationDataFromRows(parsed, combo);
            const variantText = variation_data.label ?? combo.join('-');
            const skuPart = variantText.replace(/[^A-Za-z0-9]+/g, '-').toUpperCase();
            const sku = [productCode, skuPart].filter(Boolean).join('-');

            return {
                variant: variantText,
                variation_data,
                sale_price: '',
                purchase_price: '',
                sku,
                stock: '',
            };
        });

        setCombinations((prev) => {
            const prevMap = new Map(prev.map((combo) => [combo.variant, combo]));
            const next = newCombos.map((combo) => {
                const existing = prevMap.get(combo.variant);

                if (!existing) {
                    return combo;
                }

                return {
                    ...combo,
                    sale_price: existing.sale_price ?? '',
                    purchase_price: existing.purchase_price ?? '',
                    sku: existing.sku || combo.sku,
                    stock: existing.stock ?? combo.stock,
                };
            });

            onChange(next);

            return next;
        });
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
                    {/* Column headers */}
                    <div className="flex items-center gap-2 px-3">
                        <Label className="w-1/3 text-xs">Variation Name</Label>
                        <Label className="flex-1 text-xs">Value (Tags)</Label>
                        <div className="w-16" />
                    </div>

                    <div className="space-y-2">
                        {rows.map((row, idx) => (
                            <div key={row.id} className="flex items-center gap-2 border bg-muted/30 p-3">
                                <div className="w-1/3 min-w-0">
                                    <SmartSelect
                                        options={varOptions}
                                        value={row.name}
                                        onValueChange={(v) => setRowName(row.id, v ?? '')}
                                        onOptionsChange={setVarOptions}
                                        placeholder="e.g. Color, Size"
                                        creatable={!locked}
                                        createMode="inline"
                                        createRowLabel={(q) => `Add "${q}"`}
                                        triggerClassName="h-8 text-xs"
                                        disabled={locked}
                                    />
                                </div>

                                <div className="flex-1 min-w-0">
                                    {usesPresetValues(row.name) ? (
                                        <SmartMultiSelect
                                            options={getValueOptions(row.name)}
                                            value={row.values}
                                            onValueChange={(v) => setRowValues(row.id, v)}
                                            placeholder={`Select ${row.name.toLowerCase()} values…`}
                                            triggerClassName="min-h-8 text-xs"
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

                                <div className="flex shrink-0 items-center gap-1">
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

                    {combinations.length > 0 && (
                        <div className="overflow-x-auto rounded border">
                            <table className="w-full text-xs">
                                <thead className="border-b bg-muted/40 text-left">
                                    <tr>
                                        <th className="p-2">#</th>
                                        <th className="p-2">Variant</th>
                                        <th className="p-2">Sale Price</th>
                                        <th className="p-2">Purchase Price</th>
                                        <th className="p-2">SKU</th>
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
                                                <Input className="h-7 w-32 text-xs" value={combo.sku} onChange={(e) => updateCombo(idx, 'sku', e.target.value)} disabled={locked} />
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
}

export default function ProductForm({
    form,
    categories,
    brands,
    units,
    warranties,
    branches,
    colorOptions = [],
    sizeOptions = [],
    tagOptions = [],
    ecommerceBranchId = null,
    initialVariations = [],
    variantsLocked = false,
    isEditing = false,
    processing = false,
    cancelHref = '',
}) {
    const { auth } = usePage().props;
    const { can } = useCan();
    const isAdmin = !auth.user?.branch_id;

    const branchSelectOptions = useMemo(
        () => [
            { value: '__none', label: 'All branches' },
            ...Object.entries(branches || {}).map(([value, label]) => ({ value, label })),
        ],
        [branches],
    );

    const [localCategoryOptions, setLocalCategoryOptions] = useState(() => Object.entries(categories || {}).map(([value, label]) => ({ value, label })));
    const [localBrandOptions, setLocalBrandOptions] = useState(() => Object.entries(brands || {}).map(([value, label]) => ({ value, label })));
    const [localUnitOptions, setLocalUnitOptions] = useState(() => Object.entries(units || {}).map(([value, label]) => ({ value, label })));
    const [localWarrantyOptions, setLocalWarrantyOptions] = useState(() => Object.entries(warranties || {}).map(([value, label]) => ({ value, label })));
    const [localTagOptions, setLocalTagOptions] = useState(() => tagOptions);

    const toast = useAppToast();
    const [hasVariations, setHasVariations] = useState(isEditing && initialVariations.length > 0);

    const colorSelectOptions = useMemo(
        () => colorOptions.map((opt) => ({ value: String(opt.id), label: opt.label })),
        [colorOptions],
    );

    const sizeSelectOptions = useMemo(
        () => sizeOptions.map((opt) => ({ value: String(opt.id), label: opt.label })),
        [sizeOptions],
    );

    const combinations = form.data.combinations || [];
    const allCombosHavePrices =
        hasVariations &&
        combinations.length > 0 &&
        combinations.every((c) => String(c.sale_price ?? '').trim() !== '' && String(c.purchase_price ?? '').trim() !== '');

    const allCombosHaveStock =
        hasVariations &&
        combinations.length > 0 &&
        combinations.every((c) => String(c.stock ?? '').trim() !== '');

    const priceFieldsDisabled = allCombosHavePrices;
    const stockFieldsDisabled = allCombosHaveStock;
    const showInitialStockField = !hasVariations || hasVariations;

    const effectiveBranchId = isAdmin
        ? (form.data.branch_id != null && form.data.branch_id !== '' ? String(form.data.branch_id) : null)
        : (auth.user?.branch_id != null ? String(auth.user.branch_id) : null);

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

        if (val) {
            form.setData((data) => ({
                ...data,
                color_ids: [],
                size_ids: [],
            }));
        }
    }

    const visibleOn = form.data.visible === 'yes';

    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {/* ── LEFT ── */}
            <div className="space-y-4 lg:col-span-2">

                {/* Basic Info */}
                <Card title="Basic Information" icon={Info}>
                    <div className="grid grid-cols-3 gap-3">
                        {isAdmin && (
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
                                onModalCreate={({ label }) => quickCreate('setting.category.store', label, effectiveBranchId)}
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
                                onModalCreate={({ label }) => quickCreate('setting.brand.store', label, effectiveBranchId)}
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
                                onModalCreate={({ label }) => quickCreate('setting.unit.store', label, effectiveBranchId)}
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
                                onModalCreate={({ label }) => quickCreate('setting.warranty.store', label, effectiveBranchId)}
                            />
                        </Field>

                        <Field label="Product Name / Code" required error={form.errors.name}>
                            <Input className="h-8 text-xs" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Product name" />
                        </Field>

                        <Field label="Barcode" error={form.errors.code}>
                            <Input
                                className="h-8 text-xs"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') e.preventDefault(); }}
                                placeholder="Leave empty for auto code"
                            />
                        </Field>
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
                                        const opt = await quickCreate('setting.tag.store', name, effectiveBranchId);
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
                        productCode={form.data.code}
                        colorOptions={colorOptions}
                        sizeOptions={sizeOptions}
                        initialVariations={initialVariations}
                        locked={variantsLocked}
                        onChange={(combos) => form.setData('combinations', combos)}
                        onEnabledChange={handleVariationsToggle}
                        errors={form.errors}
                    />
                </Card>

                {/* Price & Stock */}
                <Card title="Price & Stock" icon={DollarSign}>
                    <div className="grid grid-cols-3 gap-3">
                        <Field label="Purchase Price" required={!priceFieldsDisabled} error={form.errors.purchase_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.purchase_price} onChange={(e) => form.setData('purchase_price', e.target.value)} placeholder="0.00" disabled={priceFieldsDisabled} />
                        </Field>

                        <Field label="Sale Price" required={!priceFieldsDisabled} error={form.errors.sale_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.sale_price} onChange={(e) => form.setData('sale_price', e.target.value)} placeholder="0.00" disabled={priceFieldsDisabled} />
                        </Field>

                        <Field label="Discount Price" error={form.errors.discount_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.discount_price} onChange={(e) => form.setData('discount_price', e.target.value)} placeholder="0.00" />
                        </Field>

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
                    </div>
                    {hasVariations && !allCombosHavePrices && combinations.length > 0 && (
                        <p className="mt-2 text-xs text-amber-600">
                            Some combinations are missing prices — this purchase &amp; sale price will be applied to those.
                        </p>
                    )}
                    {allCombosHavePrices && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            All combinations have their own prices — these fields are not required.
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
}
