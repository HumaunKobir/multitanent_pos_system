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
 * @param {Array<{ amount?: number|string }>} payments
 * @param {Array<{ amount?: number|string }>} collectionPayments
 * @param {number|string} netAmount
 */
export function computeSplitSalePaymentWithCollections(payments, collectionPayments, netAmount) {
    const net = Math.max(0, parseFloat(netAmount) || 0);
    const collectionTotal = (collectionPayments ?? []).reduce(
        (sum, line) => sum + Math.max(0, parseFloat(line.amount) || 0),
        0,
    );
    const saleResult = computeSplitSalePayment(payments, net);
    const totalPaid = saleResult.totalPaid + collectionTotal;

    return {
        ...saleResult,
        collectionTotal,
        totalPaid,
        dueAmount: Math.max(0, net - totalPaid),
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
 * Sale returns require a payment option whenever the return net amount is greater than zero.
 *
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} payments
 * @param {number|string} netAmount
 * @returns {string|null}
 */
export function saleReturnPaymentRequiredError(payments, netAmount) {
    const net = Math.max(0, parseFloat(netAmount) || 0);

    if (net > 0.009) {
        const hasRefundLine = serializeSalePayments(payments).length > 0;

        if (!hasRefundLine) {
            return 'Select a payment option and enter the refund amount.';
        }
    }

    return splitPaymentValidationError(payments);
}

/**
 * @param {number|string|null|undefined} customerId
 * @returns {string|null}
 */
export function saleCustomerRequiredError(customerId) {
    if (!customerId) {
        return 'Select a customer to complete the sale.';
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
 * @param {number} dueAmount
 * @param {string|null|undefined} dueGivenDate
 * @returns {string|null}
 */
export function dueSaleGivenDateError(dueAmount, dueGivenDate) {
    if (dueAmount <= 0) {
        return null;
    }

    if (!dueGivenDate) {
        return 'Set a due given date for this due amount.';
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
        return [{ payment_account_id: String(paymentAccounts[0].id), amount: '' }];
    }

    return [{ payment_account_id: '', amount: '' }];
}

function collectionAmountForAccount(collectionPayments, accountId) {
    return (collectionPayments ?? [])
        .filter((line) => String(line.payment_account_id ?? '') === String(accountId))
        .reduce((sum, line) => sum + Math.max(0, parseFloat(line.amount) || 0), 0);
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>|null|undefined} salePayments
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>|null|undefined} collectionPayments
 * @param {Array<{ id: number }>} paymentAccounts
 * @returns {{ payments: Array<{ payment_account_id: string, amount: string }>, collectionPayments: Array<{ payment_account_id?: number|string, amount?: number|string }> }}
 */
export function buildEditSalePaymentState(salePayments = [], collectionPayments = [], paymentAccounts = []) {
    const collections = collectionPayments ?? [];
    const collectionTotalByAccount = new Map();

    collections.forEach((line) => {
        const accountId = String(line.payment_account_id ?? '');
        const amount = parseFloat(line.amount) || 0;

        if (accountId && amount > 0) {
            collectionTotalByAccount.set(accountId, (collectionTotalByAccount.get(accountId) || 0) + amount);
        }
    });

    const saleLines = (salePayments ?? [])
        .map((line) => ({
            payment_account_id: String(line.payment_account_id ?? ''),
            amount: String(line.amount ?? '0'),
        }))
        .filter((line) => line.payment_account_id);

    if (collectionTotalByAccount.size > 0) {
        const editablePayments = saleLines.filter((line) => {
            const saleAmount = parseFloat(line.amount) || 0;
            const collectionAmount = collectionTotalByAccount.get(line.payment_account_id) || 0;

            return !(collectionAmount > 0 && saleAmount <= 0);
        });

        if (saleLines.length === 0 || editablePayments.length > 0) {
            return {
                payments: editablePayments,
                collectionPayments: collections,
            };
        }

        return {
            payments: [],
            collectionPayments: collections,
        };
    }

    return {
        payments: buildInitialSalePayments(salePayments, paymentAccounts),
        collectionPayments: collections,
    };
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} payments
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} collectionPayments
 */
export function visibleSalePaymentLines(payments, collectionPayments) {
    return (payments ?? []).filter((line) => {
        const accountId = String(line.payment_account_id ?? '');
        const saleAmount = parseFloat(line.amount) || 0;
        const collectionAmount = collectionAmountForAccount(collectionPayments, accountId);

        return !(collectionAmount > 0 && saleAmount <= 0);
    });
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} payments
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} collectionPayments
 */
export function visibleCollectionPaymentLines(payments, collectionPayments) {
    return (collectionPayments ?? []).filter((line) => {
        const accountId = String(line.payment_account_id ?? '');
        const matchingPayment = (payments ?? []).find(
            (payment) => String(payment.payment_account_id ?? '') === accountId,
        );
        const saleAmount = parseFloat(matchingPayment?.amount) || 0;

        return saleAmount <= 0;
    });
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} payments
 * @param {number|string|null|undefined} cashInHandAccountId
 */
export function hasCashPayment(payments, cashInHandAccountId) {
    if (!cashInHandAccountId) {
        return false;
    }

    return (payments ?? []).some((line) => {
        const amount = parseFloat(line.amount || 0);

        return amount > 0 && String(line.payment_account_id) === String(cashInHandAccountId);
    });
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} payments
 * @param {number|string|null|undefined} cashInHandAccountId
 */
export function hasActiveNonCashPayment(payments, cashInHandAccountId) {
    if (!cashInHandAccountId) {
        return false;
    }

    return (payments ?? []).some((line) => {
        const amount = parseFloat(line.amount || 0);

        return amount > 0 && String(line.payment_account_id) !== String(cashInHandAccountId);
    });
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>} payments
 * @param {number|string|null|undefined} cashInHandAccountId
 */
export function isCashOnlyPayment(payments, cashInHandAccountId) {
    if (!cashInHandAccountId) {
        return false;
    }

    const activeLines = (payments ?? []).filter((line) => parseFloat(line.amount || 0) > 0);

    if (activeLines.length === 0) {
        return false;
    }

    return activeLines.every((line) => String(line.payment_account_id) === String(cashInHandAccountId));
}
