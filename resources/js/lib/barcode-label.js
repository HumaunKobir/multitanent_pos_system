import JsBarcode from 'jsbarcode';

export const PRINT_DPI = 96;
export const NAME_BARCODE_GAP_PX = 1;
export const BARCODE_PRICE_GAP_PX = 1;
export const MIN_LABEL_FONT_PX = 5;
export const MAX_LABEL_FONT_PX = 24;
export const MIN_LABEL_HEIGHT_IN = 0.3;
export const MAX_LABEL_HEIGHT_IN = 10;
export const MIN_LABEL_WIDTH_IN = 0.5;
export const MAX_LABEL_WIDTH_IN = 10;
/** Horizontal label padding (5px left + 5px right). */
export const LABEL_PADDING_X_PX = 10;
/** Top label padding — extra room so bold header text is not clipped in print. */
export const LABEL_PADDING_TOP_PX = 5;
/** Bottom label padding. */
export const LABEL_PADDING_BOTTOM_PX = 3;
/** Vertical label padding total (top + bottom). */
export const LABEL_PADDING_Y_PX = LABEL_PADDING_TOP_PX + LABEL_PADDING_BOTTOM_PX;
/** Horizontal padding inside the barcode row (2px each side). */
export const LABEL_BARCODE_WRAP_PADDING_X = 4;
/** Average character width as a fraction of font size for width-fit checks. */
export const LABEL_CHAR_WIDTH_RATIO = 0.58;
/** Bold text needs slightly more horizontal space per character. */
export const LABEL_CHAR_WIDTH_RATIO_BOLD = 0.64;
/** Extra print headroom so long product names are not clipped at the edge. */
export const LABEL_WIDTH_SAFETY_PX = 8;
/** Minimum bar height for reliable scanner reads (~8.5mm at 96 DPI). */
export const MIN_BARCODE_BAR_HEIGHT_PX = 32;
/** Floor when scaling barcode width to fit the label. */
export const MIN_BARCODE_FONT_PX = 18;
/** Upper bound for JsBarcode module width when scaling up to fill the label. */
export const MAX_BARCODE_MODULE_WIDTH = 6;
/**
 * Leave horizontal headroom so barcode quiet zones are not clipped in print
 * (browser/print engines often render slightly wider than on-screen measurement).
 */
export const BARCODE_WIDTH_SAFETY_RATIO = 0.86;
/** Use most of the vertical space between name and price for bar height. */
export const BARCODE_HEIGHT_USAGE_RATIO = 0.98;
/** Compact bar height used in the barcode list and label preview (at 1" label height). */
export const LIST_BARCODE_BAR_HEIGHT = 28;
/** Quiet zone scales with module width so scanners can find the barcode edges. */
export const QUIET_ZONE_MODULE_RATIO = 5;
/** Quiet zone never shrinks below this, even for very thin bars. */
export const MIN_QUIET_MARGIN_PX = 2;
export const JSBARCODE_CDN =
    'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';

/** Quiet zone (margin) in px for a given JsBarcode module width. */
export function getBarcodeQuietMargin(moduleWidth) {
    return Math.max(
        MIN_QUIET_MARGIN_PX,
        Math.round(moduleWidth * QUIET_ZONE_MODULE_RATIO),
    );
}

const CODE128_START_B = 204;
const CODE128_STOP = 206;

function code128SymbolToChar(value) {
    if (value <= 94) {
        return String.fromCharCode(value + 32);
    }

    return String.fromCharCode(value + 146);
}

/** Code 128 Set B accepts printable ASCII (space through tilde). */
export function isCode128Encodable(text) {
    return /^[\x20-\x7E]+$/.test(String(text ?? ''));
}

/**
 * Encode plain text for the Libre Barcode 128 font (start + data + checksum + stop).
 */
export function encodeCode128B(input) {
    const text = String(input ?? '');

    if (!text) {
        return '';
    }

    if (!isCode128Encodable(text)) {
        throw new Error(`Barcode code contains unsupported characters: ${text}`);
    }

    let checksum = 104;
    let encoded = String.fromCharCode(CODE128_START_B);

    for (let i = 0; i < text.length; i++) {
        const value = text.charCodeAt(i) - 32;
        checksum += value * (i + 1);
        encoded += text[i];
    }

    checksum %= 103;
    encoded += code128SymbolToChar(checksum);
    encoded += String.fromCharCode(CODE128_STOP);

    return encoded;
}

export function formatBarcodeForLibre128(code) {
    const text = String(code ?? '').trim();

    if (!text) {
        return '';
    }

    return encodeCode128B(text);
}

export function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function getNameBarcodeGap(_fontSize = null) {
    return NAME_BARCODE_GAP_PX;
}

export function getBarcodePriceGap() {
    return BARCODE_PRICE_GAP_PX;
}

export function getEffectivePrice(row) {
    if (row?.variation?.price != null) {
        return parseFloat(row.variation.price);
    }

    if (!row?.product) {
        return null;
    }

    const disc = parseFloat(row.product.discount_price ?? 0);
    const sale = parseFloat(row.product.sale_price ?? 0);

    return disc > 0 ? disc : sale;
}

export function formatLabelPrice(price) {
    const amount = price != null ? Number(price) : 0;

    return `TK: ${amount.toFixed(2)}`;
}

const LABEL_LINE_HEIGHT_RATIO = 1.15;

export function getLabelLineHeight(fontSize) {
    return Math.ceil(fontSize * LABEL_LINE_HEIGHT_RATIO);
}

function getLabelTextLines(row) {
    if (!row) {
        return ['Product Name', '000000', formatLabelPrice(0)];
    }

    return [
        ...getLabelHeaderLines(row).map((line) => line.text),
        getLabelCodeLine(row),
        formatLabelPrice(getEffectivePrice(row) ?? 0),
    ].filter(Boolean);
}

function getLongestLabelLineLength(row) {
    const lines = getLabelTextLines(row);

    return Math.max(...lines.map((line) => line.length), 1);
}

function clampLabelFontSize(fontSize) {
    return Math.max(
        MIN_LABEL_FONT_PX,
        Math.min(fontSize ?? 9, MAX_LABEL_FONT_PX),
    );
}

function clampLabelHeight(height) {
    return Math.max(
        MIN_LABEL_HEIGHT_IN,
        Math.min(height ?? 1, MAX_LABEL_HEIGHT_IN),
    );
}

function clampLabelWidth(width) {
    return Math.max(
        MIN_LABEL_WIDTH_IN,
        Math.min(width ?? 1.5, MAX_LABEL_WIDTH_IN),
    );
}

function estimateLabelLineWidthPx(text, fontSize, bold = false) {
    const ratio = bold ? LABEL_CHAR_WIDTH_RATIO_BOLD : LABEL_CHAR_WIDTH_RATIO;

    return String(text ?? '').length * fontSize * ratio;
}

/**
 * Minimum label width in pixels so header, code, and price lines are not clipped.
 */
export function getRequiredLabelWidthPx(settings, row, fontSize = null) {
    const fs = clampLabelFontSize(fontSize ?? settings.fontSize);
    const fw = settings.fontWeight === 'bold';
    let maxLineWidth = 0;

    if (row) {
        for (const line of getLabelHeaderLines(row)) {
            maxLineWidth = Math.max(
                maxLineWidth,
                estimateLabelLineWidthPx(line.text, fs, line.bold || fw),
            );
        }

        maxLineWidth = Math.max(
            maxLineWidth,
            estimateLabelLineWidthPx(getLabelCodeLine(row), fs, false),
        );
        maxLineWidth = Math.max(
            maxLineWidth,
            estimateLabelLineWidthPx(
                formatLabelPrice(getEffectivePrice(row) ?? 0),
                fs,
                true,
            ),
        );
    } else {
        maxLineWidth = estimateLabelLineWidthPx('Product Name', fs, true);
    }

    return maxLineWidth + LABEL_PADDING_X_PX + LABEL_WIDTH_SAFETY_PX;
}

/**
 * Minimum label width in inches so header, code, and price lines are not clipped.
 */
export function getRequiredLabelWidthIn(settings, row, fontSize = null) {
    const px = getRequiredLabelWidthPx(settings, row, fontSize);
    const inches = px / PRINT_DPI;

    return Math.min(
        MAX_LABEL_WIDTH_IN,
        Math.max(MIN_LABEL_WIDTH_IN, Math.ceil(inches * 10) / 10),
    );
}

/** Barcode height target used when calculating required label height. */
export function getTargetBarcodeBarHeightPx(heightIn = 1) {
    const scaled = Math.round(LIST_BARCODE_BAR_HEIGHT * heightIn);

    return Math.max(Math.floor(MIN_BARCODE_BAR_HEIGHT_PX * 0.75), scaled);
}

/**
 * Minimum label height in pixels for the requested font size and row content.
 */
export function getRequiredLabelHeightPx(settings, row, fontSize = null) {
    const fs = clampLabelFontSize(fontSize ?? settings.fontSize);
    const lineHeightPx = getLabelLineHeight(fs);
    const headerLines = row ? getLabelHeaderLineCount(row) : 1;
    const headerBlockPx = headerLines * lineHeightPx;
    const nameBarcodeGapPx = getNameBarcodeGap(fs);
    const footerBlockPx = lineHeightPx * 2 + getBarcodePriceGap();
    const barHeightPx = getTargetBarcodeBarHeightPx(settings.height ?? 1);

    return (
        LABEL_PADDING_Y_PX +
        headerBlockPx +
        nameBarcodeGapPx +
        barHeightPx +
        footerBlockPx
    );
}

/**
 * Minimum label height in inches for the requested font size and row content.
 */
export function getRequiredLabelHeightIn(settings, row, fontSize = null) {
    const px = getRequiredLabelHeightPx(settings, row, fontSize);
    const inches = px / PRINT_DPI;

    return Math.min(
        MAX_LABEL_HEIGHT_IN,
        Math.max(MIN_LABEL_HEIGHT_IN, Math.ceil(inches * 10) / 10),
    );
}

/**
 * Apply auto-height when enabled so text and barcode fit without clipping.
 */
export function resolveLabelSettings(settings, rows = []) {
    const fontSize = clampLabelFontSize(settings.fontSize);
    let height = clampLabelHeight(settings.height);
    let width = clampLabelWidth(settings.width);

    if (settings.autoHeight === true) {
        const labelRows = rows.length > 0 ? rows : [null];

        for (const row of labelRows) {
            const context = { ...settings, fontSize, height, width };

            height = Math.max(
                height,
                getRequiredLabelHeightIn(context, row, fontSize),
            );
            width = Math.max(
                width,
                getRequiredLabelWidthIn(context, row, fontSize),
            );
        }
    }

    return {
        ...settings,
        fontSize,
        height,
        width,
    };
}

function labelTextFitsWidth(row, fontSize, contentWidthPx) {
    return (
        getRequiredLabelWidthPx({ fontSize }, row, fontSize) - LABEL_PADDING_X_PX <=
        contentWidthPx
    );
}

/**
 * Whether the label layout fits at the given font size (text + minimum barcode).
 */
export function labelLayoutFitsAtFontSize(settings, row, fontSize) {
    const { width, height } = getContentLayoutSettings(settings);
    const labelHeightPx = height * PRINT_DPI;
    const labelWidthPx = width * PRINT_DPI;
    const lineHeightPx = getLabelLineHeight(fontSize);
    const headerLines = row ? getLabelHeaderLineCount(row) : 1;
    const headerBlockPx = headerLines * lineHeightPx;
    const nameBarcodeGapPx = getNameBarcodeGap(fontSize);
    const barcodePriceGapPx = getBarcodePriceGap();
    const footerBlockPx = lineHeightPx * 2 + barcodePriceGapPx;
    const minBarHeight = getTargetBarcodeBarHeightPx(height);
    const totalHeightPx =
        LABEL_PADDING_Y_PX +
        headerBlockPx +
        nameBarcodeGapPx +
        minBarHeight +
        footerBlockPx;

    if (totalHeightPx > labelHeightPx) {
        return false;
    }

    return labelTextFitsWidth(
        row,
        fontSize,
        labelWidthPx - LABEL_PADDING_X_PX,
    );
}

/**
 * Largest font size that fits the label for the given row and dimensions.
 */
export function getMaxFittingLabelFontSize(settings, row = null) {
    const { height } = getContentLayoutSettings(settings);
    const labelHeightPx = height * PRINT_DPI;
    const maxByHeight = Math.max(
        MIN_LABEL_FONT_PX,
        Math.floor(labelHeightPx / 4),
    );
    const upperBound = Math.min(MAX_LABEL_FONT_PX, maxByHeight);

    for (let size = upperBound; size >= MIN_LABEL_FONT_PX; size--) {
        if (labelLayoutFitsAtFontSize(settings, row, size)) {
            return size;
        }
    }

    return MIN_LABEL_FONT_PX;
}

/**
 * Use the requested font size when it fits; otherwise use the largest size that fits.
 */
export function getEffectiveLabelFontSize(settings, row = null) {
    const { fontSize } = getContentLayoutSettings(settings);
    const requested = clampLabelFontSize(fontSize);

    if (settings.autoHeight === true) {
        return requested;
    }

    const maxFit = getMaxFittingLabelFontSize(settings, row);

    return Math.min(requested, maxFit);
}

/**
 * Preview scale — renders at the label's true physical size (1 CSS inch =
 * 96px, matching print @page sizing), only shrinking to fit the preview
 * area for labels too large to display at full size. Never enlarges, so
 * the on-screen box always matches the width/height field values.
 */
export function getLabelPreviewScale(
    widthIn,
    heightIn,
    maxW = 500,
    maxH = 320,
) {
    const pxW = widthIn * PRINT_DPI;
    const pxH = heightIn * PRINT_DPI;
    const fit = Math.min(maxW / pxW, maxH / pxH);

    return Math.min(1, fit);
}

/** Round a print-pixel value for on-screen preview rendering. */
export function scaleLabelPreviewPx(px, scale) {
    return Math.max(1, Math.round(px * scale));
}

/**
 * @returns {{ scale: number, width: number, height: number }}
 */
export function getLabelPreviewDisplaySize(
    widthIn,
    heightIn,
    maxW = 500,
    maxH = 320,
) {
    const scale = getLabelPreviewScale(widthIn, heightIn, maxW, maxH);
    const pxW = widthIn * PRINT_DPI;
    const pxH = heightIn * PRINT_DPI;

    return {
        scale,
        width: Math.round(pxW * scale),
        height: Math.round(pxH * scale),
    };
}

/**
 * Barcode content area matches the label page dimensions.
 *
 * @returns {{ width: number, height: number }}
 */
export function getLabelContentDimensions(settings) {
    return {
        width: settings.width,
        height: settings.height,
    };
}

/** Settings used to lay out text and barcode inside the content area. */
export function getContentLayoutSettings(settings) {
    return {
        ...settings,
        ...getLabelContentDimensions(settings),
    };
}

function getVariationField(row, ...keys) {
    const data = row?.variation?.variation_data ?? {};

    for (const key of keys) {
        const match = Object.entries(data).find(
            ([field]) => field.toLowerCase() === key.toLowerCase() && field !== 'label',
        );

        if (match?.[1]) {
            return String(match[1]).trim();
        }
    }

    return null;
}

function getProductAttributeLabels(row, key) {
    const labels = row?.product?.[`${key}_labels`];

    if (!Array.isArray(labels) || labels.length === 0) {
        return null;
    }

    return labels.filter(Boolean).join(', ');
}

function parseColorSizeFromLabel(label) {
    const text = String(label ?? '').trim();

    if (!text) {
        return { color: null, size: null };
    }

    const main = text.split('/')[0].trim();
    const dashParts = main.split('-');

    if (dashParts.length < 2) {
        return { color: null, size: null };
    }

    const sizePattern = /^(XXS|XS|S|M|L|XL|XXL|2XL|3XL|4XL|\d+)$/i;
    const last = dashParts[dashParts.length - 1];

    if (!sizePattern.test(last)) {
        return { color: null, size: null };
    }

    return {
        color: dashParts.slice(0, -1).join('-').trim(),
        size: last,
    };
}

function getParsedVariationAttributes(row) {
    const label = row?.variation?.variation_data?.label?.trim();

    if (!label) {
        return { color: null, size: null };
    }

    return parseColorSizeFromLabel(label);
}

export function getLabelName(row) {
    const rawName = row?.product?.name ?? row?.name ?? 'Product Name';

    return rawName.split('/')[0].trim();
}

export function getLabelProductName(row) {
    return getLabelName(row).toUpperCase();
}

export function getLabelStyle(row) {
    const fromVariation = getVariationField(row, 'fit', 'style');

    if (fromVariation) {
        return fromVariation;
    }

    const rawName = row?.product?.name ?? row?.name ?? '';
    const parts = rawName.split('/');

    if (parts.length > 1) {
        return parts.slice(1).join('/').trim();
    }

    return null;
}

export function getLabelColor(row) {
    const fromVariation = getVariationField(row, 'color');

    if (fromVariation) {
        return fromVariation.toUpperCase();
    }

    const parsed = getParsedVariationAttributes(row);

    if (parsed.color) {
        return parsed.color.toUpperCase();
    }

    const fromProduct = getProductAttributeLabels(row, 'color');

    return fromProduct ? fromProduct.toUpperCase() : null;
}

export function getLabelSize(row) {
    const fromVariation = getVariationField(row, 'size');

    if (fromVariation) {
        return fromVariation;
    }

    const parsed = getParsedVariationAttributes(row);

    if (parsed.size) {
        return parsed.size;
    }

    return getProductAttributeLabels(row, 'size');
}

export function getLabelSkuLine(row) {
    const barcodeCode = String(row?.code ?? '').trim();
    const sku =
        row?.variation?.sku_code?.trim() || row?.variation?.sku?.trim();
    const productCode = row?.product?.code?.trim();

    if (sku && sku !== barcodeCode) {
        return sku;
    }

    if (productCode && productCode !== barcodeCode) {
        return productCode;
    }

    return null;
}

export function getLabelCodeLine(row) {
    const code = String(row?.code ?? '').trim();
    const size = getLabelSize(row);

    if (!code) {
        return '';
    }

    return size ? `${code} (${size})` : code;
}

/**
 * @returns {Array<{ text: string, bold?: boolean }>}
 */
export function getLabelHeaderLines(row) {
    const lines = [{ text: getLabelProductName(row), bold: true }];
    const color = getLabelColor(row);

    if (color) {
        lines.push({ text: color });
    }

    const style = getLabelStyle(row);

    if (style) {
        lines.push({ text: style });
    }

    const sku = getLabelSkuLine(row);

    if (sku) {
        lines.push({ text: sku });
    }

    return lines;
}

export function getLabelHeaderLineCount(row) {
    return getLabelHeaderLines(row).length;
}

export function getVariantSku(row) {
    const sku = row?.variation?.sku?.trim();

    return sku || null;
}

export function getVariantLabel(row) {
    const label = row?.variation?.variation_data?.label?.trim();

    return label || null;
}

export function getLabelTitle(row) {
    const baseName = getLabelName(row);
    const variantLabel = getVariantLabel(row);
    const variantSku = getVariantSku(row);

    if (variantLabel && variantSku) {
        return `${baseName} - ${variantLabel} - ${variantSku}`;
    }

    if (variantLabel) {
        return `${baseName} - ${variantLabel}`;
    }

    if (variantSku) {
        return `${baseName} - ${variantSku}`;
    }

    return row?.code ? `${baseName} - ${row.code}` : baseName;
}

/**
 * Fit barcode height inside the label without clipping text rows.
 */
export function calculateBarcodeBarHeight(settings, row = null, fontSize = null) {
    const { height } = getContentLayoutSettings(settings);
    const resolvedFontSize =
        fontSize ?? getEffectiveLabelFontSize(settings, row);
    const labelHeightPx = height * PRINT_DPI;
    const lineHeightPx = getLabelLineHeight(resolvedFontSize);
    const headerLines = row ? getLabelHeaderLineCount(row) : 1;
    const headerBlockPx = headerLines * lineHeightPx;
    const nameBarcodeGapPx = getNameBarcodeGap(resolvedFontSize);
    const barcodePriceGapPx = getBarcodePriceGap();
    const footerBlockPx = lineHeightPx * 2 + barcodePriceGapPx;
    const maxBarHeight =
        labelHeightPx -
        LABEL_PADDING_Y_PX -
        headerBlockPx -
        nameBarcodeGapPx -
        footerBlockPx;
    const minBarHeight = Math.max(10, Math.floor(labelHeightPx * 0.22));

    if (maxBarHeight <= minBarHeight) {
        return Math.max(minBarHeight, maxBarHeight);
    }

    return Math.max(
        minBarHeight,
        Math.floor(maxBarHeight * BARCODE_HEIGHT_USAGE_RATIO),
    );
}

/**
 * Bar height for labels and preview — matches the compact list thumbnail style.
 */
export function getLabelBarcodeBarHeight(settings, row = null) {
    const layout = getContentLayoutSettings(settings);
    const scaled = Math.round(LIST_BARCODE_BAR_HEIGHT * layout.height);
    const maxFit = calculateBarcodeBarHeight(settings, row);

    return Math.max(10, Math.min(scaled, maxFit));
}

function buildLabelHeaderHtml(row, fontSize, fontWeight) {
    const fw = fontWeight === 'bold' ? 700 : 400;
    const lineHeight = getLabelLineHeight(fontSize);

    return getLabelHeaderLines(row)
        .map((line) => {
            const weight = line.bold ? 700 : fw;

            return `<div class="label-line label-header-line" style="font-size:${fontSize}px;font-weight:${weight};line-height:${lineHeight}px;">${escapeHtml(line.text)}</div>`;
        })
        .join('');
}

export function escapeHtmlAttr(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}

/**
 * Shrink or grow JsBarcode module width so the SVG fits the target width.
 * Uses integer module widths only so bars render on pixel boundaries.
 */
export function fitBarcodeModuleWidth(svgEl, draw, targetWidth) {
    const minModuleWidth = 1;
    const maxModuleWidth = Math.max(
        MAX_BARCODE_MODULE_WIDTH,
        Math.ceil(targetWidth / 40),
    );
    let moduleWidth = 2;

    const measure = () => parseFloat(svgEl.getAttribute('width') || '0');

    draw(moduleWidth);
    let svgWidth = measure();

    while (svgWidth > targetWidth && moduleWidth > minModuleWidth) {
        moduleWidth -= 1;
        draw(moduleWidth);
        svgWidth = measure();
    }

    while (moduleWidth < maxModuleWidth) {
        const nextModuleWidth = moduleWidth + 1;

        draw(nextModuleWidth);
        const nextWidth = measure();

        if (nextWidth > targetWidth) {
            draw(moduleWidth);
            break;
        }

        moduleWidth = nextModuleWidth;
        svgWidth = nextWidth;
    }

    return moduleWidth;
}

/**
 * Keep the SVG at native JsBarcode dimensions — never stretch with CSS.
 */
export function finalizeBarcodeSvg(svgEl) {
    if (!svgEl) {
        return;
    }

    svgEl.style.removeProperty('width');
    svgEl.style.removeProperty('height');
    svgEl.style.removeProperty('max-width');
    svgEl.style.display = 'block';
    svgEl.style.flexShrink = '0';
    svgEl.style.margin = '0 auto';
    svgEl.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    svgEl.setAttribute('shape-rendering', 'crispEdges');
}

/**
 * Render a scannable Code 128 SVG and scale module width to fit the label.
 */
export function renderBarcodeSvg(svgEl, containerEl, code, maxBarHeight, { fill = false } = {}) {
    if (!svgEl || !containerEl) {
        return maxBarHeight;
    }

    const text = String(code ?? '').trim();

    if (!text) {
        return maxBarHeight;
    }

    const containerWidth = containerEl.clientWidth;
    const targetWidth = containerWidth * BARCODE_WIDTH_SAFETY_RATIO;
    const availableHeight = containerEl.clientHeight;
    const heightFromContainer =
        fill && availableHeight > 8
            ? Math.floor(availableHeight * BARCODE_HEIGHT_USAGE_RATIO)
            : maxBarHeight;
    let barHeight = Math.max(
        10,
        Math.min(maxBarHeight, heightFromContainer),
    );

    fitBarcodeModuleWidth(svgEl, (moduleWidth) => {
        JsBarcode(svgEl, text, {
            format: 'CODE128',
            width: moduleWidth,
            height: barHeight,
            displayValue: false,
            margin: getBarcodeQuietMargin(moduleWidth),
            background: '#ffffff',
            lineColor: '#000000',
        });
    }, targetWidth);

    finalizeBarcodeSvg(svgEl);

    return barHeight;
}

/** @deprecated Use renderBarcodeSvg */
export function fitBarcodeToContainer(svgEl, containerEl, maxBarHeight, options = {}) {
    return renderBarcodeSvg(
        svgEl,
        containerEl,
        options.code ?? '',
        maxBarHeight,
        options,
    );
}

export function buildPrintHtml(rows, settings) {
    const resolved = resolveLabelSettings(settings, rows);
    const { width, height, fontWeight, copies } = resolved;
    const fw = fontWeight === 'bold' ? 700 : 400;
    const barcodePriceGap = getBarcodePriceGap();
    const defaultBarHeight = getLabelBarcodeBarHeight(resolved);

    const labels = rows
        .flatMap((row) => Array.from({ length: copies }, () => row))
        .map((row) => {
            const price = getEffectivePrice(row) ?? 0;
            const effectiveFontSize = getEffectiveLabelFontSize(resolved, row);
            const barHeight = getLabelBarcodeBarHeight(resolved, row);
            const headerHtml = buildLabelHeaderHtml(
                row,
                effectiveFontSize,
                fontWeight,
            );
            const codeLine = getLabelCodeLine(row);
            const lineHeight = getLabelLineHeight(effectiveFontSize);
            const rowNameBarcodeGap = getNameBarcodeGap(effectiveFontSize);

            return `
      <div class="label">
        <div class="label-inner">
          <div class="label-content">
            <div class="label-header" style="margin-bottom:${rowNameBarcodeGap}px;">${headerHtml}</div>
            <div class="bars-wrap">
              <svg class="bars" data-code="${escapeHtmlAttr(row.code)}" data-max-bar-height="${barHeight}"></svg>
            </div>
            <div class="footer">
              <div class="label-line label-code" style="font-size:${effectiveFontSize}px;line-height:${lineHeight}px;">${escapeHtml(codeLine)}</div>
              <div class="label-line label-price" style="font-size:${effectiveFontSize}px;font-weight:700;line-height:${lineHeight}px;">${escapeHtml(formatLabelPrice(price))}</div>
            </div>
          </div>
        </div>
      </div>`;
        })
        .join('');

    return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Barcode Labels</title>
  <script src="${JSBARCODE_CDN}" data-jsbarcode></script>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page { size: ${width}in ${height}in; margin: 0; }
    html, body { width: 100%; background: white; }
    .page { display: flex; flex-wrap: wrap; align-content: flex-start; }
    .label {
      width: ${width}in;
      height: ${height}in;
      border: 1px solid #ccc;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: ${LABEL_PADDING_TOP_PX}px 5px ${LABEL_PADDING_BOTTOM_PX}px;
      overflow: hidden;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .label-inner {
      width: 100%;
      height: 100%;
      min-height: 0;
      display: flex;
      align-items: flex-start;
      justify-content: center;
    }
    .label-content {
      width: 100%;
      height: 100%;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: stretch;
      gap: 0;
      overflow: hidden;
    }
    .label-header {
      flex-shrink: 0;
      text-align: center;
      width: 100%;
      overflow: visible;
      padding-top: 1px;
    }
    .label-line {
      font-weight: ${fw};
      font-family: sans-serif;
      text-align: center;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .label-header-line,
    .label-code,
    .label-price {
      overflow: visible;
      text-overflow: clip;
    }
    .label-code {
      margin-bottom: 1px;
    }
    .label-price {
      font-weight: 700;
    }
    .bars-wrap {
      width: 100%;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      padding: 0 2px;
      box-sizing: border-box;
    }
    .bars {
      display: block;
      flex-shrink: 0;
    }
    .footer {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex-shrink: 0;
      margin-top: ${barcodePriceGap}px;
      width: 100%;
    }
    @media print {
      @page { size: ${width}in ${height}in; margin: 0; }
      html, body {
        width: ${width}in;
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .page {
        display: block;
        width: ${width}in;
        margin: 0;
        padding: 0;
      }
      .label {
        width: ${width}in;
        height: ${height}in;
        max-height: ${height}in;
        border: none;
        page-break-after: always;
        break-after: page;
        overflow: hidden;
      }
      .label:last-child {
        page-break-after: avoid;
        break-after: avoid;
      }
    }
  </style>
</head>
<body>
  <div class="page">${labels}</div>
  <script>
    function fitBarcode(wrap) {
      var svg = wrap.querySelector('.bars');
      if (!svg || !window.JsBarcode) return;
      var code = svg.getAttribute('data-code') || '';
      var maxBarHeight = parseFloat(svg.getAttribute('data-max-bar-height') || '${defaultBarHeight}');
      var targetWidth = wrap.clientWidth * ${BARCODE_WIDTH_SAFETY_RATIO};
      var barHeight = Math.max(10, maxBarHeight);
      var minModuleWidth = 1;
      var maxModuleWidth = Math.max(${MAX_BARCODE_MODULE_WIDTH}, Math.ceil(targetWidth / 40));
      var moduleWidth = 2;
      var draw = function(width) {
        window.JsBarcode(svg, code, {
          format: 'CODE128',
          width: width,
          height: barHeight,
          displayValue: false,
          margin: Math.max(${MIN_QUIET_MARGIN_PX}, Math.round(width * ${QUIET_ZONE_MODULE_RATIO})),
          background: '#ffffff',
          lineColor: '#000000',
        });
      };
      var measure = function() {
        return parseFloat(svg.getAttribute('width') || '0');
      };
      draw(moduleWidth);
      var svgWidth = measure();
      while (svgWidth > targetWidth && moduleWidth > minModuleWidth) {
        moduleWidth -= 1;
        draw(moduleWidth);
        svgWidth = measure();
      }
      while (moduleWidth < maxModuleWidth) {
        var nextModuleWidth = moduleWidth + 1;
        draw(nextModuleWidth);
        var nextWidth = measure();
        if (nextWidth > targetWidth) {
          draw(moduleWidth);
          break;
        }
        moduleWidth = nextModuleWidth;
        svgWidth = nextWidth;
      }
      svg.style.removeProperty('width');
      svg.style.removeProperty('height');
      svg.style.removeProperty('max-width');
      svg.style.display = 'block';
      svg.style.flexShrink = '0';
      svg.style.margin = '0 auto';
      svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
      svg.setAttribute('shape-rendering', 'crispEdges');
    }

    function printWhenReady() {
      document.querySelectorAll('.bars-wrap').forEach(fitBarcode);
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          setTimeout(function() {
            window.print();
            window.close();
          }, 150);
        });
      });
    }

    var jsBarcodeScript = document.querySelector('script[data-jsbarcode]');
    if (window.JsBarcode) {
      printWhenReady();
    } else if (jsBarcodeScript) {
      jsBarcodeScript.addEventListener('load', printWhenReady);
      jsBarcodeScript.addEventListener('error', printWhenReady);
    } else {
      printWhenReady();
    }
  <\/script>
</body>
</html>`;
}
