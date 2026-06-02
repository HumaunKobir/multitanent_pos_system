import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { SlidersHorizontal, X } from 'lucide-react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';
import { ProductCard } from '@/components/frontend/product-card';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';

export default function CollectionProducts({ collectionName, products, filters, allBrands, allColors, allSizes }) {
    return (
        <FrontendLayout>
            <Head title={collectionName} />
            <ProductListing
                title={collectionName}
                products={products}
                filters={filters}
                allBrands={allBrands}
                allColors={allColors}
                allSizes={allSizes}
                baseUrl={`/collection/${encodeURIComponent(collectionName)}`}
            />
        </FrontendLayout>
    );
}

export function ProductListing({ title, products, filters, allBrands, allColors, allSizes, baseUrl }) {
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState(filters || {});

    useDebouncedEffect(
        () => {
            const params = Object.fromEntries(Object.entries(localFilters).filter(([, v]) => v && v.length));
            router.get(baseUrl, params, { preserveScroll: true, replace: true });
        },
        [localFilters, baseUrl],
        350,
        { skipFirstRun: true },
    );

    const clearFilters = () => {
        setLocalFilters({});
    };

    const sortOptions = [
        { value: 'newest', label: 'নতুন আগে' },
        { value: 'price_low', label: 'কম দামে' },
        { value: 'price_high', label: 'বেশি দামে' },
        { value: 'name', label: 'নাম অনুযায়ী' },
    ];

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                    <p className="mt-1 text-sm text-gray-500">{products.total} টি পণ্য পাওয়া গেছে</p>
                </div>
                <div className="flex items-center gap-3">
                    <select
                        value={localFilters.sort_by ?? 'newest'}
                        onChange={(e) => {
                            const next = { ...localFilters, sort_by: e.target.value };
                            setLocalFilters(next);
                            const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v && v.length));
                            router.get(baseUrl, params, { preserveScroll: true });
                        }}
                        className="border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-black focus:outline-none"
                    >
                        {sortOptions.map((o) => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                        ))}
                    </select>
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className="flex items-center gap-1.5 border border-gray-300 px-3 py-1.5 text-sm hover:border-black"
                    >
                        <SlidersHorizontal className="size-4" />
                        ফিল্টার
                    </button>
                </div>
            </div>

            {/* Filter panel */}
            {showFilters && (
                <div className="mb-6 border border-gray-200 bg-gray-50 p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-700">ন্যূনতম দাম</label>
                            <input
                                type="number"
                                value={localFilters.min_price ?? ''}
                                onChange={(e) => setLocalFilters((p) => ({ ...p, min_price: e.target.value }))}
                                className="w-full border border-gray-300 px-2 py-1.5 text-sm focus:border-black focus:outline-none"
                                placeholder="৳"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-700">সর্বোচ্চ দাম</label>
                            <input
                                type="number"
                                value={localFilters.max_price ?? ''}
                                onChange={(e) => setLocalFilters((p) => ({ ...p, max_price: e.target.value }))}
                                className="w-full border border-gray-300 px-2 py-1.5 text-sm focus:border-black focus:outline-none"
                                placeholder="৳"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-700">রঙ</label>
                            <div className="flex flex-wrap gap-1">
                                {allColors.map((color) => {
                                    const selected = (localFilters.colors ?? []).includes(color);
                                    return (
                                        <button
                                            key={color}
                                            onClick={() =>
                                                setLocalFilters((p) => ({
                                                    ...p,
                                                    colors: selected
                                                        ? (p.colors ?? []).filter((c) => c !== color)
                                                        : [...(p.colors ?? []), color],
                                                }))
                                            }
                                            className={`border px-2 py-0.5 text-xs transition-colors ${selected ? 'border-black bg-black text-white' : 'border-gray-300 hover:border-black'}`}
                                        >
                                            {color}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-700">সাইজ</label>
                            <div className="flex flex-wrap gap-1">
                                {allSizes.map((size) => {
                                    const selected = (localFilters.sizes ?? []).includes(size);
                                    return (
                                        <button
                                            key={size}
                                            onClick={() =>
                                                setLocalFilters((p) => ({
                                                    ...p,
                                                    sizes: selected
                                                        ? (p.sizes ?? []).filter((s) => s !== size)
                                                        : [...(p.sizes ?? []), size],
                                                }))
                                            }
                                            className={`border px-2 py-0.5 text-xs transition-colors ${selected ? 'border-black bg-black text-white' : 'border-gray-300 hover:border-black'}`}
                                        >
                                            {size}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                    <div className="mt-4 flex gap-2">
                        <button
                            onClick={clearFilters}
                            className="flex items-center gap-1 border border-gray-300 px-4 py-2 text-sm hover:border-black"
                        >
                            <X className="size-3" /> ক্লিয়ার
                        </button>
                    </div>
                </div>
            )}

            {/* Products grid */}
            {products.data?.length > 0 ? (
                <>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {products.data.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>

                    {/* Pagination */}
                    {products.links && (
                        <div className="mt-8 flex flex-wrap justify-center gap-1">
                            {products.links.map((link, i) => (
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`border px-3 py-1.5 text-sm ${link.active ? 'border-black bg-black text-white' : 'border-gray-300 text-gray-700 hover:border-black'}`}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className="border border-gray-200 px-3 py-1.5 text-sm text-gray-400"
                                    />
                                )
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div className="py-16 text-center text-gray-400">কোনো পণ্য পাওয়া যায়নি।</div>
            )}
        </div>
    );
}
