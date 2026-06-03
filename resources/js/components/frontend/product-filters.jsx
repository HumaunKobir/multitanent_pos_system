import { router } from '@inertiajs/react';
import { Dialog, DialogPanel } from '@headlessui/react';
import { SlidersHorizontal, X } from 'lucide-react';
import { useState } from 'react';
import { StoreButton } from '@/components/frontend/store-button';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';

export function ProductFilters({ filters, allColors, allSizes, baseUrl, mobileOpen, onMobileClose }) {
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

    const clearFilters = () => setLocalFilters({});

    const filterContent = (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label className="mb-1 block text-xs font-medium text-store-muted">Min price</label>
                    <input
                        type="number"
                        value={localFilters.min_price ?? ''}
                        onChange={(e) => setLocalFilters((p) => ({ ...p, min_price: e.target.value }))}
                        className="w-full rounded-md border border-gray-200 px-2 py-1.5 text-sm focus:border-store-accent focus:outline-none"
                        placeholder="৳"
                    />
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-store-muted">Max price</label>
                    <input
                        type="number"
                        value={localFilters.max_price ?? ''}
                        onChange={(e) => setLocalFilters((p) => ({ ...p, max_price: e.target.value }))}
                        className="w-full rounded-md border border-gray-200 px-2 py-1.5 text-sm focus:border-store-accent focus:outline-none"
                        placeholder="৳"
                    />
                </div>
            </div>

            {allColors?.length > 0 && (
                <div>
                    <label className="mb-1 block text-xs font-medium text-store-muted">Color</label>
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
                                    className={`rounded-md border px-2 py-0.5 text-xs transition-colors ${
                                        selected
                                            ? 'border-store-accent bg-store-accent text-white'
                                            : 'border-gray-200 hover:border-store-accent'
                                    }`}
                                >
                                    {color}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {allSizes?.length > 0 && (
                <div>
                    <label className="mb-1 block text-xs font-medium text-store-muted">Size</label>
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
                                    className={`rounded-md border px-2 py-0.5 text-xs transition-colors ${
                                        selected
                                            ? 'border-store-accent bg-store-accent text-white'
                                            : 'border-gray-200 hover:border-store-accent'
                                    }`}
                                >
                                    {size}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            <StoreButton variant="outline" onClick={clearFilters} className="w-full sm:w-auto">
                <X className="size-3" /> Clear
            </StoreButton>
        </div>
    );

    return (
        <>
            <aside className="hidden w-56 shrink-0 lg:block">
                <div className="sticky top-20 rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <h3 className="mb-3 flex items-center gap-1.5 text-sm font-semibold text-store-primary">
                        <SlidersHorizontal className="size-4" /> Filters
                    </h3>
                    {filterContent}
                </div>
            </aside>

            <Dialog open={mobileOpen} onClose={onMobileClose} className="relative z-50 lg:hidden">
                <div className="fixed inset-0 bg-black/40" aria-hidden="true" />
                <div className="fixed inset-x-0 bottom-0">
                    <DialogPanel className="max-h-[80vh] overflow-y-auto rounded-t-xl bg-white p-5">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="font-semibold text-store-primary">Filters & Sort</h3>
                            <button onClick={onMobileClose}>
                                <X className="size-5" />
                            </button>
                        </div>
                        {filterContent}
                        <StoreButton className="mt-4 w-full" onClick={onMobileClose}>
                            Apply
                        </StoreButton>
                    </DialogPanel>
                </div>
            </Dialog>
        </>
    );
}
