import { Link } from '@inertiajs/react';

export function ProductCard({ product }) {
    const hasDiscount = product.discount_price > 0;

    return (
        <Link href={`/product/${product.slug}`} className="group block">
            <div className="relative overflow-hidden bg-gray-100">
                {product.image ? (
                    <img
                        src={product.image}
                        alt={product.name}
                        className="aspect-[3/4] w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="aspect-[3/4] w-full bg-gray-200" />
                )}
                {hasDiscount && (
                    <span className="absolute left-2 top-2 bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">
                        SALE
                    </span>
                )}
            </div>
            <div className="mt-2 space-y-0.5">
                <p className="text-sm font-medium text-gray-900 line-clamp-2">{product.name}</p>
                <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold text-gray-900">৳{product.price}</span>
                    {hasDiscount && (
                        <span className="text-xs text-gray-400 line-through">৳{product.sale_price}</span>
                    )}
                </div>
            </div>
        </Link>
    );
}
