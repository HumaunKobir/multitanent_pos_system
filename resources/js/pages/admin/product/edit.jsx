import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { route } from '@/lib/route';
import ProductForm from './partials/product-form';

function VariationSection({ product }) {
    const [variations, setVariations] = useState(product.variations ?? []);
    const [adding, setAdding] = useState(false);
    const [deletingId, setDeletingId] = useState(null);
    const [deleteError, setDeleteError] = useState('');
    const [varForm, setVarForm] = useState({
        variation_label: '',
        price: '',
        purchase_price: '',
        stock: '',
    });
    const [varErrors, setVarErrors] = useState({});
    const [saving, setSaving] = useState(false);

    async function handleAddVariation() {
        setSaving(true);
        setVarErrors({});

        try {
            const res = await fetch(route('variation.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    product_id: product.id,
                    variation_data: { label: varForm.variation_label },
                    price: varForm.price,
                    purchase_price: varForm.purchase_price,
                    stock: varForm.stock,
                }),
            });

            const json = await res.json();

            if (!res.ok) {
                if (json.errors) setVarErrors(json.errors);
                return;
            }

            setVariations((prev) => [...prev, json.variation]);
            setVarForm({ variation_label: '', price: '', purchase_price: '', stock: '' });
            setAdding(false);
        } finally {
            setSaving(false);
        }
    }

    async function handleDeleteVariation() {
        if (!deletingId) return;
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

        setVariations((prev) => prev.filter((v) => v.id !== deletingId));
        setDeletingId(null);
    }

    return (
        <div className="rounded-lg border bg-card p-6">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-base font-semibold">Variations</h2>
                <Button size="sm" type="button" onClick={() => setAdding(true)}>
                    <Plus className="size-4" />
                    Add Variation
                </Button>
            </div>

            {adding && (
                <div className="mb-4 rounded-lg border bg-muted/30 p-4">
                    <h3 className="mb-3 text-sm font-medium">New Variation</h3>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div className="md:col-span-2">
                            <Label className="text-xs">Variation Label</Label>
                            <Input
                                value={varForm.variation_label}
                                onChange={(e) => setVarForm((f) => ({ ...f, variation_label: e.target.value }))}
                                placeholder="e.g. Red-Large"
                                className="mt-1"
                                aria-invalid={!!varErrors['variation_data']}
                            />
                            {varErrors['variation_data'] && <p className="mt-1 text-xs text-destructive">{varErrors['variation_data']}</p>}
                        </div>
                        <div>
                            <Label className="text-xs">Sale Price</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={varForm.price}
                                onChange={(e) => setVarForm((f) => ({ ...f, price: e.target.value }))}
                                className="mt-1"
                                aria-invalid={!!varErrors.price}
                            />
                            {varErrors.price && <p className="mt-1 text-xs text-destructive">{varErrors.price}</p>}
                        </div>
                        <div>
                            <Label className="text-xs">Purchase Price</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={varForm.purchase_price}
                                onChange={(e) => setVarForm((f) => ({ ...f, purchase_price: e.target.value }))}
                                className="mt-1"
                                aria-invalid={!!varErrors.purchase_price}
                            />
                            {varErrors.purchase_price && <p className="mt-1 text-xs text-destructive">{varErrors.purchase_price}</p>}
                        </div>
                        <div>
                            <Label className="text-xs">Stock</Label>
                            <Input
                                type="number"
                                min="0"
                                value={varForm.stock}
                                onChange={(e) => setVarForm((f) => ({ ...f, stock: e.target.value }))}
                                className="mt-1"
                                aria-invalid={!!varErrors.stock}
                            />
                            {varErrors.stock && <p className="mt-1 text-xs text-destructive">{varErrors.stock}</p>}
                        </div>
                    </div>
                    <div className="mt-3 flex gap-2">
                        <Button size="sm" type="button" onClick={handleAddVariation} disabled={saving}>
                            {saving ? 'Saving...' : 'Save'}
                        </Button>
                        <Button size="sm" variant="outline" type="button" onClick={() => setAdding(false)}>
                            Cancel
                        </Button>
                    </div>
                </div>
            )}

            {variations.length === 0 ? (
                <p className="text-sm text-muted-foreground">No variations yet. Click "Add Variation" to create one.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-xs text-muted-foreground">
                                <th className="py-2 text-left">Label</th>
                                <th className="py-2 text-right">Purchase</th>
                                <th className="py-2 text-right">Sale</th>
                                <th className="py-2 text-right">Stock</th>
                                <th className="py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {variations.map((v) => (
                                <tr key={v.id} className="border-b last:border-0">
                                    <td className="py-2">
                                        {v.variation_data?.label ?? JSON.stringify(v.variation_data)}
                                    </td>
                                    <td className="py-2 text-right">৳{v.purchase_price}</td>
                                    <td className="py-2 text-right">৳{v.price}</td>
                                    <td className="py-2 text-right">
                                        <Badge className={v.stock > 0 ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}>
                                            {v.stock}
                                        </Badge>
                                    </td>
                                    <td className="py-2 text-right">
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            type="button"
                                            onClick={() => {
                                                setDeleteError('');
                                                setDeletingId(v.id);
                                            }}
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

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
        </div>
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

    if (photos.length === 0) return null;

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

export default function ProductEdit({ product, categories, brands, units, warranties, colors, sizes, tailors, branches }) {
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
        type: product.type ?? 'notstitch',
        tailor_option: product.tailor_option ?? 'no',
        tailor_price: product.tailor_price ?? '0',
        colors: (product.colors ?? []).map(Number),
        sizes: (product.sizes ?? []).map(Number),
        tailormeasurement: (product.tailormeasurement ?? []).map(Number),
        tags: product.tags ?? [],
        visible: product.visible ?? 'yes',
        status: String(product.status ?? '1'),
        description: product.description ?? '',
        bn_description: product.bn_description ?? '',
        delivery_info: product.delivery_info ?? '',
        youtube_link: product.youtube_link ?? '',
        image: null,
        photos: [],
        _existing_image: product.image ?? null,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('product.update', product.slug), { _method: 'patch' });
    }

    return (
        <>
            <Head title={`Edit: ${product.name}`} />

            <div className="p-6">
                <div className="mb-6 flex items-center gap-3">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('product.index')}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">Edit Product</h1>
                        <p className="text-sm text-muted-foreground">{product.name}</p>
                    </div>
                </div>

                <div className="space-y-6">
                    <form onSubmit={handleSubmit} encType="multipart/form-data">
                        <ProductForm
                            form={form}
                            categories={categories}
                            brands={brands}
                            units={units}
                            warranties={warranties}
                            colors={colors}
                            sizes={sizes}
                            tailors={tailors}
                            branches={branches}
                            isEditing
                        />

                        <div className="mt-6 flex justify-end gap-3">
                            <Button variant="outline" type="button" asChild>
                                <Link href={route('product.index')}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving...' : 'Update Product'}
                            </Button>
                        </div>
                    </form>

                    <PhotosSection product={product} />
                    <VariationSection product={product} />
                </div>
            </div>
        </>
    );
}
