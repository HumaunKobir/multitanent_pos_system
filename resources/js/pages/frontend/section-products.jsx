import { Head } from '@inertiajs/react';
import { ProductListingLayout } from '@/components/frontend/product-listing-layout';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function SectionProducts({ section, products, filters }) {
    return (
        <FrontendLayout>
            <Head title={section.name} />
            <ProductListingLayout
                title={section.name}
                subtitle={section.description}
                products={products}
                filters={filters}
                baseUrl={`/section/${section.id}/products`}
            />
        </FrontendLayout>
    );
}
