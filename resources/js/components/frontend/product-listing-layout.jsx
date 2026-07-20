import { Link, router } from '@inertiajs/react';
import { LayoutGrid, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { ListingSortControl } from '@/components/frontend/listing-sort-control';
import { ProductCard } from '@/components/frontend/product-card';
import { ProductFilters } from '@/components/frontend/product-filters';

export function ProductListingLayout({
    title,
    subtitle,
    products,
    filters,
    baseUrl,
    hideHeader = false,
    currentCategorySlug,
    currentBrandSlug,
    currentTagName,
    isAllProducts = false,
}) {
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
    const [sortBy, setSortBy] = useState(filters?.sort_by ?? 'newest');

    const handleSort = (value) => {
        setSortBy(value);
        const params = { ...filters, sort_by: value };
        router.get(baseUrl, Object.fromEntries(Object.entries(params).filter(([, value]) => value)), {
            preserveScroll: true,
        });
    };

    const activeFilterCount = [filters?.min_price, filters?.max_price].filter(Boolean).length;

    return (
        <section className="bg-store-surface/60">
            <div className="store-container py-4 sm:py-5">
                {!hideHeader && (
                    <div className="mb-4">
                        <h1 className="text-lg font-bold text-store-primary sm:text-xl">{title}</h1>
                        {subtitle && <p className="mt-0.5 text-xs text-store-muted">{subtitle}</p>}
                    </div>
                )}

                <div className="flex gap-4 lg:gap-5">
                    <ProductFilters
                        filters={filters}
                        baseUrl={baseUrl}
                        mobileOpen={mobileFiltersOpen}
                        onMobileClose={() => setMobileFiltersOpen(false)}
                        currentCategorySlug={currentCategorySlug}
                        currentBrandSlug={currentBrandSlug}
                        currentTagName={currentTagName}
                        isAllProducts={isAllProducts}
                    />

                    <div className="min-w-0 flex-1">
                        <div className="mb-3 flex flex-col gap-2 rounded-2xl bg-white p-2 ring-1 ring-gray-200 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:p-2.5">
                            <div className="flex items-center justify-between gap-2 sm:justify-start">
                                <div className="flex items-center gap-1.5 text-xs text-store-muted">
                                    <LayoutGrid className="size-3.5 text-store-accent" aria-hidden />
                                    {products.total > 0 ? (
                                        <span>
                                            <span className="font-semibold text-store-primary">{products.total}</span>{' '}
                                            products
                                        </span>
                                    ) : (
                                        <span>No products</span>
                                    )}
                                </div>

                                <button
                                    type="button"
                                    onClick={() => setMobileFiltersOpen(true)}
                                    className="inline-flex items-center gap-1 rounded-full bg-store-surface px-2.5 py-1 text-[11px] font-semibold text-store-primary ring-1 ring-gray-200 lg:hidden"
                                >
                                    <SlidersHorizontal className="size-3 text-store-accent" />
                                    Filter
                                    {activeFilterCount > 0 && (
                                        <span className="flex size-4 items-center justify-center rounded-full bg-store-accent text-[9px] font-bold text-white">
                                            {activeFilterCount}
                                        </span>
                                    )}
                                </button>
                            </div>

                            <div className="flex items-center justify-end gap-2">
                                <span className="hidden text-[10px] font-semibold uppercase tracking-wider text-store-muted sm:inline">
                                    Sort
                                </span>
                                <div className="hidden md:block">
                                    <ListingSortControl value={sortBy} onChange={handleSort} variant="pills" />
                                </div>
                                <div className="md:hidden">
                                    <ListingSortControl value={sortBy} onChange={handleSort} variant="dropdown" />
                                </div>
                            </div>
                        </div>

                        {products.data?.length > 0 ? (
                            <>
                                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 sm:gap-2.5 lg:grid-cols-5 xl:grid-cols-6">
                                    {products.data.map((product) => (
                                        <ProductCard key={product.id} product={product} />
                                    ))}
                                </div>

                                {products.links?.length > 3 && (
                                    <nav aria-label="Pagination" className="mt-6 flex flex-wrap justify-center gap-1">
                                        {products.links.map((link, index) =>
                                            link.url ? (
                                                <Link
                                                    key={index}
                                                    href={link.url}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                    className={`min-w-7 rounded-full px-2.5 py-1 text-xs font-medium transition-colors ${
                                                        link.active
                                                            ? 'bg-store-accent text-white'
                                                            : 'bg-white text-store-primary ring-1 ring-gray-200 hover:ring-store-accent'
                                                    }`}
                                                />
                                            ) : (
                                                <span
                                                    key={index}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                    className="min-w-7 rounded-full px-2.5 py-1 text-xs text-gray-300"
                                                />
                                            ),
                                        )}
                                    </nav>
                                )}
                            </>
                        ) : (
                            <div className="rounded-2xl bg-white px-4 py-12 text-center ring-1 ring-gray-100">
                                <p className="text-sm font-medium text-store-primary">No products found</p>
                                <p className="mt-1 text-xs text-store-muted">Try a different price range or category.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
