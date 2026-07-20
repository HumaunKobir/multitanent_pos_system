import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, PackagePlus } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import ProductForm from './partials/product-form';

export default function ProductCreate({
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
    actingBranchId = null,
    defaultCatalogBranchId = null,
    showBranchField = false,
}) {
    const form = useForm({
        branch_id: showBranchField && defaultCatalogBranchId != null ? String(defaultCatalogBranchId) : '',
        category_id: '',
        brand_id: '',
        unit_id: '',
        warranty_id: null,
        name: '',
        code: '',
        purchase_price: '',
        sale_price: '',
        discount_price: '',
        initial_stock: '',
        initial_stock_supplier_id: '',
        initial_stock_paid_amount: '',
        initial_stock_payment_account_id: '',
        tags: [],
        visible: 'no',
        status: '1',
        description: '',
        delivery_info: '',
        youtube_link: '',
        image: null,
        photos: [],
        combinations: [],
        color_ids: [],
        size_ids: [],
        has_variants: false,
    });

    const productFormRef = useRef(null);
    const toast = useAppToast();

    function handleSubmit(e) {
        e.preventDefault();

        const validation = productFormRef.current?.validateBeforeSubmit?.();

        if (validation && !validation.ok) {
            return;
        }

        form.post(route('product.store'), {
            forceFormData: true,
            onError: (errors) => {
                if (errors.initial_stock_payment_account_id) {
                    toast.warning(errors.initial_stock_payment_account_id);
                }
            },
        });
    }

    return (
        <>
            <Head title="Add Product" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <PackagePlus className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Add Product</h1>
                            <p className="text-xs text-white/60">Create a new product for your inventory.</p>
                        </div>
                    </div>
                    <Button size="sm" asChild className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md">
                        <Link href={route('product.index')}>
                            <ArrowLeft className="size-3.5" />
                            Back
                        </Link>
                    </Button>
                </div>

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
                        actingBranchId={actingBranchId}
                        defaultCatalogBranchId={defaultCatalogBranchId}
                        showBranchField={showBranchField}
                        processing={form.processing}
                        cancelHref={route('product.index')}
                    />
                </form>
            </div>
        </>
    );
}
