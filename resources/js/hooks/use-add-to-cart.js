import { useCallback, useRef } from 'react';
import { useCartDrawer } from '@/hooks/use-cart-drawer';

export function useAddToCart() {
    const { openDrawer, setLocalCart, showToast, isOpen } = useCartDrawer();
    const hasOpenedRef = useRef(false);
    const loadingRef = useRef(false);

    const addToCart = useCallback(
        async (payload, { redirect = false, openDrawerOnAdd = true } = {}) => {
            if (loadingRef.current) {
                return null;
            }

            loadingRef.current = true;

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch('/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    throw new Error(error.message || 'Could not add to cart');
                }

                const data = await response.json();
                setLocalCart(data.cart);

                if (redirect) {
                    window.location.href = '/cart';
                    return data;
                }

                if (openDrawerOnAdd && (!hasOpenedRef.current || !isOpen)) {
                    openDrawer();
                    hasOpenedRef.current = true;
                } else {
                    showToast(data.message || 'Added to cart');
                }

                return data;
            } finally {
                loadingRef.current = false;
            }
        },
        [openDrawer, setLocalCart, showToast, isOpen],
    );

    return { addToCart };
}
