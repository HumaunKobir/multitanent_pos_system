import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, GitBranch, PackagePlus, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { SmartSelect } from '@/components/smart-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { route } from '@/lib/route';
import { buildVariationDataFromRows } from '@/lib/variation-utils';
import ProductForm from './partials/product-form';

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

function VariationSection({ product, variationNames = [] }) {
    const hasExisting = (product.variations ?? []).length > 0;
    const [enabled, setEnabled] = useState(hasExisting);
    const [varOptions, setVarOptions] = useState(() => variationNames.map((n) => ({ value: n, label: n })));
    const [rows, setRows] = useState([{ id: 1, name: '', values: [] }]);
    const [combinations, setCombinations] = useState(() =>
        (product.variations ?? []).map((v) => ({
            _id: v.id,
            _existing: true,
            variant: v.variation_data?.label ?? '',
            sale_price: String(v.price ?? ''),
            purchase_price: String(v.purchase_price ?? ''),
            sku: '',
            stock: String(v.stock ?? ''),
        })),
    );
    const [saving, setSaving] = useState(false);
    const [deletingId, setDeletingId] = useState(null);
    const [deleteError, setDeleteError] = useState('');

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

        if (!parsed.length) {
return;
}

        const productCode = product.code ?? '';
        const cartesian = parsed.map((r) => r.values).reduce((acc, cur) => {
            const res = [];
            acc.forEach((a) => cur.forEach((b) => res.push([...a, b])));

            return res;
        }, [[]]);

        const newCombos = cartesian.map((combo) => {
            const variantText = combo.join('-');
            const skuPart = variantText.replace(/[^A-Za-z0-9]+/g, '-').toUpperCase();
            const sku = [productCode, skuPart].filter(Boolean).join('-');

            return {
                _existing: false,
                variant: variantText,
                variation_data: buildVariationDataFromRows(parsed, combo),
                sale_price: '',
                purchase_price: '',
                sku,
                stock: '',
            };
        });

        setCombinations((prev) => [...prev.filter((c) => c._existing), ...newCombos]);
    }

    function updateCombo(idx, field, val) {
        setCombinations((prev) => prev.map((c, i) => (i === idx ? { ...c, [field]: val } : c)));
    }

    function removeNewCombo(idx) {
        setCombinations((prev) => prev.filter((_, i) => i !== idx));
    }

    async function saveNewCombinations() {
        const newCombos = combinations.filter((c) => !c._existing);

        if (!newCombos.length) {
return;
}

        setSaving(true);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        try {
            for (const combo of newCombos) {
                const res = await fetch(route('variation.store'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: product.id,
                        variation_data: combo.variation_data ?? { label: combo.variant },
                        price: combo.sale_price,
                        purchase_price: combo.purchase_price,
                        stock: combo.stock,
                    }),
                });

                const json = await res.json();

                if (res.ok) {
                    setCombinations((prev) =>
                        prev.map((c) =>
                            !c._existing && c.variant === combo.variant
                                ? { ...c, _existing: true, _id: json.variation.id }
                                : c,
                        ),
                    );
                }
            }
        } finally {
            setSaving(false);
        }
    }

    async function handleDeleteVariation() {
        if (!deletingId) {
return;
}

        setDeleteError('');

        const res = await fetch(route('variation.destroy', deletingId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });

        const json = await res.json();

        if (!res.ok) {
            setDeleteError(json.error ?? 'Could not delete variation.');

            return;
        }

        setCombinations((prev) => prev.filter((c) => c._id !== deletingId));
        setDeletingId(null);
    }

    const hasNewCombos = combinations.some((c) => !c._existing);

    return (
        <>
            <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
                <div className="flex items-center gap-2.5 bg-blue-950 px-4 py-2.5">
                    <div className="flex size-6 items-center justify-center rounded bg-white/15">
                        <GitBranch className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-white">Variations</h2>
                </div>

                <div className="p-2">
                    <label className="flex cursor-pointer items-center gap-3">
                        <div className="relative">
                            <input type="checkbox" className="sr-only" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
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
                    <div className="flex items-center gap-2 px-3">
                        <Label className="w-1/3 text-xs">Variation Name</Label>
                        <Label className="flex-1 text-xs">Value (Tags)</Label>
                        <div className="w-8" />
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
                                        creatable
                                        createMode="inline"
                                        createRowLabel={(q) => `Add "${q}"`}
                                    />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <TagInput tags={row.values} onChange={(v) => setRowValues(row.id, v)} />
                                </div>
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

                    <div className="flex items-center gap-2">
                        <Button type="button" size="sm" className="bg-green-600 text-white hover:bg-green-700" onClick={buildCombinations}>
                            Build Combinations
                        </Button>
                        {hasNewCombos && (
                            <Button type="button" size="sm" onClick={saveNewCombinations} disabled={saving}>
                                {saving ? 'Saving…' : 'Save New Combinations'}
                            </Button>
                        )}
                    </div>

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
                                        <tr key={combo._existing ? combo._id : `new-${idx}`} className={`border-b last:border-0 ${!combo._existing ? 'bg-green-50/30' : ''}`}>
                                            <td className="p-2 text-muted-foreground">{idx + 1}</td>
                                            <td className="p-2 font-medium">{combo.variant}</td>
                                            <td className="p-2">
                                                <Input
                                                    type="number" min="0" step="0.01"
                                                    className="h-7 w-24 text-xs"
                                                    value={combo.sale_price}
                                                    onChange={(e) => updateCombo(idx, 'sale_price', e.target.value)}
                                                    disabled={combo._existing}
                                                />
                                            </td>
                                            <td className="p-2">
                                                <Input
                                                    type="number" min="0" step="0.01"
                                                    className="h-7 w-24 text-xs"
                                                    value={combo.purchase_price}
                                                    onChange={(e) => updateCombo(idx, 'purchase_price', e.target.value)}
                                                    disabled={combo._existing}
                                                />
                                            </td>
                                            <td className="p-2">
                                                <Input
                                                    className="h-7 w-32 text-xs"
                                                    value={combo.sku}
                                                    onChange={(e) => updateCombo(idx, 'sku', e.target.value)}
                                                    disabled={combo._existing}
                                                />
                                            </td>
                                            <td className="p-2">
                                                <Input
                                                    type="number" min="0"
                                                    className="h-7 w-20 text-xs"
                                                    value={combo.stock}
                                                    onChange={(e) => updateCombo(idx, 'stock', e.target.value)}
                                                    disabled={combo._existing}
                                                />
                                            </td>
                                            <td className="p-2">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (combo._existing) {
                                                            setDeleteError('');
                                                            setDeletingId(combo._id);
                                                        } else {
                                                            removeNewCombo(idx);
                                                        }
                                                    }}
                                                    className="text-destructive hover:text-destructive/80"
                                                >
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
            </div>

            <Dialog open={!!deletingId} onOpenChange={(open) => !open && setDeletingId(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Variation</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">Are you sure you want to delete this variation?</p>
                    {deleteError && <p className="text-sm text-destructive">{deleteError}</p>}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button variant="destructive" onClick={handleDeleteVariation}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function PhotosSection({ product }) {
    const [photos, setPhotos] = useState(product.photos ?? []);

    async function handleDeletePhoto(photo) {
        const res = await fetch(`/product-photo/${photo.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });

        if (res.ok) {
            setPhotos((prev) => prev.filter((p) => p.id !== photo.id));
        }
    }

    if (photos.length === 0) {
return null;
}

    return (
        <div className="rounded-lg border bg-card p-6">
            <h2 className="mb-4 text-base font-semibold">Existing Photos</h2>
            <div className="flex flex-wrap gap-3">
                {photos.map((photo) => (
                    <div key={photo.id} className="group relative">
                        <img src={`/storage/${photo.image}`} alt="Product photo" className="h-20 w-20 rounded object-cover" />
                        <button
                            type="button"
                            onClick={() => handleDeletePhoto(photo)}
                            className="absolute top-1 right-1 hidden rounded-full bg-destructive p-0.5 text-destructive-foreground group-hover:flex"
                        >
                            <Trash2 className="size-3" />
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function ProductEdit({ product, categories, brands, units, warranties, branches, variationNames = [], tagOptions = [] }) {
    const form = useForm({
        branch_id: product.branch_id ?? null,
        category_id: String(product.category_id ?? ''),
        brand_id: String(product.brand_id ?? ''),
        unit_id: String(product.unit_id ?? ''),
        warranty_id: product.warranty_id ? String(product.warranty_id) : null,
        name: product.name ?? '',
        code: product.code ?? '',
        purchase_price: product.purchase_price ?? '',
        sale_price: product.sale_price ?? '',
        discount_price: product.discount_price ?? '',
        tags: product.tags ?? [],
        visible: product.visible ?? 'yes',
        status: String(product.status ?? '1'),
        description: product.description ?? '',
        delivery_info: product.delivery_info ?? '',
        youtube_link: product.youtube_link ?? '',
        image: null,
        photos: [],
        _existing_image: product.image ?? null,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.patch(route('product.update', { product: product.slug }));
    }

    return (
        <>
            <Head title={`Edit: ${product.name}`} />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <PackagePlus className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Edit Product</h1>
                            <p className="text-xs text-white/60">{product.name}</p>
                        </div>
                    </div>
                    <Button size="sm" asChild className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md">
                        <Link href={route('product.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="space-y-6">
                    <form onSubmit={handleSubmit} encType="multipart/form-data">
                        <ProductForm
                            form={form}
                            categories={categories}
                            brands={brands}
                            units={units}
                            warranties={warranties}
                            branches={branches}
                            variationNames={variationNames}
                            tagOptions={tagOptions}
                            isEditing
                            processing={form.processing}
                            cancelHref={route('product.index')}
                        />
                    </form>

                    <PhotosSection product={product} />
                    <VariationSection product={product} variationNames={variationNames} />
                </div>
            </div>
        </>
    );
}
