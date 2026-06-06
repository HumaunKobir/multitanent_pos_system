import { AnimatePresence, motion } from 'framer-motion';
import { Link } from '@inertiajs/react';
import { ShoppingBag, Trash2, Truck, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { QuantityStepper } from '@/components/frontend/quantity-stepper';
import { StoreButton } from '@/components/frontend/store-button';
import { useCartDrawer } from '@/hooks/use-cart-drawer';
import { useCustomerCheckoutGuard } from '@/hooks/use-customer-checkout-guard';

const FREE_SHIPPING_THRESHOLD = 500;
const drawerTransition = { type: 'spring', damping: 32, stiffness: 320 };
const overlayTransition = { duration: 0.22, ease: 'easeOut' };

function formatPrice(amount) {
    return new Intl.NumberFormat('en-BD', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

export function CartDrawer() {
    const { isOpen, closeDrawer, localCart, setLocalCart, removedItem, setRemovedItem } = useCartDrawer();
    const { goToCheckout } = useCustomerCheckoutGuard();
    const [updating, setUpdating] = useState(null);

    const items = useMemo(
        () => Object.entries(localCart || {}).map(([key, item]) => ({ ...item, cartKey: key })),
        [localCart],
    );

    const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const progress = Math.min(100, (subtotal / FREE_SHIPPING_THRESHOLD) * 100);
    const remaining = Math.max(0, FREE_SHIPPING_THRESHOLD - subtotal);
    const shippingUnlocked = remaining === 0;

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
                        className="fixed inset-0 z-60 bg-black/40"
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
                        className="fixed inset-y-0 right-0 z-61 flex w-full max-w-md flex-col overflow-hidden rounded-l-none bg-white shadow-2xl"
                    >
                        <div className="flex shrink-0 items-center justify-between border-b border-white/10 bg-store-primary px-4 py-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                            <h2
                                id="cart-drawer-title"
                                className="flex items-center gap-2 text-base font-semibold text-white"
                            >
                                <ShoppingBag className="size-5 text-store-accent" />
                                Cart ({items.length})
                            </h2>
                            <button
                                onClick={closeDrawer}
                                className="rounded-md p-1.5 text-store-accent transition-colors hover:bg-white/10 hover:text-store-accent"
                                aria-label="Close cart"
                            >
                                <X className="size-5" strokeWidth={2.25} />
                            </button>
                        </div>

                        {items.length > 0 && (
                            <div className="shrink-0 border-b border-gray-100 px-4 py-3">
                                <div className="mb-1 flex justify-between text-xs text-store-muted">
                                    <span className="inline-flex items-center gap-1">
                                        <Truck className="size-3.5 shrink-0 text-store-accent" />
                                        {shippingUnlocked
                                            ? 'Free shipping unlocked!'
                                            : `৳${formatPrice(remaining)} away from free shipping`}
                                    </span>
                                    <span>৳{FREE_SHIPPING_THRESHOLD}</span>
                                </div>
                                <div className="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                    <motion.div
                                        className="h-full rounded-full bg-store-accent"
                                        initial={{ width: 0 }}
                                        animate={{ width: `${progress}%` }}
                                        transition={{ duration: 0.35, ease: 'easeOut' }}
                                    />
                                </div>
                            </div>
                        )}

                        {items.length === 0 ? (
                            <div className="flex flex-1 flex-col items-center justify-center px-5 text-center">
                                <ShoppingBag className="mb-2 size-10 text-gray-200" strokeWidth={1.25} />
                                <p className="text-sm font-medium text-store-primary">Your cart is empty</p>
                                <p className="mt-0.5 text-[11px] text-store-muted">Add items to see them here.</p>
                                <Link href="/" onClick={closeDrawer} className="mt-3 inline-block">
                                    <StoreButton variant="outline" className="px-3 py-1.5 text-xs">
                                        Continue Shopping
                                    </StoreButton>
                                </Link>
                            </div>
                        ) : (
                            <>
                                <ul className="store-filter-scroll flex-1 divide-y divide-gray-200 overflow-y-auto">
                                    <AnimatePresence mode="popLayout">
                                        {items.map((item) => {
                                            const lineTotal = item.price * item.quantity;

                                            return (
                                                <motion.li
                                                    key={item.cartKey}
                                                    layout
                                                    initial={{ opacity: 0, y: 6 }}
                                                    animate={{ opacity: 1, y: 0 }}
                                                    exit={{ opacity: 0, x: -8 }}
                                                    className="group flex gap-2.5 px-3 py-2.5"
                                                >
                                                    {item.image ? (
                                                        <img
                                                            src={item.image}
                                                            alt={item.name}
                                                            className="size-11 shrink-0 rounded object-cover ring-1 ring-gray-200"
                                                        />
                                                    ) : (
                                                        <div className="size-11 shrink-0 rounded bg-store-surface ring-1 ring-gray-200" />
                                                    )}

                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-start gap-1">
                                                            <div className="min-w-0 flex-1">
                                                                <p className="text-[11px] font-medium leading-tight text-store-primary line-clamp-2">
                                                                    {item.name}
                                                                </p>
                                                                {item.sku && (
                                                                    <p className="mt-0.5 truncate text-[9px] text-store-muted">
                                                                        {item.sku}
                                                                    </p>
                                                                )}
                                                            </div>
                                                            <button
                                                                onClick={() => removeItem(item.cartKey, item)}
                                                                className="flex size-5 shrink-0 items-center justify-center rounded text-gray-400 opacity-60 transition-colors hover:text-store-accent group-hover:opacity-100"
                                                                aria-label="Remove item"
                                                            >
                                                                <Trash2 className="size-3" />
                                                            </button>
                                                        </div>

                                                        <div className="mt-1 flex items-center gap-2">
                                                            <span className="shrink-0 text-[11px] font-bold text-store-accent">
                                                                ৳{formatPrice(item.price)}
                                                            </span>
                                                            <QuantityStepper
                                                                variant="pill"
                                                                value={item.quantity}
                                                                onChange={(q) => updateQty(item.cartKey, q)}
                                                                className={updating === item.cartKey ? 'opacity-50' : ''}
                                                            />
                                                            <span className="ml-auto shrink-0 text-[11px] font-semibold text-store-primary">
                                                                ৳{formatPrice(lineTotal)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </motion.li>
                                            );
                                        })}
                                    </AnimatePresence>
                                </ul>

                                <AnimatePresence>
                                    {removedItem && (
                                        <motion.div
                                            initial={{ opacity: 0, y: 6 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: 6 }}
                                            className="mx-2.5 mb-1 flex items-center justify-between rounded-lg border border-gray-100 bg-store-surface px-2.5 py-1.5 text-[10px] text-store-primary"
                                        >
                                            <span>Item removed</span>
                                            <button
                                                onClick={undoRemove}
                                                className="font-semibold text-store-accent hover:underline"
                                            >
                                                Undo
                                            </button>
                                        </motion.div>
                                    )}
                                </AnimatePresence>

                                <div className="shrink-0 border-t border-gray-100 bg-white px-2.5 py-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))]">
                                    <div className="mb-2 flex items-center justify-between text-xs">
                                        <span className="text-store-muted">Subtotal</span>
                                        <span className="font-bold text-store-primary">৳{formatPrice(subtotal)}</span>
                                    </div>

                                    <StoreButton
                                        className="w-full py-2 text-xs"
                                        onClick={() => {
                                            closeDrawer();
                                            goToCheckout();
                                        }}
                                    >
                                        Checkout — ৳{formatPrice(subtotal)}
                                    </StoreButton>

                                    <button
                                        onClick={closeDrawer}
                                        className="mt-1 w-full py-1 text-center text-[10px] text-store-muted hover:text-store-primary"
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
