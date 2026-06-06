import { Link, router, usePage } from '@inertiajs/react';
import { Dialog, DialogPanel } from '@headlessui/react';
import { RotateCcw, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';

const pricePresets = [
    { label: '<1k', min: '', max: '1000' },
    { label: '1–3k', min: '1000', max: '3000' },
    { label: '3–5k', min: '3000', max: '5000' },
    { label: '5k+', min: '5000', max: '' },
];

function FilterChipSection({ title, children }) {
    if (!children) {
        return null;
    }

    return (
        <div>
            <p className="mb-1.5 text-[10px] font-bold uppercase tracking-widest text-store-muted">{title}</p>
            {children}
        </div>
    );
}

function FilterChip({ href, label, active = false }) {
    return (
        <Link
            href={href}
            className={`rounded-full px-2 py-0.5 text-[10px] font-medium transition-all ${
                active
                    ? 'bg-store-primary text-white ring-1 ring-store-primary'
                    : 'bg-white text-store-primary ring-1 ring-gray-200 hover:bg-store-accent hover:text-white hover:ring-store-accent'
            }`}
        >
            {label}
        </Link>
    );
}

function BrowseSections({ currentCategorySlug, currentBrandSlug, currentTagName }) {
    const { categories = [], brands = [], tags = [] } = usePage().props;

    const otherCategories = categories.filter((category) => category.slug !== currentCategorySlug);
    const hasCategories = otherCategories.length > 0;
    const hasBrands = brands.length > 0;
    const hasTags = tags.length > 0;

    if (!hasCategories && !hasBrands && !hasTags) {
        return null;
    }

    return (
        <div className="space-y-3">
            {hasCategories && (
                <FilterChipSection title="Categories">
                    <div className="flex flex-wrap gap-1">
                        {otherCategories.map((category) => (
                            <FilterChip
                                key={category.id}
                                href={`/category/${category.slug}/products`}
                                label={category.name}
                                active={category.slug === currentCategorySlug}
                            />
                        ))}
                    </div>
                </FilterChipSection>
            )}

            {hasBrands && (
                <FilterChipSection title="Brands">
                    <div className="flex flex-wrap gap-1">
                        {brands.map((brand) => (
                            <FilterChip
                                key={brand.id}
                                href={`/brand/${brand.slug}/products`}
                                label={brand.name}
                                active={brand.slug === currentBrandSlug}
                            />
                        ))}
                    </div>
                </FilterChipSection>
            )}

            {hasTags && (
                <FilterChipSection title="Tags">
                    <div className="flex flex-wrap gap-1">
                        {tags.map((tag) => (
                            <FilterChip
                                key={tag.id}
                                href={`/collection/${encodeURIComponent(tag.name)}`}
                                label={tag.name}
                                active={tag.name === currentTagName}
                            />
                        ))}
                    </div>
                </FilterChipSection>
            )}
        </div>
    );
}

export function ProductFilters({
    filters,
    baseUrl,
    mobileOpen,
    onMobileClose,
    currentCategorySlug,
    currentBrandSlug,
    currentTagName,
}) {
    const [localFilters, setLocalFilters] = useState(filters || {});

    useDebouncedEffect(
        () => {
            const params = Object.fromEntries(
                Object.entries(localFilters).filter(([, value]) => value !== '' && value != null),
            );
            router.get(baseUrl, params, { preserveScroll: true, replace: true });
        },
        [localFilters, baseUrl],
        350,
        { skipFirstRun: true },
    );

    const hasPriceActive = Boolean(localFilters.min_price || localFilters.max_price);

    const activeLabel = useMemo(() => {
        const min = localFilters.min_price;
        const max = localFilters.max_price;
        if (min && max) {
            return `৳${min} – ৳${max}`;
        }
        if (min) {
            return `From ৳${min}`;
        }
        if (max) {
            return `Up to ৳${max}`;
        }
        return null;
    }, [localFilters]);

    const applyPreset = (preset) => {
        setLocalFilters((previous) => ({
            ...previous,
            min_price: preset.min,
            max_price: preset.max,
        }));
    };

    const clearPriceFilters = () => {
        setLocalFilters((previous) => ({
            ...previous,
            min_price: '',
            max_price: '',
        }));
    };

    const isPresetActive = (preset) =>
        (localFilters.min_price ?? '') === preset.min && (localFilters.max_price ?? '') === preset.max;

    const filterContent = (
        <div className="space-y-3">
            <div>
                <div className="mb-1.5 flex items-center justify-between gap-2">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-store-accent">Price</p>
                    {hasPriceActive && (
                        <button
                            type="button"
                            onClick={clearPriceFilters}
                            className="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[10px] font-medium text-store-muted ring-1 ring-gray-200 transition-colors hover:text-store-accent hover:ring-store-accent/40"
                        >
                            <RotateCcw className="size-2.5" aria-hidden />
                            Reset
                        </button>
                    )}
                </div>

                {activeLabel && (
                    <span className="mb-1.5 inline-block rounded-full bg-store-accent/10 px-2 py-0.5 text-[10px] font-semibold text-store-accent">
                        {activeLabel}
                    </span>
                )}

                <div className="flex items-center gap-1">
                    <div className="relative min-w-0 flex-1">
                        <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-store-muted">
                            ৳
                        </span>
                        <input
                            type="number"
                            min="0"
                            value={localFilters.min_price ?? ''}
                            onChange={(e) => setLocalFilters((previous) => ({ ...previous, min_price: e.target.value }))}
                            className="h-7 w-full rounded-full border-0 bg-store-surface py-0 pl-5 pr-2 text-xs text-store-primary ring-1 ring-gray-200 focus:ring-2 focus:ring-store-accent/30 focus:outline-none"
                            placeholder="Min"
                            aria-label="Minimum price"
                        />
                    </div>
                    <span className="shrink-0 text-[10px] text-gray-300">—</span>
                    <div className="relative min-w-0 flex-1">
                        <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-store-muted">
                            ৳
                        </span>
                        <input
                            type="number"
                            min="0"
                            value={localFilters.max_price ?? ''}
                            onChange={(e) => setLocalFilters((previous) => ({ ...previous, max_price: e.target.value }))}
                            className="h-7 w-full rounded-full border-0 bg-store-surface py-0 pl-5 pr-2 text-xs text-store-primary ring-1 ring-gray-200 focus:ring-2 focus:ring-store-accent/30 focus:outline-none"
                            placeholder="Max"
                            aria-label="Maximum price"
                        />
                    </div>
                </div>

                <div className="mt-1.5 grid grid-cols-4 gap-1">
                    {pricePresets.map((preset) => (
                        <button
                            key={preset.label}
                            type="button"
                            onClick={() => applyPreset(preset)}
                            className={`rounded-full py-1 text-[10px] font-semibold transition-all ${
                                isPresetActive(preset)
                                    ? 'bg-store-primary text-white'
                                    : 'bg-store-surface text-store-primary ring-1 ring-gray-200 hover:ring-store-accent/50'
                            }`}
                        >
                            {preset.label}
                        </button>
                    ))}
                </div>
            </div>

            <BrowseSections
                currentCategorySlug={currentCategorySlug}
                currentBrandSlug={currentBrandSlug}
                currentTagName={currentTagName}
            />
        </div>
    );

    const sidebarPanel = (
        <div className="rounded-xl bg-white p-3 ring-1 ring-gray-200">{filterContent}</div>
    );

    return (
        <>
            <aside className="hidden w-44 shrink-0 sm:w-52 lg:block">
                <div className="sticky top-18">{sidebarPanel}</div>
            </aside>

            <Dialog open={mobileOpen} onClose={onMobileClose} className="relative z-50 lg:hidden">
                <div className="fixed inset-0 bg-black/40" aria-hidden="true" />
                <div className="fixed inset-x-0 bottom-0">
                    <DialogPanel className="rounded-t-3xl bg-white px-4 pb-4 pt-3 shadow-2xl">
                        <div className="mb-3 flex items-center justify-between">
                            <p className="text-xs font-bold text-store-primary">Refine results</p>
                            <button
                                type="button"
                                onClick={onMobileClose}
                                className="flex size-6 items-center justify-center rounded-full bg-gray-100 text-store-muted"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                        <div className="rounded-2xl bg-store-surface p-3">{filterContent}</div>
                        <button
                            type="button"
                            onClick={onMobileClose}
                            className="mt-3 w-full rounded-full bg-store-primary py-2 text-xs font-semibold text-white"
                        >
                            Done
                        </button>
                    </DialogPanel>
                </div>
            </Dialog>
        </>
    );
}
