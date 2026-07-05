import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, PackagePlus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { route } from '@/lib/route';
import ProductForm from './partials/product-form';

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

function mapVariationsToCombinations(variations = []) {
    return variations.map((variation) => ({
        id: variation.id,
        variant: variation.variation_data?.label ?? '',
        variation_data: variation.variation_data,
        sale_price: String(variation.price ?? ''),
        purchase_price: String(variation.purchase_price ?? ''),
        sku: variation.sku ?? '',
        stock: String(variation.stock ?? ''),
    }));
}

export default function ProductEdit({
    product,
    categories,
    brands,
    units,
    warranties,
    branches,
    colorOptions = [],
    sizeOptions = [],
    tagOptions = [],
    ecommerceBranchId = null,
    defaultCatalogBranchId = null,
    showBranchField = false,
    selectedColors = [],
    selectedSizes = [],
    variantsLocked = false,
    formBranchId = '',
}) {
    const form = useForm({
        branch_id: formBranchId,
        category_id: String(product.category_id ?? ''),
        brand_id: String(product.brand_id ?? ''),
        unit_id: String(product.unit_id ?? ''),
        warranty_id: product.warranty_id ? String(product.warranty_id) : null,
        name: product.name ?? '',
        code: (product.variations?.length ?? 0) > 0 ? '' : (product.code ?? ''),
        purchase_price: product.purchase_price ?? '',
        sale_price: product.sale_price ?? '',
        discount_price: product.discount_price ?? '',
        initial_stock: product.initial_stock_record?.quantity != null ? String(product.initial_stock_record.quantity) : '',
        tags: product.tags ?? [],
        visible: product.visible ?? 'yes',
        status: String(product.status ?? '1'),
        description: product.description ?? '',
        delivery_info: product.delivery_info ?? '',
        youtube_link: product.youtube_link ?? '',
        image: null,
        photos: [],
        combinations: mapVariationsToCombinations(product.variations),
        color_ids: (product.colors ?? []).map(String),
        size_ids: (product.sizes ?? []).map(String),
        has_variants: (product.variations?.length ?? 0) > 0,
        _existing_image: product.image ?? null,
    });

    const productFormRef = useRef(null);

    function handleSubmit(e) {
        e.preventDefault();

        const validation = productFormRef.current?.validateBeforeSubmit?.();

        if (validation && !validation.ok) {
            return;
        }

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
                            ref={productFormRef}
                            form={form}
                            categories={categories}
                            brands={brands}
                            units={units}
                            warranties={warranties}
                            branches={branches}
                            colorOptions={colorOptions}
                            sizeOptions={sizeOptions}
                            tagOptions={tagOptions}
                            ecommerceBranchId={ecommerceBranchId}
                            defaultCatalogBranchId={defaultCatalogBranchId}
                            showBranchField={showBranchField}
                            sourceBranchId={product.branch_id}
                            selectedCatalog={{
                                category: product.category
                                    ? { id: product.category_id, label: product.category.name }
                                    : null,
                                brand: product.brand
                                    ? { id: product.brand_id, label: product.brand.name }
                                    : null,
                                unit: product.unit
                                    ? { id: product.unit_id, label: product.unit.name }
                                    : null,
                                warranty: product.warranty
                                    ? { id: product.warranty_id, label: product.warranty.name }
                                    : null,
                            }}
                            selectedColors={selectedColors}
                            selectedSizes={selectedSizes}
                            initialVariations={product.variations ?? []}
                            variantsLocked={variantsLocked}
                            isEditing
                            processing={form.processing}
                            cancelHref={route('product.index')}
                        />
                    </form>

                    <PhotosSection product={product} />
                </div>
            </div>
        </>
    );
}
