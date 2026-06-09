/**
 * @param {number|string} netAmount
 * @param {number|string} tenderedAmount
 * @returns {{ effectivePaid: number, dueAmount: number, changeAmount: number }}
 */
export function computeSalePayment(netAmount, tenderedAmount) {
    const net = Math.max(0, parseFloat(netAmount) || 0);
    const tendered = Math.max(0, parseFloat(tenderedAmount) || 0);
    const effectivePaid = Math.min(tendered, net);

    return {
        effectivePaid,
        dueAmount: Math.max(0, net - effectivePaid),
        changeAmount: Math.max(0, tendered - net),
    };
}

/**
 * @param {number|string|null|undefined} customerId
 * @param {number|string|null|undefined} walkInCustomerId
 * @param {number} dueAmount
 * @returns {string|null}
 */
export function dueSaleCustomerError(customerId, walkInCustomerId, dueAmount) {
    if (dueAmount <= 0) {
        return null;
    }

    if (!customerId) {
        return 'Select a customer to record due amount.';
    }

    if (walkInCustomerId && String(customerId) === String(walkInCustomerId)) {
        return 'Due sales require a registered customer. Walk-in cannot have due.';
    }

    return null;
}
