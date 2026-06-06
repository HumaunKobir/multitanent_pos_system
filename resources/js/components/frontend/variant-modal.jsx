import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DynamicVariantPicker } from '@/components/frontend/dynamic-variant-picker';
import { StoreButton } from '@/components/frontend/store-button';
import { useAddToCart } from '@/hooks/use-add-to-cart';

function formatPrice(value) {
    return Number(value).toLocaleString('en-BD', { maximumFractionDigits: 0 });
}

export function VariantModal({ product, open, onClose }) {
    const [selectedVariation, setSelectedVariation] = useState(null);
    const [loading, setLoading] = useState(false);
    const { addToCart } = useAddToCart();

    useEffect(() => {
        if (!open) {
            setSelectedVariation(null);
        }
    }, [open, product]);

    if (!product) {
        return null;
    }

    const hasVariations = product.variations?.length > 0;
    const effectivePrice = selectedVariation
        ? selectedVariation.price
        : product.price_min ?? product.price;

    const handleAdd = async () => {
        if (hasVariations && !selectedVariation) {
            return;
        }

        setLoading(true);
        try {
            await addToCart({
                product_id: product.id,
                quantity: 1,
                variation_id: selectedVariation?.id ?? null,
            });
            onClose();
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onClose={onClose} className="relative z-70">
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" aria-hidden="true" />
            <div className="fixed inset-0 flex items-end justify-center p-3 sm:items-center sm:p-4">
                <DialogPanel className="w-full max-w-sm overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <div className="flex items-start gap-3 border-b border-gray-100 p-4">
                        {product.image && (
                            <img src={product.image} alt="" className="size-14 shrink-0 rounded-xl object-cover ring-1 ring-gray-100" />
                        )}
                        <div className="min-w-0 flex-1">
                            <DialogTitle className="line-clamp-2 text-sm font-semibold text-store-primary">
                                {product.name}
                            </DialogTitle>
                            <p className="mt-1 text-base font-bold text-store-accent">
                                {selectedVariation ? (
                                    <>৳{formatPrice(effectivePrice)}</>
                                ) : product.price_min != null && product.price_max != null && product.price_min !== product.price_max ? (
                                    <>From ৳{formatPrice(product.price_min)}</>
                                ) : (
                                    <>৳{formatPrice(effectivePrice)}</>
                                )}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-store-muted"
                        >
                            <X className="size-4" />
                        </button>
                    </div>

                    {hasVariations && (
                        <div className="p-4">
                            <DynamicVariantPicker
                                variations={product.variations}
                                selectedVariation={selectedVariation}
                                onVariationChange={setSelectedVariation}
                            />
                        </div>
                    )}

                    <div className="space-y-2 border-t border-gray-100 p-4">
                        <StoreButton
                            className="w-full rounded-full py-2.5 text-xs"
                            onClick={handleAdd}
                            disabled={loading || (hasVariations && (!selectedVariation || selectedVariation.stock <= 0))}
                        >
                            {loading ? 'Adding…' : hasVariations ? 'Add selected to bag' : 'Add to bag'}
                        </StoreButton>
                        <Link
                            href={`/products/${product.slug}`}
                            onClick={onClose}
                            className="block text-center text-[11px] font-medium text-store-muted hover:text-store-accent"
                        >
                            View full details
                        </Link>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
