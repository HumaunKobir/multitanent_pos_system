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
export function buildVariationDataFromRows(parsedRows, comboValues) {
    /** @type {Record<string, string>} */
    const variation_data = {};

    parsedRows.forEach((row, index) => {
        variation_data[row.name] = comboValues[index];
    });

    variation_data.label = comboValues.join('-');

    return variation_data;
}
