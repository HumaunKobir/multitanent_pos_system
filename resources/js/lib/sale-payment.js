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
 * @param {Array<{ amount?: number|string }>} payments
 * @param {number|string} netAmount
 * @returns {{ totalPaid: number, effectivePaid: number, dueAmount: number, changeAmount: number, remaining: number }}
 */
export function computeSplitSalePayment(payments, netAmount) {
    const net = Math.max(0, parseFloat(netAmount) || 0);
    const totalPaid = (payments ?? []).reduce((sum, line) => sum + Math.max(0, parseFloat(line.amount) || 0), 0);
    const effectivePaid = Math.min(totalPaid, net);
    const dueAmount = Math.max(0, net - totalPaid);
    const changeAmount = Math.max(0, totalPaid - net);

    return {
        totalPaid,
        effectivePaid,
        dueAmount,
        changeAmount,
        remaining: dueAmount,
    };
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} payments
 * @returns {string|null}
 */
export function splitPaymentValidationError(payments) {
    const lines = (payments ?? []).filter((line) => {
        const amount = parseFloat(line.amount) || 0;

        return amount > 0;
    });

    if (lines.length === 0) {
        return null;
    }

    for (const line of lines) {
        const accountId = line.payment_account_id;

        if (!accountId) {
            return 'Select an account for each payment line.';
        }
    }

    return null;
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

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} payments
 * @returns {Array<{ payment_account_id: number, amount: number }>}
 */
export function serializeSalePayments(payments) {
    return (payments ?? [])
        .map((line) => ({
            payment_account_id: Number(line.payment_account_id),
            amount: Math.max(0, parseFloat(line.amount) || 0),
        }))
        .filter((line) => line.payment_account_id > 0 && line.amount > 0);
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>|null|undefined} initialPayments
 * @param {Array<{ id: number }>} paymentAccounts
 * @returns {Array<{ payment_account_id: string, amount: string }>}
 */
export function buildInitialSalePayments(initialPayments, paymentAccounts) {
    if (initialPayments?.length) {
        return initialPayments.map((line) => ({
            payment_account_id: String(line.payment_account_id ?? ''),
            amount: String(line.amount ?? '0'),
        }));
    }

    if (paymentAccounts[0]?.id) {
        return [{ payment_account_id: String(paymentAccounts[0].id), amount: '0' }];
    }

    return [{ payment_account_id: '', amount: '0' }];
}
