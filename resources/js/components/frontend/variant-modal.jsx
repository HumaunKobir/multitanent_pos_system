import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { motion } from 'framer-motion';
import { X } from 'lucide-react';
import { useState } from 'react';
import { StoreButton } from '@/components/frontend/store-button';
import { useAddToCart } from '@/hooks/use-add-to-cart';

export function VariantModal({ product, open, onClose }) {
    const [selectedVariation, setSelectedVariation] = useState(null);
    const [loading, setLoading] = useState(false);
    const { addToCart } = useAddToCart();

    if (!product) {
        return null;
    }

    const effectivePrice = selectedVariation
        ? selectedVariation.price
        : product.discount_price > 0
          ? product.discount_price
          : product.sale_price;

    const handleAdd = async () => {
        if (product.variations?.length && !selectedVariation) {
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
        <Dialog open={open} onClose={onClose} className="relative z-[70]">
            <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="fixed inset-0 bg-black/50 backdrop-blur-sm"
                aria-hidden="true"
            />
            <div className="fixed inset-0 flex items-end justify-center p-4 sm:items-center">
                <DialogPanel
                    as={motion.div}
                    initial={{ opacity: 0, y: 40 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="w-full max-w-md rounded-t-xl bg-white p-5 shadow-xl sm:rounded-xl"
                >
                    <div className="mb-4 flex items-start justify-between gap-3">
                        <div className="flex gap-3">
                            {product.image && (
                                <img
                                    src={product.image}
                                    alt={product.name}
                                    className="size-16 rounded-md object-cover"
                                />
                            )}
                            <div>
                                <DialogTitle className="text-sm font-semibold text-store-primary">
                                    {product.name}
                                </DialogTitle>
                                <p className="mt-1 text-lg font-bold text-store-accent">৳{effectivePrice}</p>
                            </div>
                        </div>
                        <button onClick={onClose} className="rounded-md p-1 text-gray-400 hover:bg-store-surface">
                            <X className="size-5" />
                        </button>
                    </div>

                    {product.variations?.length > 0 && (
                        <div className="mb-4">
                            <p className="mb-2 text-xs font-medium text-store-muted">Select variation</p>
                            <div className="flex flex-wrap gap-2">
                                {product.variations.map((v) => (
                                    <button
                                        key={v.id}
                                        onClick={() => setSelectedVariation(v)}
                                        disabled={v.stock <= 0}
                                        className={`rounded-md border px-3 py-1.5 text-xs transition-colors ${
                                            selectedVariation?.id === v.id
                                                ? 'border-store-accent bg-store-accent text-white'
                                                : 'border-gray-200 hover:border-store-accent'
                                        } ${v.stock <= 0 ? 'cursor-not-allowed opacity-40' : ''}`}
                                    >
                                        {Object.values(v.variation_data || {}).join(' / ')}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    <StoreButton className="w-full" onClick={handleAdd} disabled={loading}>
                        {loading ? 'Adding...' : 'Add to Cart'}
                    </StoreButton>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
