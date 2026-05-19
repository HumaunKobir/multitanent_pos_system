import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ShoppingCart, ChevronLeft, ChevronRight } from 'lucide-react';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function SingleProduct({ product }) {
    const [selectedPhoto, setSelectedPhoto] = useState(0);
    const [selectedVariation, setSelectedVariation] = useState(null);

    const photos = [product.image, ...(product.photos || [])].filter(Boolean);

    const effectivePrice = selectedVariation
        ? selectedVariation.price
        : product.discount_price > 0
          ? product.discount_price
          : product.sale_price;

    const handleAddToCart = () => {
        router.post(
            '/cart/add',
            {
                product_id: product.id,
                quantity: 1,
                variation_id: selectedVariation?.id ?? null,
            },
            {
                preserveScroll: true,
                onSuccess: () => alert('কার্টে যোগ হয়েছে!'),
            },
        );
    };

    return (
        <FrontendLayout>
            <Head title={product.name} />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid gap-8 lg:grid-cols-2">
                    {/* Images */}
                    <div className="space-y-3">
                        <div className="relative overflow-hidden bg-gray-100">
                            <img
                                src={photos[selectedPhoto]}
                                alt={product.name}
                                className="aspect-[3/4] w-full object-cover"
                            />
                            {photos.length > 1 && (
                                <>
                                    <button
                                        onClick={() => setSelectedPhoto((p) => Math.max(0, p - 1))}
                                        className="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-1.5 shadow"
                                    >
                                        <ChevronLeft className="size-4" />
                                    </button>
                                    <button
                                        onClick={() =>
                                            setSelectedPhoto((p) => Math.min(photos.length - 1, p + 1))
                                        }
                                        className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-1.5 shadow"
                                    >
                                        <ChevronRight className="size-4" />
                                    </button>
                                </>
                            )}
                        </div>
                        {photos.length > 1 && (
                            <div className="flex gap-2 overflow-x-auto">
                                {photos.map((photo, i) => (
                                    <button
                                        key={i}
                                        onClick={() => setSelectedPhoto(i)}
                                        className={`shrink-0 overflow-hidden border-2 transition-colors ${
                                            i === selectedPhoto ? 'border-black' : 'border-transparent'
                                        }`}
                                    >
                                        <img
                                            src={photo}
                                            alt=""
                                            className="size-16 object-cover"
                                        />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Details */}
                    <div className="space-y-5">
                        <div>
                            {product.category && (
                                <p className="mb-1 text-xs uppercase tracking-wider text-gray-500">
                                    {product.category}
                                </p>
                            )}
                            <h1 className="text-2xl font-bold text-gray-900">{product.name}</h1>
                        </div>

                        <div className="flex items-center gap-3">
                            <span className="text-2xl font-bold text-gray-900">৳{effectivePrice}</span>
                            {product.discount_price > 0 && !selectedVariation && (
                                <span className="text-base text-gray-400 line-through">
                                    ৳{product.sale_price}
                                </span>
                            )}
                        </div>

                        {/* Variations */}
                        {product.variations?.length > 0 && (
                            <div>
                                <p className="mb-2 text-sm font-medium text-gray-700">ভেরিয়েশন বেছে নিন</p>
                                <div className="flex flex-wrap gap-2">
                                    {product.variations.map((v) => (
                                        <button
                                            key={v.id}
                                            onClick={() =>
                                                setSelectedVariation(
                                                    selectedVariation?.id === v.id ? null : v,
                                                )
                                            }
                                            disabled={v.stock <= 0}
                                            className={`border px-3 py-1.5 text-sm transition-colors ${
                                                v.stock <= 0
                                                    ? 'cursor-not-allowed border-gray-200 text-gray-300'
                                                    : selectedVariation?.id === v.id
                                                      ? 'border-black bg-black text-white'
                                                      : 'border-gray-300 hover:border-black'
                                            }`}
                                        >
                                            {Object.values(v.variation_data || {}).join(' / ')}
                                            {v.stock <= 0 && ' (শেষ)'}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Simple color/size chips */}
                        {product.colors?.length > 0 && !product.variations?.length && (
                            <div>
                                <p className="mb-2 text-sm font-medium text-gray-700">রঙ</p>
                                <div className="flex flex-wrap gap-2">
                                    {product.colors.map((color) => (
                                        <span
                                            key={color}
                                            className="border border-gray-300 px-3 py-1 text-sm"
                                        >
                                            {color}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}

                        {product.sizes?.length > 0 && !product.variations?.length && (
                            <div>
                                <p className="mb-2 text-sm font-medium text-gray-700">সাইজ</p>
                                <div className="flex flex-wrap gap-2">
                                    {product.sizes.map((size) => (
                                        <span
                                            key={size}
                                            className="border border-gray-300 px-3 py-1 text-sm"
                                        >
                                            {size}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Add to cart */}
                        <button
                            onClick={handleAddToCart}
                            className="flex w-full items-center justify-center gap-2 bg-black py-3.5 text-sm font-semibold text-white transition-colors hover:bg-gray-800"
                        >
                            <ShoppingCart className="size-4" />
                            কার্টে যোগ করুন
                        </button>

                        {/* Description */}
                        {product.description && (
                            <div className="border-t border-gray-100 pt-5">
                                <h3 className="mb-2 text-sm font-semibold text-gray-900">বিবরণ</h3>
                                <div
                                    className="prose prose-sm max-w-none text-gray-600"
                                    dangerouslySetInnerHTML={{ __html: product.description }}
                                />
                            </div>
                        )}

                        {/* Delivery info */}
                        {product.delivery_info && (
                            <div className="border-t border-gray-100 pt-5">
                                <h3 className="mb-2 text-sm font-semibold text-gray-900">ডেলিভারি তথ্য</h3>
                                <p className="text-sm text-gray-600">{product.delivery_info}</p>
                            </div>
                        )}

                        {/* YouTube */}
                        {product.youtube_link && (
                            <div className="border-t border-gray-100 pt-5">
                                <h3 className="mb-2 text-sm font-semibold text-gray-900">ভিডিও</h3>
                                <div className="aspect-video">
                                    <iframe
                                        src={product.youtube_link.replace('watch?v=', 'embed/')}
                                        className="size-full"
                                        allowFullScreen
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </FrontendLayout>
    );
}
