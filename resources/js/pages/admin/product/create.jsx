import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { route } from '@/lib/route';
import ProductForm from './partials/product-form';

export default function ProductCreate({ categories, brands, units, warranties, colors, sizes, tailors, branches, variationNames = [] }) {
    const form = useForm({
        branch_id: null,
        category_id: '',
        brand_id: '',
        unit_id: '',
        warranty_id: null,
        name: '',
        code: '',
        purchase_price: '',
        sale_price: '',
        discount_price: '',
        type: 'notstitch',
        tailor_option: 'no',
        tailor_price: '0',
        colors: [],
        sizes: [],
        tailormeasurement: [],
        tags: [],
        visible: 'yes',
        status: '1',
        description: '',
        bn_description: '',
        delivery_info: '',
        bn_delivery_info: '',
        youtube_link: '',
        image: null,
        chest_size_image: null,
        photos: [],
        combinations: [],
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('product.store'));
    }

    return (
        <>
            <Head title="Add Product" />

            <div className="p-6">
                <div className="mb-6 flex items-center gap-3">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('product.index')}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-xl font-semibold">Add Product</h1>
                </div>

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
                        variationNames={variationNames}
                        processing={form.processing}
                        cancelHref={route('product.index')}
                    />
                </form>
            </div>
        </>
    );
}
