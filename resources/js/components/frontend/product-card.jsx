import { Link } from '@inertiajs/react';
import { Eye, Layers, Play, ShoppingBag, Tag } from 'lucide-react';
import { useState } from 'react';
import { VariantModal } from '@/components/frontend/variant-modal';
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
    const optionCount = product.variations_count ?? product.variations?.length ?? 0;

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
            <article className="group flex flex-col">
                <div className="relative overflow-hidden rounded-2xl bg-white ring-1 ring-gray-100 transition-all duration-300 group-hover:-translate-y-1 group-hover:shadow-xl group-hover:ring-store-accent/30">
                    <Link href={productUrl} className="block">
                        <div className="relative aspect-5/6 overflow-hidden bg-gray-50">
                            {product.image ? (
                                <img
                                    src={product.image}
                                    alt={product.name}
                                    className="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                                />
                            ) : (
                                <div className="flex size-full items-center justify-center bg-gray-50">
                                    <ShoppingBag className="size-7 text-gray-200" strokeWidth={1.25} aria-hidden />
                                </div>
                            )}

                            <div
                                className="absolute inset-0 bg-linear-to-t from-store-primary/70 via-store-primary/10 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                aria-hidden
                            />

                            {discount && (
                                <span className="absolute left-2 top-2 z-10 inline-flex items-center gap-0.5 rounded-full bg-store-accent px-2 py-0.5 text-[10px] font-bold text-white shadow-sm">
                                    <Tag className="size-2.5" aria-hidden />
                                    -{discount.percent}%
                                </span>
                            )}

                            {product.has_variations && (
                                <span className="absolute left-2 top-2 z-10 inline-flex items-center gap-0.5 rounded-full bg-store-primary px-2 py-0.5 text-[10px] font-bold text-white shadow-sm">
                                    <Layers className="size-2.5" aria-hidden />
                                    {optionCount} {optionCount === 1 ? 'option' : 'options'}
                                </span>
                            )}

                            {product.youtube_link && (
                                <div className="absolute right-2 top-2 z-10 flex size-6 items-center justify-center rounded-full bg-white/95 shadow-sm">
                                    <Play className="size-3 fill-store-primary text-store-primary" aria-hidden />
                                </div>
                            )}
                        </div>
                    </Link>

                    <div className="pointer-events-none absolute inset-0 z-20 flex flex-col items-center justify-center gap-2 opacity-0 transition-all duration-300 group-hover:opacity-100">
                        <div className="pointer-events-auto flex translate-y-3 flex-col gap-1.5 transition-transform duration-300 group-hover:translate-y-0">
                            <Link
                                href={productUrl}
                                className="inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3.5 py-1.5 text-[11px] font-semibold text-store-primary shadow-lg backdrop-blur-sm transition-transform hover:scale-105"
                            >
                                <Eye className="size-3.5" aria-hidden />
                                Quick View
                            </Link>
                            <button
                                type="button"
                                onClick={handleBagAction}
                                disabled={adding}
                                className="inline-flex items-center gap-1.5 rounded-full bg-store-accent px-3.5 py-1.5 text-[11px] font-semibold text-white shadow-lg transition-transform hover:scale-105 disabled:opacity-60"
                            >
                                {product.has_variations ? (
                                    <Layers className="size-3.5" aria-hidden />
                                ) : (
                                    <ShoppingBag className="size-3.5" aria-hidden />
                                )}
                                {adding ? 'Adding…' : product.has_variations ? 'Choose Options' : 'Add to Bag'}
                            </button>
                        </div>
                    </div>

                    {(discount || product.has_variations) && (
                        <div className="pointer-events-none absolute inset-x-0 bottom-0 z-20 translate-y-full px-2 pb-2 opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                            <div className="rounded-xl bg-white/95 px-2.5 py-2 text-center shadow-lg backdrop-blur-sm">
                                {product.has_variations ? (
                                    <>
                                        <p className="text-sm font-bold text-store-accent">{priceDisplay.primary}</p>
                                        {priceDisplay.secondary && (
                                            <p className="mt-0.5 text-[10px] text-store-muted">{priceDisplay.secondary}</p>
                                        )}
                                        <p className="mt-0.5 text-[10px] font-semibold text-store-primary">
                                            {optionCount} variants available
                                        </p>
                                    </>
                                ) : (
                                    <>
                                        <div className="flex items-center justify-center gap-1.5">
                                            <span className="text-sm font-bold text-store-accent">
                                                ৳{formatPrice(discount.current)}
                                            </span>
                                            <span className="text-[11px] text-gray-400 line-through">
                                                ৳{formatPrice(discount.original)}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-[10px] font-semibold text-emerald-600">
                                            You save ৳{formatPrice(discount.saved)}
                                        </p>
                                    </>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                <Link href={productUrl} className="mt-1.5 block px-0.5">
                    <p className="truncate text-xs font-medium text-store-primary transition-colors group-hover:text-store-accent">
                        {product.name}
                    </p>
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                        <span
                            className={`text-xs font-bold ${priceDisplay.isVariant || discount ? 'text-store-accent' : 'text-store-primary'}`}
                        >
                            {priceDisplay.primary}
                        </span>
                        {priceDisplay.strikethrough && priceDisplay.secondary && (
                            <span className="text-[11px] text-gray-400 line-through">{priceDisplay.secondary}</span>
                        )}
                        {product.has_variations && (
                            <span className="text-[10px] text-store-muted">· {optionCount} options</span>
                        )}
                    </div>
                </Link>
            </article>

            <VariantModal product={product} open={variantOpen} onClose={() => setVariantOpen(false)} />
        </>
    );
}
