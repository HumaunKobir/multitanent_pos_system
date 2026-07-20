import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search as SearchIcon } from 'lucide-react';
import { useState } from 'react';
import { ProductCard } from '@/components/frontend/product-card';
import FrontendLayout from '@/layouts/frontend/frontend-layout';
import { route } from '@/lib/route';

export default function Search({ query, products }) {
    const [searchQuery, setSearchQuery] = useState(query ?? '');

    const handleSearch = (event) => {
        event.preventDefault();

        const trimmedQuery = searchQuery.trim();

        if (!trimmedQuery) {
            return;
        }

        router.get(route('search', { query: { q: trimmedQuery } }));
    };

    return (
        <FrontendLayout>
            <Head title={query ? `"${query}" — Search` : 'Search'} />
            <div className="store-container py-6">
                <Link href="/" className="mb-4 inline-flex items-center gap-1 text-sm text-store-muted hover:text-store-primary">
                    <ArrowLeft className="size-4" /> Home
                </Link>

                <form onSubmit={handleSearch} className="mb-5">
                    <div className="relative flex max-w-2xl items-center">
                        <SearchIcon className="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-store-muted" />
                        <input
                            type="search"
                            value={searchQuery}
                            onChange={(event) => setSearchQuery(event.target.value)}
                            placeholder="Search by product, brand, category, or SKU..."
                            className="w-full rounded-full border border-gray-200 bg-white py-3 pl-11 pr-28 text-sm text-store-primary placeholder:text-store-muted focus:border-store-accent focus:outline-none focus:ring-2 focus:ring-store-accent/20"
                        />
                        <button
                            type="submit"
                            className="absolute right-1.5 rounded-full bg-store-primary px-4 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90"
                        >
                            Search
                        </button>
                    </div>
                </form>

                {query ? (
                    <h1 className="text-xl font-bold text-store-primary">
                        &quot;{query}&quot; — {products.total} results
                    </h1>
                ) : (
                    <h1 className="text-xl font-bold text-store-primary">Search products</h1>
                )}

                {products.data?.length > 0 ? (
                    <>
                        <div className="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-4 sm:gap-2.5 lg:grid-cols-5 xl:grid-cols-6">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                        {products.links?.length > 3 && (
                            <div className="mt-8 flex flex-wrap justify-center gap-1">
                                {products.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            key={index}
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
                    <p className="mt-8 text-center text-store-muted">
                        {query ? 'No products found for this search.' : 'Enter a keyword to search products.'}
                    </p>
                )}
            </div>
        </FrontendLayout>
    );
}
