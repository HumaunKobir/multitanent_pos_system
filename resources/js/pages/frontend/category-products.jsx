import { Head } from '@inertiajs/react';
import { ProductListingLayout } from '@/components/frontend/product-listing-layout';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function CategoryProducts({ category, products, filters }) {
    return (
        <FrontendLayout>
            <Head title={category.name} />
            <ProductListingLayout
                title={category.name}
                subtitle="Category products"
                products={products}
                filters={filters}
                baseUrl={`/category/${category.slug}/products`}
            />
        </FrontendLayout>
    );
}
