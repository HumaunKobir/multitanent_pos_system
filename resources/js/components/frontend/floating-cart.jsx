import { motion } from 'framer-motion';
import { ShoppingCart } from 'lucide-react';
import { useMemo } from 'react';
import { useCartDrawer } from '@/hooks/use-cart-drawer';

function formatPrice(amount) {
    return new Intl.NumberFormat('en-BD', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

export function FloatingCart() {
    const { openDrawer, cartCount, localCart } = useCartDrawer();

    const subtotal = useMemo(
        () => Object.values(localCart || {}).reduce((sum, item) => sum + (item.price || 0) * (item.quantity || 0), 0),
        [localCart],
    );

    const itemLabel = cartCount === 1 ? 'item' : 'items';

    return (
        <motion.button
            type="button"
            onClick={openDrawer}
            initial={{ opacity: 0, x: 16 }}
            animate={{ opacity: 1, x: 0 }}
            whileHover={{ scale: 1.04 }}
            whileTap={{ scale: 0.96 }}
            aria-label={`Open cart, ${cartCount} ${itemLabel}, total ৳ ${formatPrice(subtotal)}`}
            className="fixed right-0 top-1/2 z-40 flex w-14 -translate-y-1/2 flex-col overflow-hidden rounded-l-xl shadow-lg shadow-store-primary/20 transition-shadow hover:shadow-xl hover:shadow-store-primary/25"
        >
            <div className="store-gradient flex flex-col items-center px-1.5 py-2.5">
                <ShoppingCart className="size-5 text-lime-400" strokeWidth={2.5} />
                <span className="mt-1 text-center text-[10px] font-bold italic leading-none text-white">
                    {cartCount} {itemLabel}
                </span>
            </div>

            <div className="flex items-center justify-center bg-store-primary px-1 py-2">
                <span className="text-center text-[11px] font-bold leading-none text-white">
                    ৳{formatPrice(subtotal)}
                </span>
            </div>
        </motion.button>
    );
}
