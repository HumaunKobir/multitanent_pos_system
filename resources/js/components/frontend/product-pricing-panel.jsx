import { Check, Layers, ShoppingCart, Sparkles } from 'lucide-react';
import { DynamicVariantPicker } from '@/components/frontend/dynamic-variant-picker';
import { StoreButton } from '@/components/frontend/store-button';
import { getVariationLabel } from '@/lib/variation-utils';
import { cn } from '@/lib/utils';

function formatPrice(value) {
    return Number(value).toLocaleString('en-BD', { maximumFractionDigits: 0 });
}

function getPricingMeta({ product, selectedVariation, tailorService }) {
    const hasVariations = (product.variations?.length ?? 0) > 0;
    const priceMin = product.price_min ?? null;
    const priceMax = product.price_max ?? null;
    const hasPriceRange =
        hasVariations && priceMin != null && priceMax != null && Number(priceMin) !== Number(priceMax);

    const basePrice = selectedVariation
        ? selectedVariation.price
        : hasVariations && priceMin != null
          ? priceMin
          : product.discount_price > 0
            ? product.discount_price
            : product.sale_price;

    const hasDiscount =
        !hasVariations &&
        product.discount_price > 0 &&
        product.sale_price > product.discount_price;
    const saved = hasDiscount ? product.sale_price - product.discount_price : 0;
    const discountPercent = hasDiscount ? Math.round((saved / product.sale_price) * 100) : 0;

    const tailorAddon =
        product.tailor_option === 'yes' && tailorService === 'tailor' ? product.tailor_price || 0 : 0;

    const effectivePrice = (selectedVariation ? selectedVariation.price : basePrice) + tailorAddon;
    const selectedOutOfStock = selectedVariation != null && (selectedVariation.stock ?? 0) <= 0;

    return {
        hasVariations,
        hasPriceRange,
        priceMin,
        priceMax,
        basePrice,
        effectivePrice,
        hasDiscount,
        saved,
        discountPercent,
        tailorAddon,
        selectedOutOfStock,
        allVariantsOutOfStock:
            hasVariations && product.variations.every((variation) => variation.stock <= 0),
    };
}

function PriceDisplay({ meta, selectedVariation, tailorAddon, product }) {
    const {
        hasVariations,
        hasPriceRange,
        priceMin,
        priceMax,
        effectivePrice,
        hasDiscount,
        saved,
        discountPercent,
        selectedOutOfStock,
    } = meta;

    if (hasVariations && !selectedVariation) {
        return (
            <div>
                <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/60">
                    {hasPriceRange ? 'Price range' : 'Starting from'}
                </p>
                <p className="mt-1 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                    {hasPriceRange ? (
                        <>
                            ৳{formatPrice(priceMin)}
                            <span className="mx-1.5 text-xl font-medium text-white/50">–</span>
                            ৳{formatPrice(priceMax)}
                        </>
                    ) : (
                        <>৳{formatPrice(priceMin ?? effectivePrice)}</>
                    )}
                </p>
                <p className="mt-1.5 text-[11px] text-white/70">Select options below to see your price</p>
            </div>
        );
    }

    if (hasVariations && selectedVariation) {
        return (
            <div>
                <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/60">Your price</p>
                <p className="mt-1 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                    ৳{formatPrice(effectivePrice)}
                </p>
                <p className="mt-1.5 inline-flex flex-wrap items-center gap-1 rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium text-white/90">
                    <Check className="size-3 shrink-0" aria-hidden />
                    {getVariationLabel(selectedVariation)}
                    {selectedOutOfStock ? (
                        <span className="text-red-200">· Out of stock</span>
                    ) : selectedVariation.stock <= 5 ? (
                        <span className="text-amber-200">· Only {selectedVariation.stock} left</span>
                    ) : null}
                </p>
                {tailorAddon > 0 && (
                    <p className="mt-1 text-[11px] text-white/60">
                        Includes ৳{formatPrice(tailorAddon)} tailor service
                    </p>
                )}
            </div>
        );
    }

    return (
        <div>
            <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/60">Price</p>
            <div className="mt-1 flex flex-wrap items-end gap-x-2 gap-y-1">
                <p className="text-3xl font-bold tracking-tight text-white sm:text-4xl">৳{formatPrice(effectivePrice)}</p>
                {hasDiscount && (
                    <p className="pb-1 text-base text-white/45 line-through">৳{formatPrice(product.sale_price)}</p>
                )}
            </div>
            {hasDiscount && (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <span className="rounded-md bg-store-accent px-2 py-0.5 text-[11px] font-bold text-white">
                        {discountPercent}% OFF
                    </span>
                    <span className="text-[11px] font-semibold text-emerald-300">You save ৳{formatPrice(saved)}</span>
                </div>
            )}
            {tailorAddon > 0 && (
                <p className="mt-1.5 text-[11px] text-white/60">
                    Base ৳{formatPrice(meta.basePrice)} + Tailor ৳{formatPrice(tailorAddon)}
                </p>
            )}
        </div>
    );
}

export function ProductPricingPanel({
    product,
    selectedVariation,
    tailorService,
    onTailorChange,
    onVariationSelect,
    needsVariation,
    adding,
    onAddToCart,
}) {
    const meta = getPricingMeta({ product, selectedVariation, tailorService });
    const { hasVariations, effectivePrice, allVariantsOutOfStock, tailorAddon, selectedOutOfStock } = meta;

    const ctaDisabled = adding || needsVariation || allVariantsOutOfStock || selectedOutOfStock;

    return (
        <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
            <div className="relative overflow-hidden bg-store-primary px-4 py-4 sm:px-5 sm:py-5">
                <div className="absolute inset-0 store-gradient opacity-20" aria-hidden />
                <div className="absolute -right-8 -top-8 size-32 rounded-full bg-white/5" aria-hidden />
                <div className="relative">
                    <PriceDisplay
                        meta={meta}
                        selectedVariation={selectedVariation}
                        tailorAddon={tailorAddon}
                        product={product}
                    />
                </div>
            </div>

            <div className="space-y-4 p-3 sm:p-4">
                {hasVariations && (
                    <div className="rounded-xl bg-store-surface/40 p-3 ring-1 ring-gray-100">
                        <DynamicVariantPicker
                            variations={product.variations}
                            selectedVariation={selectedVariation}
                            onVariationChange={onVariationSelect}
                        />

                        {allVariantsOutOfStock && (
                            <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[11px] font-medium text-red-600 ring-1 ring-red-100">
                                All options are currently out of stock.
                            </p>
                        )}
                    </div>
                )}

                {product.tailor_option === 'yes' && (
                    <div>
                        <p className="mb-2 flex items-center gap-1.5 text-xs font-bold text-store-primary">
                            <Sparkles className="size-3.5 text-store-accent" aria-hidden />
                            Stitching service
                        </p>
                        <div className="grid grid-cols-2 gap-2">
                            {[
                                { id: 'standard', label: 'Ready to wear', sub: 'Standard fit', price: null },
                                {
                                    id: 'tailor',
                                    label: 'Custom tailor',
                                    sub: 'Made to measure',
                                    price: product.tailor_price,
                                },
                            ].map((opt) => (
                                <button
                                    key={opt.id}
                                    type="button"
                                    onClick={() => onTailorChange(opt.id)}
                                    className={cn(
                                        'rounded-xl border p-2.5 text-left transition-all',
                                        tailorService === opt.id
                                            ? 'border-store-primary bg-store-primary text-white'
                                            : 'border-gray-200 bg-white hover:border-store-accent/40',
                                    )}
                                >
                                    <p className="text-[11px] font-bold">{opt.label}</p>
                                    <p
                                        className={cn(
                                            'mt-0.5 text-[10px]',
                                            tailorService === opt.id ? 'text-white/70' : 'text-store-muted',
                                        )}
                                    >
                                        {opt.sub}
                                        {opt.price != null && (
                                            <span className="ml-1 font-semibold">+৳{formatPrice(opt.price)}</span>
                                        )}
                                    </p>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                <StoreButton
                    className="hidden w-full rounded-xl py-3 text-sm font-bold lg:flex"
                    onClick={onAddToCart}
                    disabled={ctaDisabled}
                >
                    <ShoppingCart className="size-4" />
                    {adding
                        ? 'Adding to cart...'
                        : allVariantsOutOfStock || selectedOutOfStock
                          ? 'Out of stock'
                          : needsVariation
                            ? 'Select all options'
                            : `Add to Cart — ৳${formatPrice(effectivePrice)}`}
                </StoreButton>
            </div>
        </div>
    );
}

export { formatPrice, getPricingMeta };
