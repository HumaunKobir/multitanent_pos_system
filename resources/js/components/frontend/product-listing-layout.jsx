import { Link, router } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { ProductCard } from '@/components/frontend/product-card';
import { ProductFilters } from '@/components/frontend/product-filters';

export function ProductListingLayout({ title, subtitle, products, filters, baseUrl }) {
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
    const [sortBy, setSortBy] = useState(filters?.sort_by ?? 'newest');

    const sortOptions = [
        { value: 'newest', label: 'Newest first' },
        { value: 'price_low', label: 'Price: low to high' },
        { value: 'price_high', label: 'Price: high to low' },
        { value: 'name', label: 'Name A–Z' },
    ];

    const handleSort = (value) => {
        setSortBy(value);
        const params = { ...filters, sort_by: value };
        router.get(baseUrl, Object.fromEntries(Object.entries(params).filter(([, v]) => v)), {
            preserveScroll: true,
        });
    };

    return (
        <div className="store-container py-6">
            <div className="mb-5">
                <h1 className="text-xl font-bold text-store-primary sm:text-2xl">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-store-muted">{subtitle}</p>}
                <p className="mt-0.5 text-xs text-store-muted">{products.total} products</p>
            </div>

            <div className="mb-4 flex items-center justify-between gap-3 lg:hidden">
                <button
                    onClick={() => setMobileFiltersOpen(true)}
                    className="flex items-center gap-1.5 rounded-md border border-gray-200 px-3 py-2 text-sm"
                >
                    <SlidersHorizontal className="size-4" /> Filters
                </button>
                <select
                    value={sortBy}
                    onChange={(e) => handleSort(e.target.value)}
                    className="rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-store-accent focus:outline-none"
                >
                    {sortOptions.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="flex gap-6">
                <ProductFilters
                    filters={filters}
                    baseUrl={baseUrl}
                    mobileOpen={mobileFiltersOpen}
                    onMobileClose={() => setMobileFiltersOpen(false)}
                />

                <div className="min-w-0 flex-1">
                    <div className="mb-4 hidden items-center justify-end lg:flex">
                        <select
                            value={sortBy}
                            onChange={(e) => handleSort(e.target.value)}
                            className="rounded-md border border-gray-200 px-3 py-1.5 text-sm focus:border-store-accent focus:outline-none"
                        >
                            {sortOptions.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {products.data?.length > 0 ? (
                        <>
                            <div className="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4">
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
                                                        : 'border-gray-200 text-store-primary hover:border-store-accent'
                                                }`}
                                            />
                                        ) : (
                                            <span
                                                key={i}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className="rounded-md border border-gray-100 px-3 py-1.5 text-sm text-gray-300"
                                            />
                                        ),
                                    )}
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="py-16 text-center text-store-muted">No products found.</div>
                    )}
                </div>
            </div>
        </div>
    );
}
