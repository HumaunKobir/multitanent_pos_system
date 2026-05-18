import { SmartMultiSelect } from '@/components/smart-multi-select';
import { SmartSelect } from '@/components/smart-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Link, usePage } from '@inertiajs/react';
import { ImagePlus, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

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

function Card({ title, children }) {
    return (
        <div className="border bg-card">
            <div className="border-b bg-muted/40 px-4 py-2.5">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{title}</h2>
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

function Field({ label, required, error, children }) {
    return (
        <div>
            <Label className="mb-1 block text-xs font-medium">
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
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

function ImageUploadBox({ label, existingPath, onChange, multiple = false }) {
    const inputRef = useRef(null);
    const [previews, setPreviews] = useState([]);

    function handleFiles(files) {
        if (!files.length) return;
        if (multiple) {
            setPreviews(Array.from(files).map((f) => URL.createObjectURL(f)));
            onChange(Array.from(files));
        } else {
            setPreviews([URL.createObjectURL(files[0])]);
            onChange(files[0]);
        }
    }

    const preview = previews[0];

    return (
        <div>
            {label && <Label className="mb-1 block text-xs font-medium">{label}</Label>}

            {existingPath && !preview && (
                <div className="mb-2 border">
                    <img src={`/storage/${existingPath}`} alt="Current" className="h-40 w-full object-cover" />
                    <p className="border-t bg-muted/40 px-2 py-1 text-xs text-muted-foreground">Upload to replace</p>
                </div>
            )}

            {preview && !multiple && (
                <div className="relative mb-2 border">
                    <img src={preview} alt="Preview" className="h-40 w-full object-cover" />
                    <button
                        type="button"
                        onClick={() => { setPreviews([]); onChange(null); if (inputRef.current) inputRef.current.value = ''; }}
                        className="absolute top-1.5 right-1.5 bg-black/60 p-0.5 text-white hover:bg-black/80"
                    >
                        <X className="size-3" />
                    </button>
                </div>
            )}

            {multiple && previews.length > 0 && (
                <div className="mb-2 flex flex-wrap gap-1.5">
                    {previews.map((src, i) => (
                        <img key={i} src={src} alt="" className="h-14 w-14 border object-cover" />
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
                <span>Click or drop {multiple ? 'photos' : 'image'}</span>
                <span className="text-[10px] opacity-60">JPG, PNG, WebP — max 3MB</span>
            </button>
            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple={multiple}
                className="hidden"
                onChange={(e) => handleFiles(e.target.files)}
            />
        </div>
    );
}

function TagInput({ tags, onChange, placeholder = 'Type value, press Space or Enter…' }) {
    const [input, setInput] = useState('');

    function commit() {
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
            className="flex min-h-9.5 flex-wrap items-center gap-1 border border-input bg-background px-3 py-2 shadow-xs cursor-text focus-within:ring-[3px] focus-within:ring-ring/50"
            onClick={(e) => e.currentTarget.querySelector('input')?.focus()}
        >
            {tags.map((tag) => (
                <span key={tag} className="flex items-center gap-1 bg-blue-600 px-2 py-0.5 text-[11px] leading-4 text-white shadow shadow-blue-500/50">
                    {tag}
                    <button type="button" onClick={() => onChange(tags.filter((t) => t !== tag))} className="opacity-80 hover:opacity-100">
                        <X className="size-2.5" />
                    </button>
                </span>
            ))}
            <input
                className="min-w-20 flex-1 bg-transparent text-xs outline-none"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                onBlur={commit}
                placeholder={tags.length === 0 ? placeholder : ''}
            />
        </div>
    );
}

function VariationBuilder({ productCode, variationNames = [], onChange, onEnabledChange }) {
    const [enabled, setEnabled] = useState(false);
    const [varOptions, setVarOptions] = useState(() => variationNames.map((n) => ({ value: n, label: n })));
    const [rows, setRows] = useState([{ id: 1, name: '', values: [] }]);
    const [combinations, setCombinations] = useState([]);

    function toggleEnabled(val) {
        setEnabled(val);
        onEnabledChange?.(val);
        if (!val) {
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
        setRows((prev) => prev.map((r) => (r.id === id ? { ...r, name } : r)));
    }

    function setRowValues(id, values) {
        setRows((prev) => prev.map((r) => (r.id === id ? { ...r, values } : r)));
    }

    function buildCombinations() {
        const parsed = rows
            .map((r) => ({ name: r.name.trim(), values: r.values }))
            .filter((r) => r.name && r.values.length > 0);

        if (!parsed.length) return;

        const product = parsed.map((r) => r.values).reduce((acc, cur) => {
            const res = [];
            acc.forEach((a) => cur.forEach((b) => res.push([...a, b])));
            return res;
        }, [[]]);

        const combos = product.map((combo) => {
            const variantText = combo.join('-');
            const skuPart = variantText.replace(/[^A-Za-z0-9]+/g, '-').toUpperCase();
            const sku = [productCode, skuPart].filter(Boolean).join('-');
            return { variant: variantText, sale_price: '', purchase_price: '', sku, stock: '' };
        });

        setCombinations(combos);
        onChange(combos);
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
            <label className="flex cursor-pointer items-center gap-3">
                <div className="relative">
                    <input type="checkbox" className="sr-only" checked={enabled} onChange={(e) => toggleEnabled(e.target.checked)} />
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
                        <div className="w-8" />
                    </div>

                    <div className="space-y-2">
                        {rows.map((row, idx) => (
                            <div key={row.id} className="flex items-center gap-2 border bg-muted/30 p-3">
                                {/* Variation name — SmartSelect with creatable */}
                                <div className="w-1/3 min-w-0">
                                    <SmartSelect
                                        options={varOptions}
                                        value={row.name}
                                        onValueChange={(v) => setRowName(row.id, v ?? '')}
                                        onOptionsChange={setVarOptions}
                                        placeholder="e.g. Color, Size"
                                        creatable
                                        createMode="inline"
                                        createRowLabel={(q) => `Add "${q}"`}
                                    />
                                </div>

                                {/* Values — tag pill input */}
                                <div className="flex-1 min-w-0">
                                    <TagInput
                                        tags={row.values}
                                        onChange={(v) => setRowValues(row.id, v)}
                                    />
                                </div>

                                {/* Actions: + on last row, X if multiple */}
                                <div className="flex shrink-0 items-center gap-1">
                                    {idx === rows.length - 1 && (
                                        <button
                                            type="button"
                                            onClick={addRow}
                                            className="flex size-7 items-center justify-center border border-green-600 text-green-600 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-green-600 hover:text-white hover:shadow-md hover:shadow-green-600/30"
                                            title="Add row"
                                        >
                                            <Plus className="size-3.5" />
                                        </button>
                                    )}
                                    {rows.length > 1 && (
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

                    <Button
                        type="button"
                        size="sm"
                        className="bg-green-600 text-white hover:bg-green-700"
                        onClick={buildCombinations}
                    >
                        Build Combinations
                    </Button>

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
                                            <td className="p-2 font-medium">{combo.variant}</td>
                                            <td className="p-2">
                                                <Input type="number" min="0" step="0.01" className="h-7 w-24 text-xs" value={combo.sale_price} onChange={(e) => updateCombo(idx, 'sale_price', e.target.value)} />
                                            </td>
                                            <td className="p-2">
                                                <Input type="number" min="0" step="0.01" className="h-7 w-24 text-xs" value={combo.purchase_price} onChange={(e) => updateCombo(idx, 'purchase_price', e.target.value)} />
                                            </td>
                                            <td className="p-2">
                                                <Input className="h-7 w-32 text-xs" value={combo.sku} onChange={(e) => updateCombo(idx, 'sku', e.target.value)} />
                                            </td>
                                            <td className="p-2">
                                                <Input type="number" min="0" className="h-7 w-20 text-xs" value={combo.stock} onChange={(e) => updateCombo(idx, 'stock', e.target.value)} />
                                            </td>
                                            <td className="p-2">
                                                <button type="button" onClick={() => removeCombo(idx)} className="text-destructive hover:text-destructive/80">
                                                    <Trash2 className="size-3.5" />
                                                </button>
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

export default function ProductForm({ form, categories, brands, units, warranties, colors, sizes, tailors, branches, variationNames = [], tagOptions = [], isEditing = false, processing = false, cancelHref = '' }) {
    const { auth } = usePage().props;
    const isAdmin = !auth.user?.branch_id;

    const categoryOptions = Object.entries(categories || {}).map(([value, label]) => ({ value, label }));
    const brandOptions    = Object.entries(brands    || {}).map(([value, label]) => ({ value, label }));
    const unitOptions     = Object.entries(units     || {}).map(([value, label]) => ({ value, label }));
    const warrantyOptions = Object.entries(warranties|| {}).map(([value, label]) => ({ value, label }));
    const sizeOptions     = Object.entries(sizes     || {}).map(([value, label]) => ({ value, label }));
    const tailorOptions   = Object.entries(tailors   || {}).map(([value, label]) => ({ value, label }));
    const branchOptions   = Object.entries(branches  || {}).map(([value, label]) => ({ value, label }));

    const [hasVariations, setHasVariations] = useState(false);

    function handleVariationsToggle(val) {
        setHasVariations(val);
        if (val) {
            form.setData('purchase_price', '');
            form.setData('sale_price', '');
            form.setData('code', '');
        }
    }

    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {/* ── LEFT ── */}
            <div className="space-y-4 lg:col-span-2">

                {/* Basic Info */}
                <Card title="Basic Information">
                    <div className="grid grid-cols-3 gap-3">
                        {isAdmin && (
                            <Field label="Branch" error={form.errors.branch_id}>
                                <Select value={form.data.branch_id ? String(form.data.branch_id) : '__none'} onValueChange={(v) => form.setData('branch_id', v === '__none' ? null : v)}>
                                    <SelectTrigger className="h-8 w-full text-xs"><SelectValue placeholder="All branches" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">All branches</SelectItem>
                                        {branchOptions.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </Field>
                        )}

                        <Field label="Category" required error={form.errors.category_id}>
                            <Select value={String(form.data.category_id ?? '')} onValueChange={(v) => form.setData('category_id', v)}>
                                <SelectTrigger className="h-8 w-full text-xs"><SelectValue placeholder="Select" /></SelectTrigger>
                                <SelectContent>{categoryOptions.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}</SelectContent>
                            </Select>
                        </Field>

                        <Field label="Brand" required error={form.errors.brand_id}>
                            <Select value={String(form.data.brand_id ?? '')} onValueChange={(v) => form.setData('brand_id', v)}>
                                <SelectTrigger className="h-8 w-full text-xs"><SelectValue placeholder="Select" /></SelectTrigger>
                                <SelectContent>{brandOptions.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}</SelectContent>
                            </Select>
                        </Field>

                        <Field label="Unit" required error={form.errors.unit_id}>
                            <Select value={String(form.data.unit_id ?? '')} onValueChange={(v) => form.setData('unit_id', v)}>
                                <SelectTrigger className="h-8 w-full text-xs"><SelectValue placeholder="Select" /></SelectTrigger>
                                <SelectContent>{unitOptions.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}</SelectContent>
                            </Select>
                        </Field>

                        <Field label="Warranty" error={form.errors.warranty_id}>
                            <Select value={form.data.warranty_id ? String(form.data.warranty_id) : '__none'} onValueChange={(v) => form.setData('warranty_id', v === '__none' ? null : v)}>
                                <SelectTrigger className="h-8 w-full text-xs"><SelectValue placeholder="No warranty" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none">No warranty</SelectItem>
                                    {warrantyOptions.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field label="Product Name" required error={form.errors.name}>
                            <Input className="h-8 text-xs" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Product name" />
                        </Field>

                        <Field label="Product Code" required={!hasVariations} error={form.errors.code}>
                            <Input className="h-8 text-xs" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder={hasVariations ? 'Set per variant' : 'SKU / barcode'} disabled={hasVariations} />
                        </Field>

                        <Field label="Purchase Price" required={!hasVariations} error={form.errors.purchase_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.purchase_price} onChange={(e) => form.setData('purchase_price', e.target.value)} placeholder={hasVariations ? 'Set per variant' : '0.00'} disabled={hasVariations} />
                        </Field>

                        <Field label="Sale Price" required={!hasVariations} error={form.errors.sale_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.sale_price} onChange={(e) => form.setData('sale_price', e.target.value)} placeholder={hasVariations ? 'Set per variant' : '0.00'} disabled={hasVariations} />
                        </Field>

                        <Field label="Discount Price" error={form.errors.discount_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.discount_price} onChange={(e) => form.setData('discount_price', e.target.value)} placeholder="0.00" />
                        </Field>

                        <div className="col-span-2">
                            <Field label="Tags" error={form.errors.tags}>
                                <SmartMultiSelect
                                    options={tagOptions}
                                    value={form.data.tags || []}
                                    onValueChange={(tags) => form.setData('tags', tags)}
                                    placeholder="Search tags…"
                                    triggerClassName="min-h-8"
                                />
                            </Field>
                        </div>
                    </div>
                </Card>

                {/* Type & Tailor */}
                <Card title="Type & Tailor">
                    <div className="grid grid-cols-3 gap-3">
                        <Field label="Product Type" required error={form.errors.type}>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger className="h-8 w-full text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="notstitch">Not Stitch</SelectItem>
                                    <SelectItem value="stitch">Stitch</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field label="Tailor Option" required error={form.errors.tailor_option}>
                            <Select value={form.data.tailor_option} onValueChange={(v) => form.setData('tailor_option', v)}>
                                <SelectTrigger className="h-8 w-full text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="yes">Yes</SelectItem>
                                    <SelectItem value="no">No</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field label="Tailor Price" required error={form.errors.tailor_price}>
                            <Input className="h-8 text-xs" type="number" min="0" step="0.01" value={form.data.tailor_price} onChange={(e) => form.setData('tailor_price', e.target.value)} placeholder="0.00" />
                        </Field>

                        <Field label="YouTube Link" error={form.errors.youtube_link}>
                            <Input className="h-8 text-xs" value={form.data.youtube_link} onChange={(e) => form.setData('youtube_link', e.target.value)} placeholder="https://youtube.com/..." />
                        </Field>

                        <Field label="Visible on Store">
                            <Select value={form.data.visible ?? 'yes'} onValueChange={(v) => form.setData('visible', v)}>
                                <SelectTrigger className="h-8 w-full text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="yes">Yes</SelectItem>
                                    <SelectItem value="no">No</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                </Card>

                {/* Variations (create only) */}
                {!isEditing && (
                    <Card title="Variations">
                        <VariationBuilder
                            productCode={form.data.code}
                            variationNames={variationNames}
                            onChange={(combos) => form.setData('combinations', combos)}
                            onEnabledChange={handleVariationsToggle}
                        />
                    </Card>
                )}

            </div>

            {/* ── RIGHT (sticky) ── */}
            <div className="space-y-4 lg:sticky lg:top-4 lg:self-start">

                <Card title="Product Image">
                    <ImageUploadBox
                        existingPath={isEditing ? form.data._existing_image : null}
                        onChange={(file) => form.setData('image', file)}
                    />
                    {form.errors.image && <p className="mt-1 text-xs text-destructive">{form.errors.image}</p>}
                </Card>

                <Card title="Chest Size Image">
                    <ImageUploadBox
                        existingPath={isEditing ? form.data._existing_chest_image : null}
                        onChange={(file) => form.setData('chest_size_image', file)}
                    />
                    {form.errors.chest_size_image && <p className="mt-1 text-xs text-destructive">{form.errors.chest_size_image}</p>}
                </Card>

                <Card title="Gallery Photos">
                    <ImageUploadBox onChange={(files) => form.setData('photos', files)} multiple />
                    {form.errors.photos && <p className="mt-1 text-xs text-destructive">{form.errors.photos}</p>}
                </Card>

                <Card title="Status & Settings">
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
                <Card title="Content">
                    <div className="space-y-3">
                        <Field label="Description (EN)" error={form.errors.description}>
                            <CKEditorField id="ck_description" value={form.data.description} onChange={(v) => form.setData('description', v)} />
                        </Field>
                        <Field label="Description (BN)" error={form.errors.bn_description}>
                            <CKEditorField id="ck_bn_description" value={form.data.bn_description} onChange={(v) => form.setData('bn_description', v)} />
                        </Field>
                        <Field label="Delivery Info (EN)" error={form.errors.delivery_info}>
                            <CKEditorField id="ck_delivery_info" value={form.data.delivery_info} onChange={(v) => form.setData('delivery_info', v)} />
                        </Field>
                        <Field label="Delivery Info (BN)" error={form.errors.bn_delivery_info}>
                            <CKEditorField id="ck_bn_delivery_info" value={form.data.bn_delivery_info} onChange={(v) => form.setData('bn_delivery_info', v)} />
                        </Field>
                    </div>
                </Card>
            </div>
        </div>
    );
}
