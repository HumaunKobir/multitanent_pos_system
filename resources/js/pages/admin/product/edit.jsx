import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, PackagePlus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { getSharedCombinationPrices } from '@/lib/variation-utils';
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
    suppliers = [],
    paymentAccounts = [],
    ecommerceBranchId = null,
    defaultCatalogBranchId = null,
    showBranchField = false,
    selectedColors = [],
    selectedSizes = [],
    variantsLocked = false,
    formBranchId = '',
}) {
    const initialCombinations = mapVariationsToCombinations(product.variations);
    const sharedCombinationPrices = getSharedCombinationPrices(initialCombinations);

    const form = useForm({
        branch_id: formBranchId,
        category_id: String(product.category_id ?? ''),
        brand_id: String(product.brand_id ?? ''),
        unit_id: String(product.unit_id ?? ''),
        warranty_id: product.warranty_id ? String(product.warranty_id) : null,
        name: product.name ?? '',
        code: (product.variations?.length ?? 0) > 0 ? '' : (product.code ?? ''),
        purchase_price: sharedCombinationPrices?.purchase_price ?? product.purchase_price ?? '',
        sale_price: sharedCombinationPrices?.sale_price ?? product.sale_price ?? '',
        discount_price: product.discount_price ?? '',
        initial_stock: product.initial_stock_record?.quantity != null ? String(product.initial_stock_record.quantity) : '',
        initial_stock_supplier_id: product.initial_stock_supplier_id ? String(product.initial_stock_supplier_id) : '',
        initial_stock_paid_amount: product.initial_stock_paid_amount != null && Number(product.initial_stock_paid_amount) > 0
            ? String(product.initial_stock_paid_amount)
            : '',
        initial_stock_payment_account_id: product.initial_stock_payment_account_id ? String(product.initial_stock_payment_account_id) : '',
        tags: product.tags ?? [],
        visible: product.visible ?? 'yes',
        status: String(product.status ?? '1'),
        description: product.description ?? '',
        delivery_info: product.delivery_info ?? '',
        youtube_link: product.youtube_link ?? '',
        image: null,
        photos: [],
        combinations: initialCombinations,
        color_ids: (product.colors ?? []).map(String),
        size_ids: (product.sizes ?? []).map(String),
        has_variants: (product.variations?.length ?? 0) > 0,
        _existing_image: product.image ?? null,
    });

    const productFormRef = useRef(null);
    const toast = useAppToast();

    function handleSubmit(e) {
        e.preventDefault();

        const validation = productFormRef.current?.validateBeforeSubmit?.();

        // #region agent log
        fetch('http://127.0.0.1:7682/ingest/b2b77a02-47d0-43f6-ab11-689e8f32c576',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'178204'},body:JSON.stringify({sessionId:'178204',hypothesisId:'I,K',location:'edit.jsx:handleSubmit',message:'handleSubmit fired',data:{validation_ok:validation?.ok ?? '(none)',validation_error:validation?.error ?? null,has_variants:form.data.has_variants,combinations_count:Array.isArray(form.data.combinations)?form.data.combinations.length:'not-array',sale_price:form.data.sale_price,branch_id:form.data.branch_id},timestamp:Date.now()})}).catch(()=>{});
        // #endregion

        if (validation && !validation.ok) {
            return;
        }

        form.patch(route('product.update', { product: product.slug }), {
            // #region agent log
            onStart: () => { fetch('http://127.0.0.1:7682/ingest/b2b77a02-47d0-43f6-ab11-689e8f32c576',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'178204'},body:JSON.stringify({sessionId:'178204',hypothesisId:'J',location:'edit.jsx:patch.onStart',message:'patch request started',data:{url:route('product.update',{product:product.slug})},timestamp:Date.now()})}).catch(()=>{}); },
            onError: (errors) => {
                // #region agent log
                fetch('http://127.0.0.1:7682/ingest/b2b77a02-47d0-43f6-ab11-689e8f32c576',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'178204'},body:JSON.stringify({sessionId:'178204',hypothesisId:'J',location:'edit.jsx:patch.onError',message:'patch returned errors',data:{errors},timestamp:Date.now()})}).catch(()=>{});
                // #endregion
                if (errors.initial_stock_payment_account_id) {
                    toast.warning(errors.initial_stock_payment_account_id);
                }
            },
            onFinish: () => { fetch('http://127.0.0.1:7682/ingest/b2b77a02-47d0-43f6-ab11-689e8f32c576',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'178204'},body:JSON.stringify({sessionId:'178204',hypothesisId:'J',location:'edit.jsx:patch.onFinish',message:'patch finished',data:{},timestamp:Date.now()})}).catch(()=>{}); },
            // #endregion
        });
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
                            suppliers={suppliers}
                            paymentAccounts={paymentAccounts}
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
