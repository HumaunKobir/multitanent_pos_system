export function getProductDisplayName(rawName) {
    const name = String(rawName ?? '—').trim();

    if (!name) {
        return '—';
    }

    return name.split('/')[0].trim() || name;
}

export function getProductNameSuffixCode(rawName) {
    const name = String(rawName ?? '').trim();

    if (!name.includes('/')) {
        return '';
    }

    return name.split('/').slice(1).join('/').trim();
}

export function getVariantDisplayText(item) {
    const variantId = item.variant_id ?? item.variation_id ?? null;

    if (variantId == null || variantId === '') {
        return '';
    }

    const variantLabel = (item.variant?.name ?? item.variant_label ?? item.variation?.variation_data?.label ?? '').trim();
    const variantSku = (item.variant?.sku ?? item.variant_sku ?? item.variation?.sku_code ?? '').trim();

    return variantLabel || variantSku;
}

export function buildPosItemDisplayName(item) {
    const rawName = item.product?.name ?? item.name ?? '—';
    const baseName = getProductDisplayName(rawName);
    const variantText = getVariantDisplayText(item);
    const name = !variantText ? baseName : `${baseName} (Variant: ${variantText})`;

    return item.is_free_row ? `${name} (FREE)` : name;
}

export function buildPosItemCode(item) {
    const rawName = item.product?.name ?? item.name ?? '';
    const nameCode = getProductNameSuffixCode(rawName);

    if (nameCode) {
        return nameCode;
    }

    return (item.product?.code ?? item.product_code ?? '').trim();
}
