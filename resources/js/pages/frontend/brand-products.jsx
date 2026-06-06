import { Head } from '@inertiajs/react';
import { ProductListingLayout } from '@/components/frontend/product-listing-layout';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function BrandProducts({ brand, products, filters }) {
    return (
        <FrontendLayout>
            <Head title={brand.name} />
            <ProductListingLayout
                title={brand.name}
                subtitle="Brand products"
                products={products}
                filters={filters}
                baseUrl={`/brand/${brand.slug}/products`}
                currentBrandSlug={brand.slug}
            />
        </FrontendLayout>
    );
}
