function roundAmount(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function localTodayYmd() {
    const date = new Date();

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/**
 * @param {object|null|undefined} promotion
 * @param {Date|string} at
 * @returns {boolean}
 */
export function isPromotionWithinSchedule(promotion, at = new Date()) {
    if (!promotion) {
        return false;
    }

    const moment = at instanceof Date ? at : new Date(at);
    const startsAt = promotion.starts_at ? new Date(promotion.starts_at) : null;
    const endsAt = promotion.ends_at ? new Date(promotion.ends_at) : null;

    if (startsAt && moment < startsAt) {
        return false;
    }

    if (endsAt && moment > endsAt) {
        return false;
    }

    return true;
}

/**
 * @param {object|null|undefined} promotion
 * @param {string|null|undefined} saleDate Y-m-d
 * @returns {boolean}
 */
export function isPromotionActiveOnSaleDate(promotion, saleDate) {
    if (!promotion) {
        return false;
    }

    const effectiveSaleDate = saleDate || localTodayYmd();

    if (effectiveSaleDate === localTodayYmd()) {
        return isPromotionWithinSchedule(promotion, new Date());
    }

    const dayStart = new Date(`${effectiveSaleDate}T00:00:00`);
    const dayEnd = new Date(`${effectiveSaleDate}T23:59:59.999`);
    const startsAt = promotion.starts_at ? new Date(promotion.starts_at) : null;
    const endsAt = promotion.ends_at ? new Date(promotion.ends_at) : null;

    if (startsAt && startsAt > dayEnd) {
        return false;
    }

    if (endsAt && endsAt < dayStart) {
        return false;
    }

    return true;
}

/**
 * @param {Array<object>} promotions
 * @param {string|null|undefined} saleDate
 * @returns {Array<object>}
 */
export function filterPromotionsForSaleDate(promotions = [], saleDate = null) {
    return promotions.filter((promotion) => isPromotionActiveOnSaleDate(promotion, saleDate));
}

function percentDiscount(base, percent) {
    const amount = (base * percent) / 100;

    return roundAmount(Math.min(amount, base));
}

function matchesPromotion(promotion, item) {
    const targetIds = (promotion.target_ids ?? []).map(Number);
    if (targetIds.length === 0) {
        return false;
    }

    if (promotion.scope === 'product') {
        return targetIds.includes(Number(item.product_id));
    }

    if (promotion.scope === 'category') {
        return targetIds.includes(Number(item.category_id));
    }

    if (promotion.scope === 'brand') {
        return targetIds.includes(Number(item.brand_id));
    }

    return false;
}

function meetsMinQty(promotion, qty) {
    if (promotion.min_qty === null || promotion.min_qty === undefined || promotion.min_qty === '') {
        return true;
    }

    return qty >= parseFloat(promotion.min_qty);
}

function selectPromotionForLine(promotions, item, qty) {
    const linePromotions = promotions.filter((promotion) =>
        ['percent', 'flat', 'fixed_price'].includes(promotion.type),
    );

    const matches = linePromotions
        .filter((promotion) => matchesPromotion(promotion, item) && meetsMinQty(promotion, qty))
        .sort((a, b) => Number(b.priority ?? 0) - Number(a.priority ?? 0));

    if (matches.length === 0) {
        return null;
    }

    const exclusive = matches.find((promotion) => promotion.exclusive);
    return exclusive ?? matches[0];
}

function computePromoUnitPrice(promotion, basePrice, qty) {
    const lineGross = basePrice * qty;
    let discountAmount = 0;

    if (promotion.type === 'percent') {
        discountAmount = percentDiscount(lineGross, parseFloat(promotion.discount_value ?? 0));
    } else if (promotion.type === 'flat') {
        discountAmount = Math.min(parseFloat(promotion.discount_value ?? 0), lineGross);
    } else if (promotion.type === 'fixed_price') {
        discountAmount = Math.max(0, lineGross - parseFloat(promotion.fixed_price ?? 0) * qty);
    }

    const finalLine = Math.max(0, lineGross - discountAmount);
    return roundAmount(finalLine / Math.max(qty, 1));
}

function computeBundleDiscountAmount(promotion, bundleGross) {
    if (promotion.fixed_price !== null && promotion.fixed_price !== undefined) {
        return roundAmount(Math.max(0, bundleGross - parseFloat(promotion.fixed_price)));
    }

    if (parseFloat(promotion.discount_value ?? 0) <= 0) {
        return 0;
    }

    let amount = bundleGross * (parseFloat(promotion.discount_value) / 100);

    return roundAmount(Math.min(amount, bundleGross));
}

function linePhysicalQuantity(item) {
    return parseFloat(item.quantity || 0) + parseFloat(item.free_quantity || 0);
}

function calculateBogoFreeUnits(paidQty, buyQty, getQty) {
    const paid = Number(paidQty);
    const buy = Number(buyQty);
    const get = Number(getQty);

    if (buy <= 0 || get <= 0 || paid < buy) {
        return 0;
    }

    return Math.floor(paid / buy) * get;
}

function selectBogoPromotionForLine(promotions, item, paidQty) {
    const bogoPromotions = promotions.filter((promotion) => promotion.type === 'buy_x_get_y');

    const matches = bogoPromotions
        .filter((promotion) => {
            if (!matchesPromotion(promotion, item)) {
                return false;
            }

            const buyQty = Number(promotion.buy_qty ?? 0);
            const getQty = Number(promotion.get_qty ?? 0);

            return calculateBogoFreeUnits(paidQty, buyQty, getQty) > 0 && meetsMinQty(promotion, paidQty);
        })
        .sort((a, b) => {
            const priorityDiff = Number(b.priority ?? 0) - Number(a.priority ?? 0);
            if (priorityDiff !== 0) {
                return priorityDiff;
            }

            return Number(b.buy_qty ?? 0) - Number(a.buy_qty ?? 0);
        });

    if (matches.length === 0) {
        return null;
    }

    const exclusive = matches.find((promotion) => promotion.exclusive);
    return exclusive ?? matches[0];
}

function prepareItemsForPromotion(items) {
    return items.map((item) => ({
        ...item,
        free_quantity: 0,
    }));
}

function stripPromotionsFromItems(items) {
    return items.map((item) => {
        const catalogPrice = parseFloat(item.original_unit_price ?? item.unit_price ?? 0);

        return {
            ...item,
            original_unit_price: catalogPrice,
            unit_price: catalogPrice,
            promotion_id: null,
            promotion_discount: 0,
            promotion_label: null,
            promotion_meta: null,
            free_quantity: 0,
        };
    });
}

function applyLinePromotions(items, promotions) {
    return items.map((item) => {
        const paidQty = parseFloat(item.quantity || 0);
        const totalQty = linePhysicalQuantity(item);
        const catalogPrice = parseFloat(item.original_unit_price ?? item.unit_price ?? 0);
        const basePrice = parseFloat(item.base_unit_price ?? catalogPrice);
        const promotion = selectPromotionForLine(promotions, item, totalQty);

        const line = {
            ...item,
            original_unit_price: catalogPrice,
            unit_price: catalogPrice,
            promotion_id: null,
            promotion_discount: 0,
            promotion_label: null,
            promotion_meta: null,
        };

        if (!promotion) {
            return line;
        }

        const pricingBase = promotion.stack_with_product_discount ? catalogPrice : basePrice;
        const promoUnitPrice = computePromoUnitPrice(promotion, pricingBase, paidQty);
        const finalUnitPrice = Math.min(catalogPrice, promoUnitPrice);
        const promotionDiscount = roundAmount(Math.max(0, (catalogPrice - finalUnitPrice) * paidQty));

        return {
            ...line,
            unit_price: finalUnitPrice,
            promotion_id: promotion.id,
            promotion_discount: promotionDiscount,
            promotion_label: promotion.name,
            promotion_meta: {
                type: promotion.type,
                stack_with_manual_line_discount: promotion.stack_with_manual_line_discount,
                stack_with_invoice_discount: promotion.stack_with_invoice_discount,
                stack_with_special_discount: promotion.stack_with_special_discount,
            },
        };
    });
}

function applyBuyXGetY(items, promotions) {
    const nextItems = items.map((item) => ({ ...item }));

    nextItems.forEach((item, index) => {
        const paidQty = parseFloat(item.quantity || 0);
        const promotion = selectBogoPromotionForLine(promotions, item, paidQty);

        if (!promotion) {
            return;
        }

        if (item.promotion_id && item.promotion_meta?.type !== 'buy_x_get_y') {
            const existingPromotion = promotions.find((candidate) => Number(candidate.id) === Number(item.promotion_id));

            if (existingPromotion && Number(existingPromotion.priority ?? 0) >= Number(promotion.priority ?? 0)) {
                return;
            }
        }

        const buyQty = Number(promotion.buy_qty ?? 0);
        const getQty = Number(promotion.get_qty ?? 0);
        const freeUnits = calculateBogoFreeUnits(paidQty, buyQty, getQty);

        if (freeUnits <= 0) {
            return;
        }

        const unitPrice = parseFloat(item.unit_price || 0);
        const discountPercent = parseFloat(promotion.get_discount_percent ?? 100);
        const partialFreeDiscount = roundAmount(unitPrice * freeUnits * Math.max(0, (100 - discountPercent) / 100));

        nextItems[index] = {
            ...nextItems[index],
            free_quantity: freeUnits,
            promotion_id: promotion.id,
            promotion_discount: roundAmount(parseFloat(nextItems[index].promotion_discount || 0) + partialFreeDiscount),
            promotion_label: promotion.name,
            promotion_meta: {
                ...(nextItems[index].promotion_meta ?? {}),
                type: 'buy_x_get_y',
                free_units: freeUnits,
                paid_units: paidQty,
                stack_with_manual_line_discount: promotion.stack_with_manual_line_discount,
                stack_with_invoice_discount: promotion.stack_with_invoice_discount,
                stack_with_special_discount: promotion.stack_with_special_discount,
            },
        };
    });

    return nextItems;
}

function applyBundlePromotions(items, promotions) {
    const nextItems = items.map((item) => ({ ...item }));
    const bundlePromotions = promotions.filter((promotion) => promotion.type === 'bundle');

    bundlePromotions.forEach((promotion) => {
        const requiredIds = (promotion.bundle_product_ids ?? []).map(Number).filter(Boolean);
        if (requiredIds.length === 0) {
            return;
        }

        const cartProductIds = nextItems.map((item) => Number(item.product_id));
        const missing = requiredIds.some((id) => !cartProductIds.includes(id));
        if (missing) {
            return;
        }

        const bundleLines = nextItems.filter((item) => requiredIds.includes(Number(item.product_id)));
        const bundleGross = bundleLines.reduce(
            (sum, item) => sum + parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0),
            0,
        );

        if (bundleGross <= 0) {
            return;
        }

        const bundleDiscount = computeBundleDiscountAmount(promotion, bundleGross);
        if (bundleDiscount <= 0) {
            return;
        }

        let remainingDiscount = bundleDiscount;
        nextItems.forEach((item, index) => {
            if (!requiredIds.includes(Number(item.product_id))) {
                return;
            }

            const lineGross = parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0);
            const share = bundleGross > 0 ? lineGross / bundleGross : 0;
            const lineDiscount = roundAmount(Math.min(remainingDiscount, bundleDiscount * share));
            remainingDiscount = roundAmount(remainingDiscount - lineDiscount);

            nextItems[index] = {
                ...nextItems[index],
                promotion_id: promotion.id,
                promotion_discount: roundAmount(parseFloat(nextItems[index].promotion_discount || 0) + lineDiscount),
                promotion_label: promotion.name,
                promotion_meta: {
                    ...(nextItems[index].promotion_meta ?? {}),
                    type: 'bundle',
                    bundle_id: promotion.id,
                    stack_with_manual_line_discount: promotion.stack_with_manual_line_discount,
                    stack_with_invoice_discount: promotion.stack_with_invoice_discount,
                    stack_with_special_discount: promotion.stack_with_special_discount,
                },
            };
        });
    });

    return nextItems;
}

function resolveStackingFlags(items, promotions) {
    const appliedPromotionIds = [...new Set(items.map((item) => item.promotion_id).filter(Boolean))];
    const applied = promotions.filter((promotion) => appliedPromotionIds.includes(promotion.id));

    if (applied.length === 0) {
        return {
            manual_line_discount: true,
            invoice_discount: true,
            special_discount: true,
        };
    }

    return {
        manual_line_discount: applied.every((promotion) => promotion.stack_with_manual_line_discount),
        invoice_discount: applied.every((promotion) => promotion.stack_with_invoice_discount),
        special_discount: applied.every((promotion) => promotion.stack_with_special_discount),
    };
}

export function applyPromotionsToCart(items = [], promotions = [], saleDate = null) {
    if (!items.length) {
        return {
            items: [],
            promotion_discount_total: 0,
            stacking: {
                manual_line_discount: true,
                invoice_discount: true,
                special_discount: true,
            },
        };
    }

    const scheduledPromotions = filterPromotionsForSaleDate(promotions, saleDate);

    if (!scheduledPromotions.length) {
        const strippedItems = stripPromotionsFromItems(items);

        return {
            items: strippedItems,
            promotion_discount_total: 0,
            stacking: {
                manual_line_discount: true,
                invoice_discount: true,
                special_discount: true,
            },
        };
    }

    const normalizedItems = prepareItemsForPromotion(items);
    let resolved = applyLinePromotions(normalizedItems, scheduledPromotions);
    resolved = applyBuyXGetY(resolved, scheduledPromotions);
    resolved = applyBundlePromotions(resolved, scheduledPromotions);

    const promotionDiscountTotal = roundAmount(
        resolved.reduce((sum, item) => sum + parseFloat(item.promotion_discount ?? 0), 0),
    );

    return {
        items: resolved,
        promotion_discount_total: promotionDiscountTotal,
        stacking: resolveStackingFlags(resolved, scheduledPromotions),
    };
}

export function canApplyManualLineDiscount(item, stacking) {
    if (!item?.promotion_id) {
        return true;
    }

    return stacking?.manual_line_discount ?? item?.promotion_meta?.stack_with_manual_line_discount ?? true;
}
