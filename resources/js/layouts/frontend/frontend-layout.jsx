import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CartDrawer } from '@/components/frontend/cart-drawer';
import { FloatingCart } from '@/components/frontend/floating-cart';
import { ToastNotification } from '@/components/frontend/toast-notification';
import { CartDrawerProvider, useCartDrawer } from '@/hooks/use-cart-drawer';
import { StoreFooter } from '@/layouts/frontend/store-footer';
import { StoreHeader } from '@/layouts/frontend/store-header';
import { TrustStrip } from '@/layouts/frontend/trust-strip';

function LayoutInner({ children, isHome }) {
    const { cart = {}, logo, flash } = usePage().props;
    const { toast, setLocalCart } = useCartDrawer();
    const [showPreloader, setShowPreloader] = useState(isHome);

    useEffect(() => {
        setLocalCart(cart);
    }, [cart, setLocalCart]);

    useEffect(() => {
        if (!isHome) {
            return;
        }
        const timer = setTimeout(() => setShowPreloader(false), 800);
        const onLoad = () => setShowPreloader(false);
        window.addEventListener('load', onLoad);
        return () => {
            clearTimeout(timer);
            window.removeEventListener('load', onLoad);
        };
    }, [isHome]);

    return (
        <div className="flex min-h-screen flex-col bg-store-surface font-[Inter,system-ui,sans-serif] text-store-primary">
            {showPreloader && isHome && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-white">
                    {logo ? (
                        <img src={logo} alt="Loading" className="h-16 animate-pulse object-contain" />
                    ) : (
                        <div className="size-10 animate-spin rounded-full border-2 border-store-accent border-t-transparent" />
                    )}
                </div>
            )}

            <StoreHeader />
            <main className="flex-1">{children}</main>
            <TrustStrip />
            <StoreFooter />
            <FloatingCart />
            <CartDrawer />
            <ToastNotification message={toast || flash?.success} />
        </div>
    );
}

export default function FrontendLayout({ children, isHome = false, title }) {
    const { cart = {} } = usePage().props;

    return (
        <CartDrawerProvider initialCart={cart}>
            {title && <Head title={title} />}
            <LayoutInner isHome={isHome}>{children}</LayoutInner>
        </CartDrawerProvider>
    );
}
