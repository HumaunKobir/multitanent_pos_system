/**
 * @typedef {{ name: string, options: string[], labelOnly?: boolean, index?: number }} VariationAxis
 */

/**
 * @param {{ variation_data?: Record<string, string>, sku?: string }} variation
 */
export function getVariationLabel(variation) {
    const data = variation.variation_data ?? {};

    if (data.label) {
        return data.label;
    }

    const entries = Object.entries(data).filter(([key]) => key !== 'label');

    if (entries.length === 0) {
        return variation.sku || 'Option';
    }

    return entries.map(([, value]) => value).join(' · ');
}

/**
 * @param {Array<{ variation_data?: Record<string, string> }>} variations
 * @returns {VariationAxis[]}
 */
export function inferVariationAxes(variations) {
    if (!variations?.length) {
        return [];
    }

    const axisNames = [
        ...new Set(
            variations.flatMap((variation) =>
                Object.keys(variation.variation_data ?? {}).filter((key) => key !== 'label'),
            ),
        ),
    ];

    if (axisNames.length > 0) {
        return axisNames.map((name) => ({
            name,
            options: [
                ...new Set(
                    variations
                        .map((variation) => variation.variation_data?.[name])
                        .filter((value) => value != null && value !== ''),
                ),
            ],
        }));
    }

    const labels = variations.map((variation) => variation.variation_data?.label).filter(Boolean);

    if (labels.length === variations.length) {
        return [
            {
                name: 'Option',
                options: [...new Set(labels)],
                labelOnly: true,
            },
        ];
    }

    return [
        {
            name: 'Option',
            options: [...new Set(variations.map(getVariationLabel))],
            labelOnly: true,
        },
    ];
}

/**
 * @param {Record<string, string>} selections
 * @param {VariationAxis[]} axes
 * @param {{ variation_data?: Record<string, string> }} variation
 */
function variationMatchesSelections(variation, selections, axes) {
    const data = variation.variation_data ?? {};

    return axes.every((axis) => {
        const selected = selections[axis.name];
        if (!selected) {
            return true;
        }

        if (data[axis.name] !== undefined) {
            return data[axis.name] === selected;
        }

        if (axis.labelOnly && data.label) {
            if (axis.name === 'Option') {
                return data.label === selected;
            }
        }

        return false;
    });
}

/**
 * @param {Array<{ variation_data?: Record<string, string> }>} variations
 * @param {Record<string, string>} selections
 * @param {VariationAxis[]} axes
 */
export function findMatchingVariation(variations, selections, axes) {
    const complete = axes.every((axis) => selections[axis.name]);

    if (!complete) {
        return null;
    }

    return (
        variations.find((variation) => variationMatchesSelections(variation, selections, axes)) ?? null
    );
}

/**
 * @param {Array<{ variation_data?: Record<string, string>, stock?: number }>} variations
 * @param {string} axisName
 * @param {Record<string, string>} selections
 * @param {VariationAxis[]} axes
 */
export function getOptionsForAxis(variations, axisName, selections, axes) {
    const axis = axes.find((item) => item.name === axisName);
    if (!axis) {
        return [];
    }

    return axis.options.map((option) => {
        const probe = { ...selections, [axisName]: option };
        const match = findMatchingVariation(variations, probe, axes);
        const partialMatches = variations.filter((variation) =>
            variationMatchesSelections(variation, probe, axes),
        );
        const inStock = partialMatches.some((variation) => (variation.stock ?? 0) > 0);

        return {
            value: option,
            available: partialMatches.length > 0,
            inStock,
            variation: match,
        };
    });
}

/**
 * @param {{ variation_data?: Record<string, string> }} variation
 * @param {VariationAxis[]} axes
 */
export function selectionsFromVariation(variation, axes) {
    if (!variation) {
        return {};
    }

    const data = variation.variation_data ?? {};
    /** @type {Record<string, string>} */
    const selections = {};

    axes.forEach((axis) => {
        if (data[axis.name] !== undefined) {
            selections[axis.name] = data[axis.name];
            return;
        }

        if (axis.labelOnly && axis.name === 'Option' && data.label) {
            selections[axis.name] = data.label;
        }
    });

    return selections;
}

/**
 * Build variation_data for admin combination rows.
 *
 * @param {Array<{ name: string, values: string[] }>} parsedRows
 * @param {string[]} comboValues
 */
/**
 * Build initial VariationBuilder state from existing product variations.
 *
 * @param {Array<{ id?: number, variation_data?: Record<string, string>, price?: number|string, purchase_price?: number|string, sku?: string, stock?: number|string }>} variations
 */
export function buildVariationBuilderState(variations = []) {
    const defaultRows = [
        { id: 1, name: 'Color', values: [] },
        { id: 2, name: 'Size', values: [] },
    ];

    if (!variations?.length) {
        return {
            enabled: false,
            rows: defaultRows,
            combinations: [],
        };
    }

    const axes = inferVariationAxes(variations);
    const rows = axes.map((axis, index) => ({
        id: index + 1,
        name: axis.labelOnly && axis.name === 'Option' ? '' : axis.name,
        values: [...axis.options],
    }));

    if (rows.length === 0) {
        rows.push({ id: 1, name: '', values: [] });
    }

    const combinations = variations.map((variation) => ({
        id: variation.id,
        _existing: true,
        variant: variation.variation_data?.label ?? getVariationLabel(variation),
        variation_data: variation.variation_data ?? {},
        sale_price: String(variation.price ?? ''),
        purchase_price: String(variation.purchase_price ?? ''),
        sku: variation.sku ?? '',
        stock: String(variation.stock ?? 0),
    }));

    return {
        enabled: true,
        rows,
        combinations,
    };
}

export function buildVariationDataFromRows(parsedRows, comboValues) {
    /** @type {Record<string, string>} */
    const variation_data = {};

    parsedRows.forEach((row, index) => {
        variation_data[row.name] = comboValues[index];
    });

    variation_data.label = comboValues.join('-');

    return variation_data;
}

const VARIANT_ROW_ORDER = ['Color', 'Size'];

/**
 * @param {Array<{ name: string, values: string[] }>} rows
 */
export function sortParsedVariantRows(rows) {
    return [...rows].sort((a, b) => {
        const aIndex = VARIANT_ROW_ORDER.indexOf(a.name);
        const bIndex = VARIANT_ROW_ORDER.indexOf(b.name);

        if (aIndex === -1 && bIndex === -1) {
            return 0;
        }

        if (aIndex === -1) {
            return 1;
        }

        if (bIndex === -1) {
            return -1;
        }

        return aIndex - bIndex;
    });
}

/**
 * Build combination rows from variation builder rows, preserving existing prices/stock/sku.
 *
 * @param {Array<{ name?: string, values?: string[] }>} rows
 * @param {Array<{ variant?: string, variation_data?: Record<string, string>, sale_price?: string, purchase_price?: string, sku?: string, stock?: string, id?: number }>} existingCombinations
 * @returns {{ combinations: typeof existingCombinations, error: string | null }}
 */
export function buildCombinationsFromVariantRows(rows, existingCombinations = []) {
    const namedRows = rows.filter((row) => String(row.name ?? '').trim());
    const missingValues = namedRows.filter((row) => (row.values ?? []).length === 0);

    if (missingValues.length > 0) {
        return {
            combinations: existingCombinations,
            error: `Add values for: ${missingValues.map((row) => row.name).join(', ')}`,
        };
    }

    const parsed = sortParsedVariantRows(
        namedRows.map((row) => ({ name: String(row.name).trim(), values: row.values ?? [] })),
    );

    if (!parsed.length) {
        return { combinations: [], error: null };
    }

    const cartesian = parsed.map((row) => row.values).reduce((acc, cur) => {
        const res = [];
        acc.forEach((a) => cur.forEach((b) => res.push([...a, b])));

        return res;
    }, [[]]);

    const prevMap = new Map((existingCombinations ?? []).map((combo) => [combo.variant, combo]));

    const combinations = cartesian.map((combo) => {
        const variation_data = buildVariationDataFromRows(parsed, combo);
        const variantText = variation_data.label ?? combo.join('-');
        const existing = prevMap.get(variantText);

        if (!existing) {
            return {
                variant: variantText,
                variation_data,
                sale_price: '',
                purchase_price: '',
                sku: '',
                stock: '',
            };
        }

        return {
            ...existing,
            variant: variantText,
            variation_data,
            sale_price: existing.sale_price ?? '',
            purchase_price: existing.purchase_price ?? '',
            sku: existing.sku ?? '',
            stock: existing.stock ?? '',
        };
    });

    return { combinations, error: null };
}
