import { Link } from '@inertiajs/react';
import { BadgeCheck, ShieldCheck, ShoppingCart, Sparkles, Truck, Zap } from 'lucide-react';
import { DynamicVariantPicker } from '@/components/frontend/dynamic-variant-picker';
import { ProductReviewBadge } from '@/components/frontend/product-review-badge';
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

function PriceHeader({ meta, selectedVariation, tailorAddon, product }) {
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

    let priceLabel = 'Price';
    let priceMain = `৳${formatPrice(effectivePrice)}`;
    let priceHint = null;
    let showFromPrefix = false;

    if (hasVariations && !selectedVariation) {
        priceLabel = hasPriceRange ? 'Range' : 'From';
        showFromPrefix = !hasPriceRange;
        priceMain = hasPriceRange
            ? `৳${formatPrice(priceMin)} – ৳${formatPrice(priceMax)}`
            : `৳${formatPrice(priceMin ?? effectivePrice)}`;
        priceHint = 'Pick size & color below';
    } else if (hasVariations && selectedVariation) {
        priceLabel = 'Selected';
        priceMain = `৳${formatPrice(effectivePrice)}`;
        priceHint = getVariationLabel(selectedVariation);
    } else if (hasDiscount) {
        priceLabel = 'Offer price';
    }

    const stockBadge =
        selectedVariation && selectedOutOfStock
            ? { label: 'Out of stock', className: 'bg-red-50 text-red-600 ring-red-100' }
            : selectedVariation && selectedVariation.stock <= 5
              ? { label: `${selectedVariation.stock} left`, className: 'bg-amber-50 text-amber-700 ring-amber-100' }
              : null;

    return (
        <div className="relative overflow-hidden bg-linear-to-br from-store-surface/90 via-white to-white px-4 py-3">
            <div className="absolute inset-x-0 top-0 h-0.5 bg-store-accent/80" aria-hidden />

            <div className="flex items-start justify-between gap-2.5">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-md bg-white px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-store-muted ring-1 ring-gray-200">
                            {priceLabel}
                        </span>
                        {hasDiscount && !hasVariations && (
                            <span className="rounded-md bg-store-accent px-2 py-0.5 text-[10px] font-bold text-white">
                                {discountPercent}% off
                            </span>
                        )}
                        {stockBadge && (
                            <span
                                className={cn(
                                    'rounded-md px-2 py-0.5 text-[10px] font-semibold ring-1',
                                    stockBadge.className,
                                )}
                            >
                                {stockBadge.label}
                            </span>
                        )}
                    </div>

                    <div className="mt-1.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        {showFromPrefix && (
                            <span className="text-[11px] font-medium text-store-muted">from</span>
                        )}
                        <p className="text-2xl font-bold leading-none tracking-tight text-store-accent">{priceMain}</p>
                        {hasDiscount && !hasVariations && (
                            <p className="text-sm text-gray-400 line-through">৳{formatPrice(product.sale_price)}</p>
                        )}
                    </div>

                    {hasDiscount && !hasVariations && (
                        <p className="mt-1 text-[10px] font-medium text-emerald-600">
                            You save ৳{formatPrice(saved)}
                        </p>
                    )}

                    {priceHint && !(hasVariations && selectedVariation) && (
                        <p className="mt-1 text-[11px] text-store-muted">{priceHint}</p>
                    )}

                    {tailorAddon > 0 && (
                        <p className="mt-1 text-[10px] text-store-muted">+ ৳{formatPrice(tailorAddon)} tailoring</p>
                    )}
                </div>

                {priceHint && hasVariations && selectedVariation && (
                    <span className="shrink-0 rounded-lg bg-store-accent/10 px-3 py-1.5 text-[10px] font-semibold leading-tight text-store-accent ring-1 ring-store-accent/25">
                        {priceHint}
                    </span>
                )}
            </div>
        </div>
    );
}

function ProductPurchaseDetails({ product }) {
    const metaItems = [
        product.brand ? { label: 'Brand', value: product.brand } : null,
        product.category
            ? {
                  label: 'Category',
                  value: product.category,
                  href: product.category_slug ? `/category/${product.category_slug}/products` : null,
              }
            : null,
        product.code ? { label: 'SKU', value: product.code } : null,
    ].filter(Boolean);

    const assurances = [
        { icon: Truck, title: 'Cash on delivery', desc: 'Inside & outside Dhaka' },
        { icon: ShieldCheck, title: 'Secure checkout', desc: 'SSLCommerz · COD' },
        { icon: BadgeCheck, title: 'Quality assured', desc: '100% authentic products' },
    ];

    return (
        <div className="border-b border-gray-200 bg-white">
            {product.review_summary?.count > 0 && (
                <div className="border-b border-gray-200 bg-amber-50/40 px-4 py-2">
                    <ProductReviewBadge summary={product.review_summary} className="rounded-md ring-amber-200/80" />
                </div>
            )}

            {metaItems.length > 0 && (
                <div className="relative border-b border-gray-200">
                    <div className="absolute inset-y-0 left-0 w-0.5 bg-store-accent" aria-hidden />
                    <dl
                        className={cn(
                            'grid divide-x divide-gray-200',
                            metaItems.length === 1 ? 'grid-cols-1' : metaItems.length === 2 ? 'grid-cols-2' : 'grid-cols-2',
                        )}
                    >
                        {metaItems.map((item, index) => (
                            <div
                                key={item.label}
                                className={cn(
                                    'px-3 py-2.5',
                                    metaItems.length === 3 && index === 2 && 'col-span-2 border-t border-gray-200',
                                )}
                            >
                                <dt className="text-[9px] font-bold uppercase tracking-[0.14em] text-store-muted">
                                    {item.label}
                                </dt>
                                <dd className="mt-1 truncate text-[11px] font-semibold text-store-primary">
                                    {item.href ? (
                                        <Link href={item.href} className="transition-colors hover:text-store-accent">
                                            {item.value}
                                        </Link>
                                    ) : (
                                        item.value
                                    )}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
            )}

            <div className="grid grid-cols-3 divide-x divide-gray-200">
                {assurances.map(({ icon: Icon, title, desc }) => (
                    <div key={title} className="group px-2 py-3 text-center transition-colors hover:bg-store-surface/50">
                        <span className="mx-auto inline-flex size-8 items-center justify-center rounded-lg border border-store-accent/20 bg-store-accent/5">
                            <Icon className="size-3.5 text-store-accent" aria-hidden />
                        </span>
                        <p className="mt-2 text-[10px] font-bold leading-tight text-store-primary">{title}</p>
                        <p className="mt-0.5 text-[9px] leading-snug text-store-muted">{desc}</p>
                    </div>
                ))}
            </div>

            {product.delivery_info && (
                <div className="border-t border-gray-200 bg-emerald-50/50 px-4 py-2.5">
                    <p className="border-l-2 border-emerald-500 pl-2.5 text-[10px] leading-relaxed text-emerald-900">
                        {product.delivery_info.replace(/<[^>]+>/g, '')}
                    </p>
                </div>
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
    buyingNow,
    onAddToCart,
    onBuyNow,
}) {
    const meta = getPricingMeta({ product, selectedVariation, tailorService });
    const { hasVariations, allVariantsOutOfStock, tailorAddon, selectedOutOfStock } = meta;

    const actionDisabled = adding || buyingNow || needsVariation || allVariantsOutOfStock || selectedOutOfStock;

    const ctaLabel = adding
        ? 'Adding...'
        : allVariantsOutOfStock || selectedOutOfStock
          ? 'Out of stock'
          : needsVariation
            ? 'Select options'
            : 'Add to Bag';

    const buyNowLabel = buyingNow
        ? 'Checkout...'
        : allVariantsOutOfStock || selectedOutOfStock
          ? 'Out of stock'
          : needsVariation
            ? 'Select options'
            : 'Buy Now';

    return (
        <div className="overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200">
            <PriceHeader
                meta={meta}
                selectedVariation={selectedVariation}
                tailorAddon={tailorAddon}
                product={product}
            />

            {hasVariations && (
                <div className="border-b border-gray-100 px-4 py-3">
                    <DynamicVariantPicker
                        layout="details"
                        variations={product.variations}
                        selectedVariation={selectedVariation}
                        onVariationChange={onVariationSelect}
                    />

                    {allVariantsOutOfStock && (
                        <p className="mt-2 rounded-lg bg-red-50 px-2.5 py-1.5 text-[10px] font-medium text-red-600 ring-1 ring-red-100">
                            All options are currently out of stock.
                        </p>
                    )}
                </div>
            )}

            {product.tailor_option === 'yes' && (
                <div className="space-y-2 border-b border-gray-100 px-4 py-3">
                    <p className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-store-muted">
                        <Sparkles className="size-3 text-store-accent" aria-hidden />
                        Stitching
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
                                    'rounded-lg border px-2.5 py-2 text-left transition-all',
                                    tailorService === opt.id
                                        ? 'border-store-accent bg-store-accent/5 ring-1 ring-store-accent/20'
                                        : 'border-gray-200 bg-white hover:border-store-accent/30',
                                )}
                            >
                                <p className="text-[11px] font-bold text-store-primary">{opt.label}</p>
                                <p className="mt-0.5 text-[10px] text-store-muted">
                                    {opt.sub}
                                    {opt.price != null && (
                                        <span className="ml-0.5 font-semibold text-store-accent">
                                            +৳{formatPrice(opt.price)}
                                        </span>
                                    )}
                                </p>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <ProductPurchaseDetails product={product} />

            <div className="flex gap-1.5 px-4 py-2.5">
                <StoreButton
                    variant="accent"
                    className="hidden flex-1 gap-1.5 rounded-xl px-3 py-2 text-xs font-bold shadow-sm lg:inline-flex"
                    onClick={onAddToCart}
                    disabled={actionDisabled}
                >
                    <ShoppingCart className="size-3.5" />
                    {ctaLabel}
                </StoreButton>

                <StoreButton
                    variant="ghost"
                    className="hidden flex-1 gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 hover:text-white lg:inline-flex"
                    onClick={onBuyNow}
                    disabled={actionDisabled}
                >
                    <Zap className="size-3.5" />
                    {buyNowLabel}
                </StoreButton>
            </div>
        </div>
    );
}

export { formatPrice, getPricingMeta };
