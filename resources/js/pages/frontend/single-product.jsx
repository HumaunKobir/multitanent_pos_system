import { Head } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { useState } from 'react';
import { StoreButton } from '@/components/frontend/store-button';
import { useAddToCart } from '@/hooks/use-add-to-cart';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function SingleProduct({ product }) {
    const [selectedPhoto, setSelectedPhoto] = useState(0);
    const [selectedVariation, setSelectedVariation] = useState(null);
    const [tailorService, setTailorService] = useState('standard');
    const [adding, setAdding] = useState(false);
    const { addToCart } = useAddToCart();

    const photos = [product.image, ...(product.photos || [])].filter(Boolean);

    let effectivePrice = selectedVariation
        ? selectedVariation.price
        : product.discount_price > 0
          ? product.discount_price
          : product.sale_price;

    if (tailorService === 'tailor' && product.tailor_option === 'yes') {
        effectivePrice += product.tailor_price || 0;
    }

    const handleAddToCart = async () => {
        if (product.variations?.length && !selectedVariation) {
            return;
        }
        setAdding(true);
        try {
            await addToCart({
                product_id: product.id,
                quantity: 1,
                variation_id: selectedVariation?.id ?? null,
                tailor_service: product.tailor_option === 'yes' ? tailorService : null,
                tailor_price: product.tailor_option === 'yes' ? product.tailor_price : null,
            });
        } finally {
            setAdding(false);
        }
    };

    return (
        <FrontendLayout>
            <Head title={product.name} />
            <div className="store-container py-6 pb-24 lg:pb-6">
                <div className="grid gap-6 lg:grid-cols-2 lg:gap-10">
                    <div className="space-y-3">
                        <div className="relative overflow-hidden rounded-lg bg-white shadow-sm">
                            <img
                                src={photos[selectedPhoto]}
                                alt={product.name}
                                className="aspect-[3/4] w-full object-cover"
                            />
                        </div>
                        {photos.length > 1 && (
                            <div className="flex gap-2 overflow-x-auto">
                                {photos.map((photo, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => setSelectedPhoto(i)}
                                        className={`shrink-0 overflow-hidden rounded-md border-2 ${
                                            i === selectedPhoto ? 'border-store-accent' : 'border-transparent'
                                        }`}
                                    >
                                        <img src={photo} alt="" className="size-14 object-cover sm:size-16" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="space-y-4">
                        {product.category && (
                            <p className="text-xs uppercase tracking-wider text-store-muted">{product.category}</p>
                        )}
                        <h1 className="text-xl font-bold text-store-primary sm:text-2xl">{product.name}</h1>

                        <div className="flex items-center gap-3">
                            <span className="text-2xl font-bold text-store-accent">৳{effectivePrice}</span>
                            {product.discount_price > 0 && !selectedVariation && (
                                <span className="text-base text-gray-400 line-through">৳{product.sale_price}</span>
                            )}
                        </div>

                        {product.variations?.length > 0 && (
                            <div>
                                <p className="mb-2 text-sm font-medium text-store-primary">Variation</p>
                                <div className="flex flex-wrap gap-2">
                                    {product.variations.map((v) => (
                                        <button
                                            key={v.id}
                                            type="button"
                                            onClick={() => setSelectedVariation(selectedVariation?.id === v.id ? null : v)}
                                            disabled={v.stock <= 0}
                                            className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                                selectedVariation?.id === v.id
                                                    ? 'border-store-accent bg-store-accent text-white'
                                                    : 'border-gray-200 hover:border-store-accent'
                                            } ${v.stock <= 0 ? 'cursor-not-allowed opacity-40' : ''}`}
                                        >
                                            {Object.values(v.variation_data || {}).join(' / ')}
                                            {v.stock <= 0 && ' (Out of stock)'}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {product.tailor_option === 'yes' && (
                            <div>
                                <p className="mb-2 text-sm font-medium text-store-primary">Service</p>
                                <div className="flex gap-2">
                                    {['standard', 'tailor'].map((opt) => (
                                        <button
                                            key={opt}
                                            type="button"
                                            onClick={() => setTailorService(opt)}
                                            className={`rounded-md border px-3 py-1.5 text-sm capitalize ${
                                                tailorService === opt
                                                    ? 'border-store-accent bg-store-accent text-white'
                                                    : 'border-gray-200'
                                            }`}
                                        >
                                            {opt === 'standard' ? 'Standard' : `Tailor (+৳${product.tailor_price})`}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <StoreButton className="hidden w-full lg:flex" onClick={handleAddToCart} disabled={adding}>
                            <ShoppingCart className="size-4" />
                            {adding ? 'Adding...' : 'Add to Cart'}
                        </StoreButton>

                        {product.description && (
                            <div className="border-t border-gray-100 pt-4">
                                <h3 className="mb-2 text-sm font-semibold">Description</h3>
                                <div
                                    className="prose prose-sm max-w-none text-store-muted"
                                    dangerouslySetInnerHTML={{ __html: product.description }}
                                />
                            </div>
                        )}

                        {product.delivery_info && (
                            <div className="rounded-lg bg-store-warm/50 p-3 text-sm text-store-primary">
                                {product.delivery_info}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-gray-100 bg-white p-3 shadow-lg lg:hidden">
                <div className="flex items-center gap-3">
                    <div className="shrink-0">
                        <p className="text-lg font-bold text-store-accent">৳{effectivePrice}</p>
                    </div>
                    <StoreButton className="flex-1" onClick={handleAddToCart} disabled={adding}>
                        {adding ? 'Adding...' : 'Add to Cart'}
                    </StoreButton>
                </div>
            </div>
        </FrontendLayout>
    );
}
