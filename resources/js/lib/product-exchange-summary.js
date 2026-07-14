import { computeDiscountAmount, findSpecialDiscountById, isSpecialDiscountEligible } from '@/lib/pos-discount';
import { applyPromotionsToCart, remainingQuantityKeepsPromotion } from '@/lib/pos-promotion';
import { computeCoinDiscount, maxRedeemableCoins, resolveEffectiveCoinsRedeemed } from '@/lib/pos-coin';
import { derivedVatPercent } from '@/lib/sale-return-summary';

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
        product_id: item.product_id ?? item.new_product_id,
        variation_id: item.variation_id ?? item.new_variation_id,
        category_id: item.category_id,
        brand_id: item.brand_id,
        quantity: qty,
        unit_price: parseFloat(item.unit_price ?? item.new_unit_price ?? 0),
        original_unit_price: parseFloat(item.unit_price ?? item.new_unit_price ?? 0),
    };
    const result = applyPromotionsToCart([cartItem], promotions, saleDate);

    return parseFloat(result.items[0]?.promotion_discount || 0);
}

function proportionalPromotionDiscount(sellLine, item, qty) {
    const soldQty = parseFloat(sellLine?.sold_quantity ?? item.sold_quantity ?? 0);
    const originalPromo = parseFloat(sellLine?.promotion_discount ?? item.promotion_discount ?? 0);

    if (soldQty <= 0 || qty <= 0 || originalPromo <= 0) {
        return 0;
    }

    const proportion = Math.min(1, qty / soldQty);

    return Math.round(originalPromo * proportion * 100) / 100;
}

function resolveLinePromo(item, sellLine, promotions, saleDate) {
    const newProductId = Number(item.new_product_id);
    const oldProductId = Number(sellLine?.product_id ?? item.old_product_id ?? 0);
    const newVariationId = item.new_variation_id ? Number(item.new_variation_id) : null;
    const oldVariationId = sellLine?.variation_id ? Number(sellLine.variation_id) : null;
    const isSameProduct =
        newProductId === oldProductId && (newVariationId ?? 0) === (oldVariationId ?? 0);
    const qty = parseFloat(item.quantity || 0);
    const catalogPrice = parseFloat(item.new_unit_price || 0);

    if (!newProductId || qty <= 0) {
        return null;
    }

    if (isSameProduct && parseFloat(sellLine?.promotion_discount || item.promotion_discount || 0) > 0) {
        const promotion =
            findPromotion(promotions, sellLine?.promotion_id ?? item.promotion_id) ??
            item.promotion_details ??
            null;

        if (remainingQuantityKeepsPromotion(promotion, qty)) {
            const promotionDiscount = proportionalPromotionDiscount(sellLine, item, qty);
            const unitPrice =
                qty > 0
                    ? Math.round((catalogPrice - promotionDiscount / qty) * 100) / 100
                    : catalogPrice;
            const storedPromo = findPromotion(
                promotions,
                sellLine?.promotion_id ?? item.promotion_id,
            );

            return {
                product_id: newProductId,
                unit_price: unitPrice,
                promotion_discount: promotionDiscount,
                promotion_id: sellLine?.promotion_id ?? item.promotion_id ?? null,
                promotion_label: storedPromo?.name ?? promotion?.name ?? null,
                free_quantity: 0,
            };
        }

        return {
            product_id: newProductId,
            unit_price: catalogPrice,
            promotion_discount: 0,
            promotion_id: null,
            promotion_label: null,
            free_quantity: 0,
        };
    }

    if (isSameProduct) {
        return {
            product_id: newProductId,
            unit_price: catalogPrice,
            promotion_discount: 0,
            promotion_id: null,
            promotion_label: null,
            free_quantity: 0,
        };
    }

    const cartItem = {
        product_id: newProductId,
        variation_id: newVariationId,
        category_id: item.category_id,
        brand_id: item.brand_id,
        quantity: qty,
        unit_price: catalogPrice,
        original_unit_price: catalogPrice,
    };
    const result = applyPromotionsToCart([cartItem], promotions, saleDate);

    return result.items[0] ?? null;
}

function resolveLineDiscount(item, sellLine) {
    const newProductId = Number(item.new_product_id);
    const oldProductId = Number(sellLine?.product_id ?? item.old_product_id ?? 0);
    const newVariationId = item.new_variation_id ? Number(item.new_variation_id) : null;
    const oldVariationId = sellLine?.variation_id ? Number(sellLine.variation_id) : null;

    if (newProductId !== oldProductId || (newVariationId ?? 0) !== (oldVariationId ?? 0)) {
        return 0;
    }

    const soldQty = parseFloat(sellLine?.sold_quantity ?? sellLine?.quantity ?? item.sold_quantity ?? 0);
    const exchangeQty = parseFloat(item.quantity || 0);

    if (soldQty <= 0 || exchangeQty <= 0) {
        return 0;
    }

    const originalDiscount = parseFloat(sellLine?.line_discount ?? item.line_discount ?? 0);
    const proportion = Math.min(1, exchangeQty / soldQty);

    return originalDiscount * proportion;
}

function previewExchangeItem(item) {
    const qty = parseFloat(item.quantity || 0);

    if (qty <= 0) {
        return null;
    }

    if (item.new_product_id) {
        return { item };
    }

    const unitPrice = parseFloat(item.new_unit_price || item.old_unit_price || 0);

    if (unitPrice <= 0) {
        return null;
    }

    return {
        item: {
            ...item,
            new_product_id: item.old_product_id,
            new_variation_id: item.old_variation_id ?? item.new_variation_id ?? null,
            new_unit_price: String(unitPrice),
        },
    };
}

export function resolveExchangeSettlement(netNew, oldNet) {
    const diff = Math.abs(parseFloat(netNew || 0) - parseFloat(oldNet || 0));

    return diff < 0.01 ? 0 : diff;
}

export function resolveSignedExchangeSettlement(netNew, oldNet) {
    const net = parseFloat(netNew || 0);
    const old = parseFloat(oldNet || 0);

    if (Math.abs(net - old) < 0.01) {
        return 0;
    }

    return net > old ? net - old : -(old - net);
}

/**
 * Settlement compares catalog gross totals. VAT is excluded from the payment/refund
 * difference; invoice, round off, and other gross-level discounts reduce the new side.
 */
export function resolveGrossBasedSignedSettlement(
    oldGross,
    newGross,
    invoiceDiscount = 0,
    roundOffAmount = 0,
    lineDiscountTotal = 0,
    specialDiscountAmount = 0,
    coinDiscountAmount = 0,
) {
    const old = parseFloat(oldGross || 0);
    const grossDiff = Math.round((parseFloat(newGross || 0) - old) * 100) / 100;

    if (Math.abs(grossDiff) < 0.01) {
        return 0;
    }

    const discountOnGross = Math.round(
        (parseFloat(invoiceDiscount || 0) +
            parseFloat(roundOffAmount || 0) +
            parseFloat(lineDiscountTotal || 0) +
            parseFloat(specialDiscountAmount || 0) +
            parseFloat(coinDiscountAmount || 0)) *
            100,
    ) / 100;

    return Math.round((parseFloat(newGross || 0) - discountOnGross - old) * 100) / 100;
}

export function resolveSoldLineTotal(items = []) {
    return items.reduce(
        (sum, item) =>
            sum +
            parseFloat(item.sold_quantity || 0) *
                parseFloat(item.original_old_unit_price ?? (item.old_unit_price || 0)),
        0,
    );
}

export function resolveOldExchangeTotal(items = []) {
    return items.reduce(
        (sum, item) =>
            sum +
            parseFloat(item.quantity || 0) *
                parseFloat(item.original_old_unit_price ?? (item.old_unit_price || 0)),
        0,
    );
}

/**
 * Proportional share of the promotion the customer originally received on the
 * swapped-out units. The old side of the settlement must be valued at what the
 * customer actually paid (catalog minus their original promotion), mirroring the
 * promotion-adjusted new side, otherwise a like-for-like swap of a promoted item
 * shows a phantom refund.
 */
export function resolveOldPromotionTotal(items = []) {
    return items.reduce((sum, item) => {
        const qty = parseFloat(item.quantity || 0);
        const soldQty = parseFloat(item.sold_quantity || 0);
        const originalPromo = parseFloat(item.promotion_discount || 0);

        if (qty <= 0 || soldQty <= 0 || originalPromo <= 0) {
            return sum;
        }

        const proportion = Math.min(1, qty / soldQty);

        return sum + Math.round(originalPromo * proportion * 100) / 100;
    }, 0);
}

export function resolveReturnTotal(items = []) {
    return items.reduce(
        (sum, item) =>
            sum +
            parseFloat(item.return_quantity || 0) *
                parseFloat(item.original_old_unit_price ?? (item.old_unit_price || 0)),
        0,
    );
}

export function resolveExchangeCatalogProportion(oldExchangeTotal, parentCatalogGross) {
    const parentGross = parseFloat(parentCatalogGross || 0);
    const oldGross = parseFloat(oldExchangeTotal || 0);

    if (parentGross <= 0) {
        return 1;
    }

    return Math.min(1, oldGross / parentGross);
}

export function resolveOldNetTotal(sellDiscounts, oldTotal, sourceItems = []) {
    const parentNet = parseFloat(sellDiscounts?.net_amount || 0);
    const parentCatalogGross = parseFloat(sellDiscounts?.gross_amount || 0);
    const old = parseFloat(oldTotal || 0);

    if (parentCatalogGross <= 0) {
        return old;
    }

    return Math.round((old / parentCatalogGross) * parentNet * 100) / 100;
}

export function resolveCustomerAccountEffect(netNew, oldNet) {
    return Math.round((parseFloat(netNew || 0) - parseFloat(oldNet || 0)) * 100) / 100;
}

export function buildInitialExchangeDiscounts(sellDiscounts = {}) {
    const sd = sellDiscounts ?? {};
    const invoiceType = sd.invoice_discount_type || 'flat';
    const rawValue = parseFloat(sd.invoice_discount_value ?? 0);
    const invoiceValue = rawValue > 0 ? rawValue : parseFloat(sd.invoice_discount || 0);
    const roundOff = parseFloat(sd.round_off_amount || 0);

    return {
        invoiceType,
        invoice: invoiceValue > 0 ? String(invoiceValue) : '',
        specialDiscountId: sd.special_discount_id ? String(sd.special_discount_id) : '',
        roundOff: roundOff > 0 ? String(roundOff.toFixed(2)) : '',
        coinsRedeemed: parseFloat(sd.coins_redeemed || 0) > 0 ? String(sd.coins_redeemed) : '',
    };
}

/**
 * Restore editable discount inputs on the exchange edit page.
 * Starts from the source sale defaults (like create) and overlays saved exchange overrides.
 * Round off is stored on the exchange as an applied amount, so recover the raw input.
 */
export function buildEditExchangeDiscounts(exchange = {}, sellDiscounts = {}, sourceItems = []) {
    const base = buildInitialExchangeDiscounts(sellDiscounts);

    if (exchange.discount_type) {
        base.invoiceType = exchange.discount_type;
    }

    if (exchange.discount_value != null) {
        const discountValue = parseFloat(exchange.discount_value || 0);
        base.invoice = discountValue > 0 ? String(exchange.discount_value) : '';
    }

    if (exchange.special_discount_id) {
        base.specialDiscountId = String(exchange.special_discount_id);
    } else if (parseFloat(exchange.special_discount_amount || 0) <= 0) {
        base.specialDiscountId = '';
    }

    const appliedRoundOff = parseFloat(exchange.round_off_amount || 0);
    if (appliedRoundOff <= 0) {
        base.roundOff = '';
    } else {
        // Exchange stores the applied (proportioned) amount. Always recover the
        // raw input the user can edit — do not prefer the parent sale's raw
        // value, or a custom round-off override from create is lost on edit.
        const parentGross = parseFloat(sellDiscounts?.gross_amount || 0);
        const oldExchangeTotal = resolveOldExchangeTotal(sourceItems);
        const proportion = resolveExchangeCatalogProportion(oldExchangeTotal, parentGross);
        const rawRoundOff =
            proportion > 0.0001
                ? Math.round((appliedRoundOff / proportion) * 100) / 100
                : appliedRoundOff;
        base.roundOff = rawRoundOff > 0 ? String(rawRoundOff.toFixed(2)) : '';
    }

    if (parseFloat(exchange.coins_redeemed || 0) > 0) {
        base.coinsRedeemed = String(exchange.coins_redeemed);
    } else if (parseFloat(exchange.coin_discount_amount || 0) <= 0) {
        base.coinsRedeemed = '';
    }

    return base;
}

/**
 * Keep recorded refund/payment amounts on edit unless settlement changes for a
 * previously fully-settled exchange, or paid exceeds the new settlement.
 */
export function syncExchangeEditPaidAmount({
    currentPaid,
    previousSettlement,
    nextSettlement,
    skipAutoFill = false,
}) {
    const paid = parseFloat(currentPaid || 0);
    const prev = parseFloat(previousSettlement || 0);
    const next = parseFloat(nextSettlement || 0);

    if (skipAutoFill) {
        if (paid > next + 0.009) {
            return next > 0.009 ? next.toFixed(2) : '0';
        }

        return null;
    }

    if (prev > 0.009 && Math.abs(paid - prev) < 0.01) {
        return next > 0.009 ? next.toFixed(2) : '0';
    }

    if (paid > next + 0.009) {
        return next > 0.009 ? next.toFixed(2) : '0';
    }

    return null;
}

export function calcProductExchangeSummary({
    items = [],
    sellDiscounts = null,
    sourceItems = [],
    promotions = [],
    saleDate = null,
    manualDiscounts = {},
    specialDiscounts = [],
    coinSettings = null,
    coinBalanceOffset = 0,
    customerBalance = 0,
}) {
    if (!sellDiscounts) {
        return null;
    }

    const sourceMap = Object.fromEntries(
        (sourceItems ?? []).map((line) => [Number(line.sell_product_id), line]),
    );

    let grossAmount = 0;
    let lineDiscountTotal = 0;
    let promotionDiscountTotal = 0;
    const promoLines = {};

    items.forEach((item) => {
        const resolved = previewExchangeItem(item);

        if (!resolved) {
            return;
        }

        const { item: workingItem } = resolved;
        const sellLine = sourceMap[Number(item.sell_product_id)] ?? item;

        const promoLine = resolveLinePromo(workingItem, sellLine, promotions, saleDate);
        promoLines[Number(item.sell_product_id)] = promoLine;

        const catalogPrice = parseFloat(workingItem.new_unit_price || 0);
        const effectivePrice = promoLine
            ? Math.min(catalogPrice, parseFloat(promoLine.unit_price || 0))
            : catalogPrice;
        const qty = parseFloat(workingItem.quantity || 0);
        const lineGross = qty * effectivePrice;
        const lineDiscount = Math.min(resolveLineDiscount(workingItem, sellLine), lineGross);

        grossAmount += lineGross;
        lineDiscountTotal += lineDiscount;
        promotionDiscountTotal += parseFloat(promoLine?.promotion_discount || 0);
    });

    const taxableBase = Math.max(0, grossAmount - lineDiscountTotal);
    const invoiceType = manualDiscounts.invoiceType || 'flat';
    const parentCatalogGross = parseFloat(sellDiscounts?.gross_amount || 0);
    const soldLineTotal = resolveSoldLineTotal(items);
    const oldExchangeTotal = resolveOldExchangeTotal(items);
    const exchangeProportion = resolveExchangeCatalogProportion(oldExchangeTotal, parentCatalogGross);
    const invoiceDiscountAmount =
        invoiceType === 'flat'
            ? Math.min(
                  parseFloat(manualDiscounts.invoice || 0) * exchangeProportion,
                  taxableBase,
              )
            : computeDiscountAmount(invoiceType, manualDiscounts.invoice || 0, taxableBase);

    const specialDiscount = findSpecialDiscountById(
        specialDiscounts,
        manualDiscounts.specialDiscountId,
    );
    const specialDiscountAmount =
        specialDiscount && isSpecialDiscountEligible(specialDiscount, taxableBase)
            ? computeDiscountAmount(
                  specialDiscount.discount_type,
                  specialDiscount.discount_value,
                  taxableBase,
              )
            : 0;

    const afterCommercialDiscounts = Math.max(0, taxableBase - invoiceDiscountAmount - specialDiscountAmount);

    const effectiveBalance = Math.max(0, parseFloat(customerBalance || 0) + parseFloat(coinBalanceOffset || 0));
    const maxRedeemable = coinSettings?.enabled
        ? maxRedeemableCoins(effectiveBalance, coinSettings, afterCommercialDiscounts)
        : 0;
    const coinsRedeemed = resolveEffectiveCoinsRedeemed(
        manualDiscounts.coinsRedeemed ?? '',
        maxRedeemable,
        false,
    );
    const coinDiscountAmount = coinSettings?.enabled
        ? computeCoinDiscount(coinsRedeemed, coinSettings, afterCommercialDiscounts)
        : 0;

    const vatPercent = derivedVatPercent(sellDiscounts);
    const vatBase = Math.max(0, afterCommercialDiscounts - coinDiscountAmount);
    const vatAmount = vatPercent > 0 ? Math.round(vatBase * (vatPercent / 100) * 100) / 100 : 0;
    const beforeRoundOff = vatBase + vatAmount;
    const roundOffAmount = Math.min(
        Math.max(0, parseFloat(manualDiscounts.roundOff || 0)) * exchangeProportion,
        beforeRoundOff,
    );
    const netNewAmount = Math.max(0, beforeRoundOff - roundOffAmount);

    const oldTotal = oldExchangeTotal;
    const oldPromotionTotal = resolveOldPromotionTotal(items);
    const oldSettlementTotal = Math.max(0, oldTotal - oldPromotionTotal);
    const oldNetTotal = resolveOldNetTotal(sellDiscounts, oldTotal);
    const returnTotal = resolveReturnTotal(items);
    const returnRefund = returnTotal > 0 ? resolveOldNetTotal(sellDiscounts, returnTotal) : 0;
    const grossPriceDifference = grossAmount - oldSettlementTotal;
    const newDiscountTotal = Math.max(0, grossAmount + vatAmount - netNewAmount);
    const priceDifference = grossPriceDifference;
    const swapSignedSettlement = resolveGrossBasedSignedSettlement(
        oldSettlementTotal,
        grossAmount,
        invoiceDiscountAmount,
        roundOffAmount,
        lineDiscountTotal,
        specialDiscountAmount,
        coinDiscountAmount,
    );
    const signedSettlement = Math.round((swapSignedSettlement - returnRefund) * 100) / 100;
    const settlementAmount = Math.abs(signedSettlement) < 0.01 ? 0 : Math.abs(signedSettlement);
    const customerAccountEffect = Math.round(signedSettlement * 100) / 100;

    return {
        grossAmount,
        lineDiscountTotal,
        promotionDiscountTotal,
        taxableBase,
        invoiceDiscountAmount,
        specialDiscountAmount,
        vatPercent,
        vatAmount,
        netBeforeCoin: afterCommercialDiscounts,
        coinsRedeemed,
        maxRedeemable,
        coinDiscountAmount,
        roundOffAmount,
        netNewAmount,
        oldTotal,
        soldLineTotal,
        oldExchangeTotal,
        returnTotal,
        returnRefund,
        exchangeProportion,
        oldNetTotal,
        grossPriceDifference,
        newDiscountTotal,
        priceDifference,
        swapSignedSettlement,
        settlementAmount,
        signedSettlement,
        customerAccountEffect,
        promoLines,
    };
}
