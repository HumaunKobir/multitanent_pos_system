import { Link } from '@inertiajs/react';
import { Layers, Play, ShoppingBag, Tag } from 'lucide-react';
import { useState } from 'react';
import { VariantModal } from '@/components/frontend/variant-modal';
import { ProductReviewBadge } from '@/components/frontend/product-review-badge';
import { useAddToCart } from '@/hooks/use-add-to-cart';

function getDiscountMeta(product) {
    if (product.has_variations || !product.discount_price || product.discount_price <= 0) {
        return null;
    }

    const original = Number(product.sale_price);
    const current = Number(product.discount_price);

    if (!original || original <= current) {
        return null;
    }

    const saved = original - current;
    const percent = Math.round((saved / original) * 100);

    return { original, current, saved, percent };
}

function formatPrice(value) {
    return Number(value).toLocaleString('en-BD', { maximumFractionDigits: 0 });
}

function getPriceDisplay(product, discount) {
    if (product.has_variations && product.price_min != null) {
        const min = formatPrice(product.price_min);
        const hasRange =
            product.price_max != null && Number(product.price_max) !== Number(product.price_min);

        return {
            primary: hasRange ? `From ৳${min}` : `৳${min}`,
            secondary: hasRange ? `Up to ৳${formatPrice(product.price_max)}` : null,
            isVariant: true,
        };
    }

    if (discount) {
        return {
            primary: `৳${formatPrice(discount.current)}`,
            secondary: `৳${formatPrice(discount.original)}`,
            isVariant: false,
            strikethrough: true,
        };
    }

    return {
        primary: `৳${formatPrice(product.price)}`,
        secondary: null,
        isVariant: false,
    };
}

export function ProductCard({ product }) {
    const discount = getDiscountMeta(product);
    const priceDisplay = getPriceDisplay(product, discount);
    const [adding, setAdding] = useState(false);
    const [variantOpen, setVariantOpen] = useState(false);
    const { addToCart } = useAddToCart();
    const productUrl = `/products/${product.slug}`;

    const handleBagAction = async (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (product.has_variations) {
            setVariantOpen(true);
            return;
        }

        setAdding(true);
        try {
            await addToCart({ product_id: product.id, quantity: 1 });
        } finally {
            setAdding(false);
        }
    };

    return (
        <>
            <article className="group flex w-full max-w-full flex-col gap-1">
                <div className="relative overflow-hidden rounded-md bg-white ring-1 ring-gray-200/80 transition duration-300 group-hover:ring-store-accent/25 group-hover:shadow-sm">
                    <Link href={productUrl} className="block">
                        <div className="relative aspect-square overflow-hidden bg-store-surface">
                            {product.image ? (
                                <img
                                    src={product.image}
                                    alt={product.name}
                                    className="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03]"
                                    loading="lazy"
                                />
                            ) : (
                                <div className="flex size-full items-center justify-center">
                                    <ShoppingBag className="size-4 text-gray-300" strokeWidth={1.25} aria-hidden />
                                </div>
                            )}

                            {discount && (
                                <span className="absolute left-1 top-1 z-10 inline-flex items-center gap-0.5 rounded bg-store-accent px-1 py-px text-[8px] font-bold tracking-wide text-white">
                                    <Tag className="size-2" aria-hidden />
                                    -{discount.percent}%
                                </span>
                            )}

                            {product.youtube_link && (
                                <div className="absolute right-1 top-1 z-10 flex size-4 items-center justify-center rounded-full bg-white/95 shadow-sm">
                                    <Play className="size-2 fill-store-primary text-store-primary" aria-hidden />
                                </div>
                            )}
                        </div>
                    </Link>

                    <button
                        type="button"
                        onClick={handleBagAction}
                        disabled={adding}
                        aria-label={product.has_variations ? 'Choose options' : 'Add to bag'}
                        className="absolute bottom-1 right-1 z-20 flex size-6 items-center justify-center rounded bg-store-primary text-white opacity-100 shadow-sm transition duration-300 hover:bg-store-accent disabled:opacity-60 sm:translate-y-0.5 sm:opacity-0 sm:group-hover:translate-y-0 sm:group-hover:opacity-100"
                    >
                        {product.has_variations ? (
                            <Layers className="size-3" aria-hidden />
                        ) : (
                            <ShoppingBag className="size-3" aria-hidden />
                        )}
                    </button>
                </div>

                <Link href={productUrl} className="block min-w-0 space-y-0.5 px-0.5">
                    <p className="truncate text-[10px] font-medium leading-tight text-store-primary transition-colors group-hover:text-store-accent sm:text-[11px]">
                        {product.name}
                    </p>
                    <ProductReviewBadge summary={product.review_summary} />
                    <div className="flex flex-wrap items-baseline gap-x-1">
                        <span
                            className={`text-[11px] font-bold tabular-nums sm:text-xs ${
                                priceDisplay.isVariant || discount ? 'text-store-accent' : 'text-store-primary'
                            }`}
                        >
                            {priceDisplay.primary}
                        </span>
                        {priceDisplay.strikethrough && priceDisplay.secondary && (
                            <span className="text-[9px] tabular-nums text-store-muted line-through">
                                {priceDisplay.secondary}
                            </span>
                        )}
                        {!priceDisplay.strikethrough && priceDisplay.secondary && (
                            <span className="text-[9px] text-store-muted">{priceDisplay.secondary}</span>
                        )}
                    </div>
                </Link>
            </article>

            <VariantModal product={product} open={variantOpen} onClose={() => setVariantOpen(false)} />
        </>
    );
}
