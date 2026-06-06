import { Head } from '@inertiajs/react';
import { ProductListingLayout } from '@/components/frontend/product-listing-layout';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function AllProducts({ products, filters }) {
    return (
        <FrontendLayout>
            <Head title="All Products" />
            <ProductListingLayout
                title="All Products"
                subtitle="Browse our full catalog"
                products={products}
                filters={filters}
                baseUrl="/products"
                isAllProducts
            />
        </FrontendLayout>
    );
}
