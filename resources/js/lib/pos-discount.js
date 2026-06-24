export function computeDiscountAmount(type, value, base) {
    const parsedValue = parseFloat(value || 0);
    const parsedBase = parseFloat(base || 0);

    if (!Number.isFinite(parsedValue) || parsedValue <= 0 || parsedBase <= 0) {
        return 0;
    }

    const amount = type === 'percent' ? (parsedBase * parsedValue) / 100 : parsedValue;

    return Math.min(amount, parsedBase);
}

export function isSpecialDiscountEligible(discount, taxableAmount) {
    const amount = parseFloat(taxableAmount || 0);

    if (!discount || !Number.isFinite(amount) || amount <= 0) {
        return false;
    }

    const min = parseFloat(discount.min_amount ?? 0);
    const max =
        discount.max_amount !== null && discount.max_amount !== undefined
            ? parseFloat(discount.max_amount)
            : null;

    if (amount < min) {
        return false;
    }

    return max === null || amount <= max;
}

export function filterEligibleSpecialDiscounts(specialDiscounts, taxableAmount) {
    if (!specialDiscounts?.length) {
        return [];
    }

    return specialDiscounts.filter((discount) => isSpecialDiscountEligible(discount, taxableAmount));
}

export function findSpecialDiscountById(specialDiscounts, id) {
    if (!id) {
        return null;
    }

    return specialDiscounts?.find((discount) => String(discount.id) === String(id)) ?? null;
}

export function findBestSpecialDiscount(specialDiscounts, taxableAmount) {
    const matches = filterEligibleSpecialDiscounts(specialDiscounts, taxableAmount).sort(
        (a, b) => parseFloat(b.min_amount ?? 0) - parseFloat(a.min_amount ?? 0),
    );

    return matches[0] ?? null;
}

export function computeSellNetAmount({
    grossAmount = 0,
    vat = 0,
    discount = 0,
    specialDiscountAmount = 0,
    coinDiscountAmount = 0,
    roundOffAmount = 0,
    lineDiscountTotal = 0,
} = {}) {
    return (
        parseFloat(grossAmount ?? 0) +
        parseFloat(vat ?? 0) -
        parseFloat(discount ?? 0) -
        parseFloat(specialDiscountAmount ?? 0) -
        parseFloat(coinDiscountAmount ?? 0) -
        parseFloat(roundOffAmount ?? 0) -
        parseFloat(lineDiscountTotal ?? 0)
    );
}

export function formatDiscountLabel(type, value) {
    const parsed = parseFloat(value || 0);

    if (type === 'percent') {
        return `${parsed.toFixed(2)}%`;
    }

    return `৳${parsed.toFixed(2)}`;
}
