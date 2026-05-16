import { Head } from '@inertiajs/react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';
import { ProductListing } from './collection-products';

export default function CategoryProducts({ category, products, filters, allBrands, allColors, allSizes }) {
    return (
        <FrontendLayout>
            <Head title={category.name} />
            <ProductListing
                title={category.name}
                products={products}
                filters={filters}
                allBrands={allBrands}
                allColors={allColors}
                allSizes={allSizes}
                baseUrl={`/category/${category.id}/products`}
            />
        </FrontendLayout>
    );
}
