import { AnimatePresence, motion } from 'framer-motion';
import { Link, router } from '@inertiajs/react';
import { ShoppingBag, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { QuantityStepper } from '@/components/frontend/quantity-stepper';
import { StoreButton } from '@/components/frontend/store-button';
import { useCartDrawer } from '@/hooks/use-cart-drawer';

const FREE_SHIPPING_THRESHOLD = 500;
const drawerTransition = { type: 'spring', damping: 30, stiffness: 300 };
const overlayTransition = { duration: 0.25, ease: 'easeInOut' };

export function CartDrawer() {
    const { isOpen, closeDrawer, localCart, setLocalCart, removedItem, setRemovedItem } = useCartDrawer();
    const [updating, setUpdating] = useState(null);

    const items = useMemo(
        () => Object.entries(localCart || {}).map(([key, item]) => ({ ...item, cartKey: key })),
        [localCart],
    );

    const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const progress = Math.min(100, (subtotal / FREE_SHIPPING_THRESHOLD) * 100);
    const remaining = Math.max(0, FREE_SHIPPING_THRESHOLD - subtotal);

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

    const refreshCart = async () => {
        const res = await fetch('/cart/json', { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            setLocalCart(data.cart);
        }
    };

    const updateQty = async (cartKey, qty) => {
        setUpdating(cartKey);
        await fetch(`/cart/${encodeURIComponent(cartKey)}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ quantity: qty }),
        });
        await refreshCart();
        setUpdating(null);
    };

    const removeItem = async (cartKey, item) => {
        setRemovedItem({ cartKey, item, timeout: Date.now() });
        await fetch(`/cart/${encodeURIComponent(cartKey)}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        await refreshCart();
        setTimeout(() => setRemovedItem(null), 5000);
    };

    const undoRemove = async () => {
        if (!removedItem?.item) {
            return;
        }
        await fetch('/cart/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                product_id: removedItem.item.product_id,
                quantity: removedItem.item.quantity,
                variation_id: removedItem.item.variation_id,
            }),
        });
        setRemovedItem(null);
        await refreshCart();
    };

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                closeDrawer();
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [isOpen, closeDrawer]);

    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        }
    }, [isOpen]);

    const handleExitComplete = () => {
        document.body.style.overflow = '';
    };

    return (
        <AnimatePresence onExitComplete={handleExitComplete}>
            {isOpen && (
                <>
                    <motion.button
                        type="button"
                        key="cart-backdrop"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={overlayTransition}
                        onClick={closeDrawer}
                        className="fixed inset-0 z-[60] bg-black/40 backdrop-blur-sm"
                        aria-label="Close cart"
                    />

                    <motion.aside
                        key="cart-panel"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="cart-drawer-title"
                        initial={{ x: '100%' }}
                        animate={{ x: 0 }}
                        exit={{ x: '100%' }}
                        transition={drawerTransition}
                        className="fixed inset-y-0 right-0 z-[61] flex w-full max-w-md flex-col bg-white shadow-xl"
                    >
                        <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <h2 id="cart-drawer-title" className="flex items-center gap-2 text-base font-semibold text-store-primary">
                                <ShoppingBag className="size-5" />
                                Cart ({items.length})
                            </h2>
                            <button
                                onClick={closeDrawer}
                                className="rounded-md p-1.5 text-gray-500 hover:bg-store-surface"
                                aria-label="Close cart"
                            >
                                <X className="size-5" />
                            </button>
                        </div>

                        {items.length === 0 ? (
                            <div className="flex flex-1 flex-col items-center justify-center px-6 text-center">
                                <ShoppingBag className="mb-3 size-12 text-gray-300" />
                                <p className="text-sm text-store-muted">Your cart is empty</p>
                                <Link href="/" onClick={closeDrawer} className="mt-4 inline-block">
                                    <StoreButton variant="outline">Continue Shopping</StoreButton>
                                </Link>
                            </div>
                        ) : (
                            <>
                                <div className="border-b border-gray-100 px-4 py-3">
                                    <div className="mb-1 flex justify-between text-xs text-store-muted">
                                        <span>
                                            {remaining > 0
                                                ? `৳${remaining} away from free shipping`
                                                : 'Free shipping unlocked!'}
                                        </span>
                                        <span>৳{FREE_SHIPPING_THRESHOLD}</span>
                                    </div>
                                    <div className="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                        <motion.div
                                            className="store-gradient h-full rounded-full"
                                            initial={{ width: 0 }}
                                            animate={{ width: `${progress}%` }}
                                        />
                                    </div>
                                </div>

                                <ul className="flex-1 overflow-y-auto px-4 py-3">
                                    <AnimatePresence mode="popLayout">
                                        {items.map((item) => (
                                            <motion.li
                                                key={item.cartKey}
                                                layout
                                                initial={{ opacity: 0, x: 20 }}
                                                animate={{ opacity: 1, x: 0 }}
                                                exit={{ opacity: 0, x: -20 }}
                                                className="flex gap-3 border-b border-gray-50 py-3 last:border-0"
                                            >
                                                {item.image ? (
                                                    <img
                                                        src={item.image}
                                                        alt={item.name}
                                                        className="size-16 shrink-0 rounded-md object-cover"
                                                    />
                                                ) : (
                                                    <div className="size-16 shrink-0 rounded-md bg-store-surface" />
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-medium text-store-primary line-clamp-2">
                                                        {item.name}
                                                    </p>
                                                    {item.sku && (
                                                        <p className="text-xs text-store-muted">{item.sku}</p>
                                                    )}
                                                    <p className="mt-0.5 text-sm font-semibold text-store-accent">
                                                        ৳{item.price}
                                                    </p>
                                                    <div className="mt-2 flex items-center justify-between">
                                                        <QuantityStepper
                                                            value={item.quantity}
                                                            onChange={(q) => updateQty(item.cartKey, q)}
                                                            className={updating === item.cartKey ? 'opacity-50' : ''}
                                                        />
                                                        <button
                                                            onClick={() => removeItem(item.cartKey, item)}
                                                            className="p-1 text-gray-400 hover:text-store-accent"
                                                            aria-label="Remove item"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </motion.li>
                                        ))}
                                    </AnimatePresence>
                                </ul>

                                {removedItem && (
                                    <div className="mx-4 mb-2 flex items-center justify-between rounded-md bg-store-surface px-3 py-2 text-xs">
                                        <span>Item removed</span>
                                        <button onClick={undoRemove} className="font-semibold text-store-accent">
                                            Undo
                                        </button>
                                    </div>
                                )}

                                <div className="border-t border-gray-100 bg-white px-4 py-4">
                                    <div className="mb-3 flex justify-between text-sm">
                                        <span className="text-store-muted">Subtotal</span>
                                        <span className="font-semibold text-store-primary">৳{subtotal}</span>
                                    </div>
                                    <p className="mb-3 text-center text-[10px] text-store-muted">
                                        Cash on Delivery · SSLCommerz · bKash
                                    </p>
                                    <StoreButton
                                        className="w-full"
                                        onClick={() => {
                                            closeDrawer();
                                            router.visit('/checkout');
                                        }}
                                    >
                                        Checkout — ৳{subtotal}
                                    </StoreButton>
                                    <button
                                        onClick={closeDrawer}
                                        className="mt-2 w-full py-2 text-center text-xs text-store-muted hover:text-store-primary"
                                    >
                                        Continue shopping
                                    </button>
                                </div>
                            </>
                        )}
                    </motion.aside>
                </>
            )}
        </AnimatePresence>
    );
}
