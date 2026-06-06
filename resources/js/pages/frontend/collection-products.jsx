import { Head } from '@inertiajs/react';
import { ProductListingLayout } from '@/components/frontend/product-listing-layout';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function CollectionProducts({ collectionName, products, filters, allBrands }) {
    return (
        <FrontendLayout>
            <Head title={collectionName || 'Collection'} />
            <ProductListingLayout
                title={collectionName ? `${collectionName}` : 'Collection'}
                subtitle={`Products in ${collectionName} collection`}
                products={products}
                filters={filters}
                baseUrl={`/collection/${encodeURIComponent(collectionName)}`}
                currentTagName={collectionName}
            />
        </FrontendLayout>
    );
}
