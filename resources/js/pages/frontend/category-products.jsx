import { Head } from '@inertiajs/react';
import { CategoryListingHero } from '@/components/frontend/category-listing-hero';
import { ProductListingLayout } from '@/components/frontend/product-listing-layout';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function CategoryProducts({ category, products, filters }) {
    return (
        <FrontendLayout>
            <Head title={category.name} />
            <CategoryListingHero category={category} productCount={products.total} />
            <ProductListingLayout
                products={products}
                filters={filters}
                baseUrl={`/category/${category.slug}/products`}
                hideHeader
                currentCategorySlug={category.slug}
            />
        </FrontendLayout>
    );
}
