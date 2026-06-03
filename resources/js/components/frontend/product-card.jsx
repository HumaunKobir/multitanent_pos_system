import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Play, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import { StoreBadge } from '@/components/frontend/store-badge';
import { useAddToCart } from '@/hooks/use-add-to-cart';

export function ProductCard({ product }) {
    const hasDiscount = product.discount_price > 0;
    const [adding, setAdding] = useState(false);
    const { addToCart } = useAddToCart();

    const handleQuickAdd = async (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (product.has_variations) {
            window.location.href = `/products/${product.slug}`;
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
        <div className="group relative">
            <Link href={`/products/${product.slug}`} className="block">
                <div className="relative overflow-hidden rounded-lg bg-white shadow-sm transition-shadow duration-300 group-hover:shadow-md">
                    {product.image ? (
                        <motion.img
                            src={product.image}
                            alt={product.name}
                            className="aspect-[3/4] w-full object-cover"
                            whileHover={{ scale: 1.04 }}
                            transition={{ duration: 0.4 }}
                        />
                    ) : (
                        <div className="aspect-[3/4] w-full bg-gray-100" />
                    )}

                    {hasDiscount && (
                        <StoreBadge variant="sale" className="absolute left-2 top-2">
                            SALE
                        </StoreBadge>
                    )}

                    {product.youtube_link && (
                        <div className="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:opacity-100">
                            <div className="flex size-10 items-center justify-center rounded-full bg-white/90 shadow">
                                <Play className="size-4 fill-store-primary text-store-primary" />
                            </div>
                        </div>
                    )}

                    <div className="absolute inset-x-0 bottom-0 translate-y-full bg-gradient-to-t from-black/60 to-transparent p-3 transition-transform duration-300 group-hover:translate-y-0">
                        <button
                            type="button"
                            onClick={handleQuickAdd}
                            disabled={adding}
                            className="flex w-full items-center justify-center gap-1.5 rounded-md bg-white py-2 text-xs font-semibold text-store-primary hover:bg-store-accent hover:text-white disabled:opacity-60"
                        >
                            <ShoppingBag className="size-3.5" />
                            {adding ? 'Adding...' : 'Add to Cart'}
                        </button>
                    </div>
                </div>

                <div className="mt-2 space-y-0.5 px-0.5">
                    <p className="text-sm font-medium text-store-primary line-clamp-2">{product.name}</p>
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-bold text-store-accent">৳{product.price}</span>
                        {hasDiscount && (
                            <span className="text-xs text-gray-400 line-through">৳{product.sale_price}</span>
                        )}
                    </div>
                </div>
            </Link>
        </div>
    );
}
