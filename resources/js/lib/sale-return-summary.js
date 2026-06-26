import { computeCoinDiscount, maxRedeemableCoins } from '@/lib/pos-coin';
import { computeDiscountAmount } from '@/lib/pos-discount';
import { applyPromotionsToCart, remainingQuantityKeepsPromotion } from '@/lib/pos-promotion';

function parentNetBeforeCoin(sellDiscounts) {
    const sd = sellDiscounts;

    return (
        parseFloat(sd.gross_amount || 0) +
        parseFloat(sd.vat || 0) -
        parseFloat(sd.invoice_discount || 0) -
        parseFloat(sd.special_discount_amount || 0) -
        parseFloat(sd.round_off_amount || 0) -
        parseFloat(sd.line_discount_total || 0)
    );
}

function findPromotion(promotions, promotionId) {
    if (promotionId === null || promotionId === undefined || promotionId === '') {
        return null;
    }

    return promotions.find((promotion) => Number(promotion.id) === Number(promotionId)) ?? null;
}

function promotionDiscountAtQuantity(item, qty, promotions, saleDate) {
    if (qty <= 0) {
        return 0;
    }

    const cartItem = {
        product_id: item.product_id,
        variation_id: item.variation_id,
        category_id: item.category_id,
        brand_id: item.brand_id,
        quantity: qty,
        unit_price: parseFloat(item.unit_price || 0),
        original_unit_price: parseFloat(item.unit_price || 0),
    };
    const result = applyPromotionsToCart([cartItem], promotions, saleDate);

    return parseFloat(result.items[0]?.promotion_discount || 0);
}

function promotionClawback(item, returnQty, promotions, saleDate) {
    const soldQty = parseFloat(item.sold_quantity || 0);
    const original = parseFloat(item.promotion_discount || 0);
    const retQty = parseFloat(returnQty || 0);

    if (original <= 0 || retQty <= 0) {
        return 0;
    }

    const remaining = Math.max(0, soldQty - retQty);

    // Try active promotions list first, then fall back to embedded details from the sale line.
    // The fallback handles expired promotions that are no longer in the active list.
    const promotion = findPromotion(promotions, item.promotion_id) ?? item.promotion_details ?? null;

    // If remaining qty still qualifies for the promotion, no clawback needed.
    if (remainingQuantityKeepsPromotion(promotion, remaining)) {
        return 0;
    }

    // For promotions with a min_qty threshold: only claw back when the return qty itself
    // meets or exceeds the minimum. Returning fewer items than the threshold means the
    // returned portion wasn't individually responsible for triggering the promotion discount.
    const minQty = promotion?.min_qty != null ? parseFloat(promotion.min_qty) : null;
    if (minQty != null && minQty > 0 && retQty < minQty) {
        return 0;
    }

    if (!promotion && promotionDiscountAtQuantity(item, remaining, promotions, saleDate) > 0) {
        return 0;
    }

    return original;
}

function coinDiscountForPortion(sellDiscounts, coinSettings, netBeforeCoin, parentNetBeforeCoin) {
    if (netBeforeCoin <= 0.009 || parentNetBeforeCoin <= 0.009) {
        return 0;
    }

    const parentCoin = parseFloat(sellDiscounts.coin_discount_amount || 0);
    const parentCoins = parseFloat(sellDiscounts.coins_redeemed || 0);
    const proportion = netBeforeCoin / parentNetBeforeCoin;
    const coinsForPortion = parentCoins * proportion;

    if (!coinSettings?.enabled || parentCoin <= 0.009) {
        return parentCoin * proportion;
    }

    const maxRedeemable = maxRedeemableCoins(parentCoins, coinSettings, netBeforeCoin);
    const allowedCoins = Math.min(coinsForPortion, maxRedeemable);

    return computeCoinDiscount(allowedCoins, coinSettings, netBeforeCoin);
}

function coinDiscountClawback(sellDiscounts, coinSettings, returnNetBeforeCoin) {
    const parentCoin = parseFloat(sellDiscounts.coin_discount_amount || 0);

    if (parentCoin <= 0.009) {
        return 0;
    }

    const parentNet = parentNetBeforeCoin(sellDiscounts);
    const remainingNet = Math.max(0, parentNet - returnNetBeforeCoin);
    const remainingCoin = coinDiscountForPortion(sellDiscounts, coinSettings, remainingNet, parentNet);

    return Math.max(0, parentCoin - remainingCoin);
}

export function calcSaleReturnSummary(lines, sellDiscounts, context = {}, manualOverrides = {}) {
    const sd = sellDiscounts;
    const { promotions = [], saleDate = null, coinSettings = null, specialDiscounts = [] } = context;
    const parentGross = parseFloat(sd.gross_amount || 0);
    const parentLineDiscount = parseFloat(sd.line_discount_total || 0);
    const parentNetForProportion = parentGross - parentLineDiscount;

    const grossAmount = lines.reduce(
        (s, it) => s + parseFloat(it.quantity || 0) * parseFloat(it.unit_price || 0),
        0,
    );
    const returnLineDiscount = lines.reduce((s, it) => {
        const soldQty = parseFloat(it.sold_quantity || 0);
        const returnQty = parseFloat(it.quantity || 0);
        return s + (soldQty > 0 ? parseFloat(it.line_discount || 0) * (returnQty / soldQty) : 0);
    }, 0);
    const returnPromotionDiscount = lines.reduce(
        (s, it) => s + promotionClawback(it, parseFloat(it.quantity || 0), promotions, saleDate),
        0,
    );

    const returnAfterPromo = grossAmount - returnLineDiscount - returnPromotionDiscount;
    const proportion = parentNetForProportion > 0 ? returnAfterPromo / parentNetForProportion : 0;

    // Auto-calculated proportional values
    const autoInvoice = proportion * parseFloat(sd.invoice_discount || 0);
    const autoSpecial = proportion * parseFloat(sd.special_discount_amount || 0);
    const autoRoundOff = proportion * parseFloat(sd.round_off_amount || 0);

    // invoice/roundOff: null → auto proportional; '' or number → manual ('' treated as 0 via `|| 0` fallback)
    // specialDiscountId: undefined → auto proportional; null → 0 (none); string id → compute from discount
    // coin: null or '' → auto clawback; number → manual
    const returnInvoiceDiscount =
        manualOverrides.invoice != null
            ? Math.min(Math.max(0, parseFloat(manualOverrides.invoice || 0)), parseFloat(sd.invoice_discount || 0))
            : autoInvoice;
    const returnSpecialDiscount = (() => {
        const sdId = manualOverrides.specialDiscountId;
        if (sdId === undefined) {
            return autoSpecial;
        }
        if (!sdId) {
            return 0;
        }
        const disc = specialDiscounts.find((d) => String(d.id) === String(sdId));
        return disc
            ? Math.min(
                  computeDiscountAmount(disc.discount_type, disc.discount_value, returnAfterPromo),
                  parseFloat(sd.special_discount_amount || 0),
              )
            : 0;
    })();
    const returnRoundOff =
        manualOverrides.roundOff != null
            ? Math.min(Math.max(0, parseFloat(manualOverrides.roundOff || 0)), parseFloat(sd.round_off_amount || 0))
            : autoRoundOff;

    const returnInvoiceLevelDiscount = returnInvoiceDiscount + returnSpecialDiscount + returnRoundOff;
    const returnNetBeforeCoin = Math.max(0, returnAfterPromo - returnInvoiceLevelDiscount);
    const autoCoinDiscount = coinDiscountClawback(sd, coinSettings, returnNetBeforeCoin);
    const returnCoinDiscount =
        manualOverrides.coin != null && manualOverrides.coin !== ''
            ? Math.min(Math.max(0, parseFloat(manualOverrides.coin || 0)), parseFloat(sd.coin_discount_amount || 0))
            : autoCoinDiscount;

    const returnDiscounts = {
        line: returnLineDiscount,
        promotion: returnPromotionDiscount,
        invoice: returnInvoiceDiscount,
        special: returnSpecialDiscount,
        coin: returnCoinDiscount,
        roundOff: returnRoundOff,
    };
    const autoDiscounts = {
        invoice: autoInvoice,
        roundOff: autoRoundOff,
        coin: autoCoinDiscount,
    };

    const discountAmount =
        returnLineDiscount + returnPromotionDiscount + returnInvoiceLevelDiscount + returnCoinDiscount;
    const netAmount = Math.max(0, grossAmount - discountAmount);

    const parentNet = parseFloat(sd.net_amount || 0);
    const parentPaid = parseFloat(sd.paid_amount || 0);
    const parentDue = Math.max(0, parentNet - parentPaid);

    return {
        grossAmount,
        returnLineDiscount,
        returnPromotionDiscount,
        returnInvoiceLevelDiscount,
        returnDiscounts,
        autoDiscounts,
        discountAmount,
        netAmount,
        proportion,
        parentNet,
        parentPaid,
        parentDue,
        suggestedPaid: calcSuggestedReturnPaid(netAmount, sd),
    };
}

export function calcSuggestedReturnPaid(netReturnAmount, sellDiscounts) {
    const parentNet = parseFloat(sellDiscounts?.net_amount || 0);
    const parentPaid = parseFloat(sellDiscounts?.paid_amount || 0);

    if (parentNet <= 0 || netReturnAmount <= 0) {
        return 0;
    }

    return Math.min(netReturnAmount, Math.max(0, (parentPaid / parentNet) * netReturnAmount));
}

/**
 * @param {Array<{ payment_account_id?: number|string, amount?: number|string }>|null|undefined} salePayments
 * @param {number|string} suggestedRefund
 * @param {Array<{ id: number }>} paymentAccounts
 * @returns {Array<{ payment_account_id: string, amount: string }>}
 */
export function buildInitialReturnPayments(salePayments, suggestedRefund, paymentAccounts = []) {
    const refund = Math.max(0, parseFloat(suggestedRefund || 0));

    if (refund <= 0.009) {
        return [{ payment_account_id: paymentAccounts[0] ? String(paymentAccounts[0].id) : '', amount: '0' }];
    }

    const paidLines = (salePayments ?? []).filter((payment) => parseFloat(payment.amount || 0) > 0.009);

    if (paidLines.length === 0) {
        return [
            {
                payment_account_id: paymentAccounts[0] ? String(paymentAccounts[0].id) : '',
                amount: refund.toFixed(2),
            },
        ];
    }

    const totalPaidOnSale = paidLines.reduce((sum, payment) => sum + parseFloat(payment.amount || 0), 0);
    let allocated = 0;

    return paidLines.map((payment, index) => {
        const isLast = index === paidLines.length - 1;
        const amount = isLast
            ? Math.max(0, refund - allocated)
            : Math.round(((refund * parseFloat(payment.amount || 0)) / totalPaidOnSale) * 100) / 100;

        allocated += amount;

        return {
            payment_account_id: String(payment.payment_account_id),
            amount: amount.toFixed(2),
        };
    });
}
