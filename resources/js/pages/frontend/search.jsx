import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ProductCard } from '@/components/frontend/product-card';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function Search({ query, products }) {
    return (
        <FrontendLayout>
            <Head title={`"${query}" — Search`} />
            <div className="store-container py-6">
                <Link href="/" className="mb-4 inline-flex items-center gap-1 text-sm text-store-muted hover:text-store-primary">
                    <ArrowLeft className="size-4" /> Home
                </Link>
                <h1 className="text-xl font-bold text-store-primary">
                    &quot;{query}&quot; — {products.total} results
                </h1>

                {products.data?.length > 0 ? (
                    <>
                        <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                        {products.links?.length > 3 && (
                            <div className="mt-8 flex flex-wrap justify-center gap-1">
                                {products.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`rounded-md border px-3 py-1.5 text-sm ${
                                                link.active
                                                    ? 'border-store-accent bg-store-accent text-white'
                                                    : 'border-gray-200 hover:border-store-accent'
                                            }`}
                                        />
                                    ) : null,
                                )}
                            </div>
                        )}
                    </>
                ) : (
                    <p className="mt-8 text-center text-store-muted">No products found.</p>
                )}
            </div>
        </FrontendLayout>
    );
}
