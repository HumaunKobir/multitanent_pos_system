import { computeSellNetAmount, computeSellNonCoinDiscount } from '@/lib/pos-discount';

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

    const dueAmount = Math.max(0, netAmount - paidAmount);

    return {
        netAmount,
        nonCoinDiscount,
        coinDiscountAmount,
        promotionDiscountAmount,
        paidAmount,
        dueAmount,
    };
}
