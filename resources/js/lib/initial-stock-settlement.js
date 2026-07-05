export function resolveComboStock(combo, mainInitialStock) {
    if (String(combo?.stock ?? '').trim() !== '') {
        return parseInt(combo.stock, 10) || 0;
    }

    return parseInt(mainInitialStock || 0, 10) || 0;
}

export function calculateInitialStockTotal({
    hasVariations,
    combinations = [],
    mainInitialStock = '',
    mainPurchasePrice = '',
}) {
    const purchasePrice = parseFloat(mainPurchasePrice || 0) || 0;

    if (hasVariations) {
        let total = 0;

        combinations.forEach((combo) => {
            const stock = resolveComboStock(combo, mainInitialStock);

            if (stock <= 0) {
                return;
            }

            const comboPrice = String(combo?.purchase_price ?? '').trim() !== ''
                ? parseFloat(combo.purchase_price) || 0
                : purchasePrice;

            total += stock * comboPrice;
        });

        return total;
    }

    const quantity = parseInt(mainInitialStock || 0, 10) || 0;

    if (quantity <= 0) {
        return 0;
    }

    return quantity * purchasePrice;
}
