import { createContext, useCallback, useContext, useMemo, useState } from 'react';

const CartDrawerContext = createContext(null);

export function CartDrawerProvider({ children, initialCart = {} }) {
    const [isOpen, setIsOpen] = useState(false);
    const [localCart, setLocalCart] = useState(initialCart);
    const [toast, setToast] = useState(null);
    const [removedItem, setRemovedItem] = useState(null);

    const cartCount = useMemo(() => Object.keys(localCart || {}).length, [localCart]);

    const openDrawer = useCallback(() => setIsOpen(true), []);
    const closeDrawer = useCallback(() => setIsOpen(false), []);

    const showToast = useCallback((message) => {
        setToast(message);
        setTimeout(() => setToast(null), 3000);
    }, []);

    const updateCart = useCallback((cart) => {
        setLocalCart(cart);
    }, []);

    const value = useMemo(
        () => ({
            isOpen,
            openDrawer,
            closeDrawer,
            setIsOpen,
            localCart,
            setLocalCart: updateCart,
            cartCount,
            toast,
            showToast,
            removedItem,
            setRemovedItem,
        }),
        [isOpen, openDrawer, closeDrawer, localCart, updateCart, cartCount, toast, showToast, removedItem],
    );

    return <CartDrawerContext.Provider value={value}>{children}</CartDrawerContext.Provider>;
}

export function useCartDrawer() {
    const ctx = useContext(CartDrawerContext);
    if (!ctx) {
        throw new Error('useCartDrawer must be used within CartDrawerProvider');
    }
    return ctx;
}
