export const CHECKOUT_LOGIN_MESSAGE = 'Please log in to continue checkout.';

export function isCustomerLoggedIn(auth) {
    return Boolean(auth?.customer);
}

export function alertCustomerLoginRequired() {
    window.alert(CHECKOUT_LOGIN_MESSAGE);
}
