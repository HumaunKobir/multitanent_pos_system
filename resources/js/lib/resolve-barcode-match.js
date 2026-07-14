/**
 * Resolve a scanned barcode / code / SKU to a product (+ optional variation).
 *
 * @param {Array<object>} data
 * @param {string} term
 * @returns {{ product: object, variation: object|null, needsVariantPick?: boolean }|null}
 */
export function resolveBarcodeMatch(data, term) {
    for (const product of data) {
        const barcodeHit = (product.barcodes ?? []).find((barcode) => String(barcode.code) === term);
        if (barcodeHit) {
            if (barcodeHit.product_variation_id) {
                const variation = (product.variations ?? []).find(
                    (item) => String(item.id) === String(barcodeHit.product_variation_id),
                );
                if (variation) {
                    return { product, variation };
                }
            }

            if (!product.has_variations) {
                return { product, variation: null };
            }
        }
    }

    const variationMatch = data
        .flatMap((product) => (product.variations ?? []).map((variation) => ({ product, variation })))
        .find(({ variation }) => String(variation.sku) === term);

    if (variationMatch) {
        return variationMatch;
    }

    const exact = data.find((product) => String(product.code) === term);
    if (exact && !exact.has_variations) {
        return { product: exact, variation: null };
    }

    if (exact?.has_variations) {
        return { product: exact, variation: null, needsVariantPick: true };
    }

    if (data.length === 1 && !data[0].has_variations) {
        return { product: data[0], variation: null };
    }

    if (data.length === 1 && data[0].has_variations) {
        return { product: data[0], variation: null, needsVariantPick: true };
    }

    return null;
}
