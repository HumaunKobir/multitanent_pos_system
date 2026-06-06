import { router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { alertCustomerLoginRequired, isCustomerLoggedIn } from '@/lib/customer-checkout-auth';

export function useCustomerCheckoutGuard() {
    const { auth } = usePage().props;
    const loggedIn = isCustomerLoggedIn(auth);

    const requireCustomerLogin = useCallback(
        (onAllowed) => {
            if (loggedIn) {
                onAllowed?.();
                return true;
            }

            alertCustomerLoginRequired();
            return false;
        },
        [loggedIn],
    );

    const goToCheckout = useCallback(() => {
        requireCustomerLogin(() => router.visit('/checkout'));
    }, [requireCustomerLogin]);

    return { loggedIn, requireCustomerLogin, goToCheckout };
}
