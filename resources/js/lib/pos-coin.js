/**
 * @param {object|null|undefined} settings
 * @returns {boolean}
 */
export function isCoinSystemActive(settings) {
    return Boolean(settings?.enabled);
}

/**
 * @param {number|string} coinsToRedeem
 * @param {object|null|undefined} settings
 * @param {number|string} netBeforeCoin
 * @returns {number}
 */
export function computeCoinDiscount(coinsToRedeem, settings, netBeforeCoin) {
    const coins = Math.max(0, parseFloat(coinsToRedeem) || 0);
    const net = Math.max(0, parseFloat(netBeforeCoin) || 0);
    const coinValue = parseFloat(settings?.coin_value) || 0;

    if (!isCoinSystemActive(settings) || coins <= 0 || coinValue <= 0) {
        return 0;
    }

    return Math.min(coins * coinValue, net);
}

/**
 * @param {number|string} paidBase
 * @param {object|null|undefined} settings
 * @returns {number}
 */
export function computeCoinsEarned(paidBase, settings) {
    const paid = Math.max(0, parseFloat(paidBase) || 0);
    const spendAmount = parseFloat(settings?.earn_spend_amount) || 0;
    const earnCoins = parseFloat(settings?.earn_coins) || 0;

    if (!isCoinSystemActive(settings) || paid <= 0 || spendAmount <= 0 || earnCoins <= 0) {
        return 0;
    }

    return Math.floor(paid / spendAmount) * earnCoins;
}

/**
 * @param {number|string} balance
 * @param {object|null|undefined} settings
 * @param {number|string} netBeforeCoin
 * @returns {number}
 */
export function resolveEffectiveCoinsRedeemed(coinsRedeemed, maxRedeemable, deferClamp = false) {
    const parsed = Math.max(0, parseFloat(coinsRedeemed) || 0);

    if (deferClamp) {
        return parsed;
    }

    return Math.min(parsed, maxRedeemable);
}

export function maxRedeemableCoins(balance, settings, netBeforeCoin) {
    const available = Math.max(0, parseFloat(balance) || 0);
    const net = Math.max(0, parseFloat(netBeforeCoin) || 0);
    const coinValue = parseFloat(settings?.coin_value) || 0;
    const maxPercent = parseFloat(settings?.max_redeem_percent) || 0;

    if (!isCoinSystemActive(settings) || available <= 0 || net <= 0 || coinValue <= 0) {
        return 0;
    }

    const billCap = Math.floor(net / coinValue);
    const percentCap = Math.floor((net * (maxPercent / 100)) / coinValue);

    return Math.max(0, Math.min(available, billCap, percentCap));
}

/**
 * @param {object|null|undefined} settings
 * @param {Date|string|number} [fromDate]
 * @returns {Date|null}
 */
export function computeCoinsExpireAt(settings, fromDate = new Date()) {
    const value = parseInt(settings?.expiry_value, 10);
    const unit = settings?.expiry_unit;

    if (!Number.isFinite(value) || value < 1 || !unit) {
        return null;
    }

    const from = fromDate instanceof Date ? new Date(fromDate.getTime()) : new Date(fromDate);

    if (Number.isNaN(from.getTime())) {
        return null;
    }

    if (unit === 'day') {
        from.setDate(from.getDate() + value);
    } else if (unit === 'week') {
        from.setDate(from.getDate() + value * 7);
    } else if (unit === 'month') {
        from.setMonth(from.getMonth() + value);
    } else if (unit === 'year') {
        from.setFullYear(from.getFullYear() + value);
    } else {
        return null;
    }

    return from;
}

/**
 * @param {Date|null|undefined} date
 * @returns {string}
 */
export function formatCoinsExpireAt(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * @param {object} params
 * @param {number|string} params.balance
 * @param {number|string} params.coinsToRedeem
 * @param {number|string} params.netBeforeCoin
 * @param {number|string} params.earnBase
 * @param {object|null|undefined} params.settings
 * @param {boolean} params.isWalkIn
 * @param {Date|string|number} [params.fromDate]
 * @returns {{
 *   coinDiscount: number,
 *   coinsEarned: number,
 *   remainingBalance: number,
 *   maxRedeemable: number,
 *   expiresAt: Date|null
 * }}
 */
export function previewCoinSale({
    balance = 0,
    coinsToRedeem = 0,
    netBeforeCoin = 0,
    earnBase = 0,
    settings = null,
    isWalkIn = false,
    fromDate = new Date(),
} = {}) {
    if (isWalkIn || !isCoinSystemActive(settings)) {
        return {
            coinDiscount: 0,
            coinsEarned: 0,
            remainingBalance: Math.max(0, parseFloat(balance) || 0),
            maxRedeemable: 0,
            expiresAt: null,
        };
    }

    const maxRedeemable = maxRedeemableCoins(balance, settings, netBeforeCoin);
    const redeemed = Math.min(Math.max(0, parseFloat(coinsToRedeem) || 0), maxRedeemable);
    const coinDiscount = computeCoinDiscount(redeemed, settings, netBeforeCoin);
    const coinsEarned = computeCoinsEarned(earnBase, settings);
    const remainingBalance = Math.max(0, (parseFloat(balance) || 0) - redeemed + coinsEarned);

    return {
        coinDiscount,
        coinsEarned,
        remainingBalance,
        maxRedeemable,
        expiresAt: coinsEarned > 0 ? computeCoinsExpireAt(settings, fromDate) : null,
    };
}
