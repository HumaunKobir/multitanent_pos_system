export function computeDiscountAmount(type, value, base) {
    const parsedValue = parseFloat(value || 0);
    const parsedBase = parseFloat(base || 0);

    if (!Number.isFinite(parsedValue) || parsedValue <= 0 || parsedBase <= 0) {
        return 0;
    }

    const amount = type === 'percent' ? (parsedBase * parsedValue) / 100 : parsedValue;

    return Math.min(amount, parsedBase);
}

export function findBestSpecialDiscount(specialDiscounts, taxableAmount) {
    const amount = parseFloat(taxableAmount || 0);

    if (!Number.isFinite(amount) || amount <= 0 || !specialDiscounts?.length) {
        return null;
    }

    const matches = specialDiscounts
        .filter((discount) => {
            const min = parseFloat(discount.min_amount ?? 0);
            const max = discount.max_amount !== null && discount.max_amount !== undefined
                ? parseFloat(discount.max_amount)
                : null;

            if (amount < min) {
                return false;
            }

            return max === null || amount <= max;
        })
        .sort((a, b) => parseFloat(b.min_amount ?? 0) - parseFloat(a.min_amount ?? 0));

    return matches[0] ?? null;
}

export function formatDiscountLabel(type, value) {
    const parsed = parseFloat(value || 0);

    if (type === 'percent') {
        return `${parsed.toFixed(2)}%`;
    }

    return `৳${parsed.toFixed(2)}`;
}
