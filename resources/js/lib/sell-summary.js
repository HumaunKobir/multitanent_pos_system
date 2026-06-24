import { computeSellIndexDiscountTotal, computeSellNetAmount, computeSellNonCoinDiscount } from '@/lib/pos-discount';

export function buildSellRowSummary(row) {
    const grossAmount = parseFloat(row.gross_amount ?? 0);
    const vat = parseFloat(row.vat ?? 0);
    const discount = parseFloat(row.discount ?? 0);
    const specialDiscountAmount = parseFloat(row.special_discount_amount ?? 0);
    const promotionDiscountAmount = parseFloat(row.promotion_discount_total ?? 0);
    const coinDiscountAmount = parseFloat(row.coin_discount_amount ?? 0);
    const roundOffAmount = parseFloat(row.round_off_amount ?? 0);
    const lineDiscountTotal = parseFloat(row.line_discount_total ?? 0);
    const paidAmount = parseFloat(row.paid_amount ?? 0);

    const netAmount = computeSellNetAmount({
        grossAmount,
        vat,
        discount,
        specialDiscountAmount,
        coinDiscountAmount,
        roundOffAmount,
        lineDiscountTotal,
    });

    const nonCoinDiscount = computeSellNonCoinDiscount({
        discount,
        specialDiscountAmount,
        promotionDiscountAmount,
        roundOffAmount,
        lineDiscountTotal,
    });

    const indexDiscountTotal = computeSellIndexDiscountTotal({
        discount,
        specialDiscountAmount,
        promotionDiscountAmount,
        coinDiscountAmount,
        roundOffAmount,
        lineDiscountTotal,
    });

    const dueAmount = Math.max(0, netAmount - paidAmount);

    return {
        netAmount,
        nonCoinDiscount,
        indexDiscountTotal,
        coinDiscountAmount,
        promotionDiscountAmount,
        paidAmount,
        dueAmount,
    };
}

export function resolveSellEditAccess({ netAmount, paidAmount, dueAmount } = {}) {
    const net = parseFloat(netAmount ?? 0);
    const paid = parseFloat(paidAmount ?? 0);
    const due = dueAmount != null ? parseFloat(dueAmount) : Math.max(0, net - paid);
    const isFullyPaid = due <= 0.009;
    const isPartiallyPaid = paid > 0.009 && due > 0.009;

    return {
        canEdit: !isFullyPaid,
        paymentOnlyEdit: isPartiallyPaid,
    };
}
