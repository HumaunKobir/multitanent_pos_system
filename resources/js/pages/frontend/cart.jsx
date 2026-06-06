import { Head, Link, router } from '@inertiajs/react';
import { ShoppingBag, Trash2 } from 'lucide-react';
import { QuantityStepper } from '@/components/frontend/quantity-stepper';
import { StoreButton } from '@/components/frontend/store-button';
import { useCustomerCheckoutGuard } from '@/hooks/use-customer-checkout-guard';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export default function Cart({ cart }) {
    const { goToCheckout } = useCustomerCheckoutGuard();
    const items = Object.entries(cart || {}).map(([key, item]) => ({ ...item, cartKey: key }));
    const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const tailorTotal = items.reduce(
        (sum, item) => sum + (item.tailor_service === 'tailor' ? (item.tailor_price || 0) * item.quantity : 0),
        0,
    );

    const updateQty = (cartKey, qty) => {
        router.patch(`/cart/${encodeURIComponent(cartKey)}`, { quantity: qty }, { preserveScroll: true });
    };

    const remove = (cartKey) => {
        router.delete(`/cart/${encodeURIComponent(cartKey)}`, { preserveScroll: true });
    };

    return (
        <FrontendLayout>
            <Head title="Cart" />
            <div className="store-container py-6 pb-28 lg:pb-6">
                <h1 className="mb-5 text-xl font-bold text-store-primary sm:text-2xl">Shopping Cart</h1>

                {items.length === 0 ? (
                    <div className="rounded-lg bg-white py-16 text-center shadow-sm">
                        <ShoppingBag className="mx-auto mb-4 size-12 text-gray-300" />
                        <p className="mb-4 text-store-muted">Your cart is empty</p>
                        <Link href="/">
                            <StoreButton>Continue Shopping</StoreButton>
                        </Link>
                    </div>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-3 lg:col-span-2">
                            {items.map((item) => (
                                <div
                                    key={item.cartKey}
                                    className="flex gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm"
                                >
                                    {item.image ? (
                                        <img src={item.image} alt={item.name} className="size-20 shrink-0 rounded-md object-cover" />
                                    ) : (
                                        <div className="size-20 shrink-0 rounded-md bg-store-surface" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-store-primary line-clamp-2">{item.name}</p>
                                        {item.sku && <p className="text-xs text-store-muted">{item.sku}</p>}
                                        {item.tailor_service === 'tailor' && (
                                            <p className="text-xs text-store-accent">Tailor service</p>
                                        )}
                                        <p className="mt-1 text-sm font-semibold text-store-accent">৳{item.price}</p>
                                        <div className="mt-2 flex items-center justify-between">
                                            <QuantityStepper value={item.quantity} onChange={(q) => updateQty(item.cartKey, q)} />
                                            <button onClick={() => remove(item.cartKey)} className="p-1 text-gray-400 hover:text-store-accent">
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="lg:col-span-1">
                            <div className="sticky top-20 rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                                <h2 className="mb-3 font-semibold text-store-primary">Order Summary</h2>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-store-muted">Subtotal</span>
                                        <span>৳{subtotal}</span>
                                    </div>
                                    {tailorTotal > 0 && (
                                        <div className="flex justify-between">
                                            <span className="text-store-muted">Tailor</span>
                                            <span>৳{tailorTotal}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between border-t border-gray-100 pt-2 font-bold">
                                        <span>Total</span>
                                        <span className="text-store-accent">৳{subtotal}</span>
                                    </div>
                                </div>
                                <StoreButton className="mt-4 w-full" onClick={goToCheckout}>
                                    Proceed to Checkout
                                </StoreButton>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {items.length > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-40 border-t bg-white p-3 shadow-lg lg:hidden">
                    <StoreButton className="w-full" onClick={goToCheckout}>
                        Checkout — ৳{subtotal}
                    </StoreButton>
                </div>
            )}
        </FrontendLayout>
    );
}
