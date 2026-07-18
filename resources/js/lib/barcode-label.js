export const PRINT_DPI = 96;
/** Equal gap between header↔barcode and barcode↔footer. */
export const NAME_BARCODE_GAP_PX = 1;
/** Space between the barcode bars and the code/price footer — match header gap. */
export const BARCODE_PRICE_GAP_PX = NAME_BARCODE_GAP_PX;
export const MIN_LABEL_FONT_PX = 5;
export const MAX_LABEL_FONT_PX = 24;
/**
 * Extra px on all label text when the header is multi-line (variants), so
 * header and footer stay readable without changing the label format.
 */
export const LABEL_FOOTER_FONT_BOOST_PX = 2;
export const MIN_LABEL_HEIGHT_IN = 0.3;
export const MAX_LABEL_HEIGHT_IN = 10;
export const MIN_LABEL_WIDTH_IN = 0.5;
export const MAX_LABEL_WIDTH_IN = 10;
/**
 * Fixed equal page margin on every side (top, right, bottom, left)
 * so content sits centered in the label.
 */
export const LABEL_PAGE_MARGIN_PX = 5;
/** Horizontal label padding total (left + right page margins). */
export const LABEL_PADDING_X_PX = LABEL_PAGE_MARGIN_PX * 2;
/** Top label padding — fixed margin above the header. */
export const LABEL_PADDING_TOP_PX = LABEL_PAGE_MARGIN_PX;
/** Bottom label padding — fixed margin below the footer. */
export const LABEL_PADDING_BOTTOM_PX = LABEL_PAGE_MARGIN_PX;
/** Vertical label padding total (top + bottom). */
export const LABEL_PADDING_Y_PX = LABEL_PADDING_TOP_PX + LABEL_PADDING_BOTTOM_PX;
/** No extra header-only pad — top gap matches the footer bottom gap. */
export const LABEL_HEADER_PAD_TOP_PX = 0;
/** Gap between the code line and the price line. */
export const LABEL_CODE_PRICE_GAP_PX = 1;
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
/**
 * Total horizontal safe space for the barcode inside the label page.
 * Actual barcode width = page width − this margin (~0.10" each side).
 */
export const BARCODE_HORIZONTAL_MARGIN_IN = 0.2;
/** Barcode bar height scales with page height (plan: pageHeight × 0.48). */
export const BARCODE_HEIGHT_RATIO = 0.48;
/**
 * Library module/bar thickness only — not the printed barcode width in inches.
 * Printed size is controlled via CSS on the SVG wrapper.
 */
export const BARCODE_MODULE_WIDTH = 1.5;
/** Use most of the vertical space between name and price when clamping bar height. */
export const BARCODE_HEIGHT_USAGE_RATIO = 0.98;
/** Compact bar height used in barcode list thumbnails. */
export const LIST_BARCODE_BAR_HEIGHT = 28;
/** Quiet zone around generated bars (library margin option). */
export const BARCODE_QUIET_MARGIN_PX = 2;
/** CDN used only by the print popup (non-React window). */
export const JSBARCODE_CDN =
    'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';

/**
 * Printed barcode width in inches for a given label page width.
 * Formula: pageWidth − 0.20, clipped so it never exceeds the page.
 */
export function getBarcodeWidthIn(pageWidthIn = 1.5) {
    const width = Math.max(
        MIN_LABEL_WIDTH_IN,
        Math.min(pageWidthIn ?? 1.5, MAX_LABEL_WIDTH_IN),
    );
    const withMargin = width - BARCODE_HORIZONTAL_MARGIN_IN;

    // If the page is too narrow for the preferred side margins, use full width
    // and let the wrapper overflow:hidden clip — never shift to one side.
    return Number(Math.max(0.1, Math.min(width, withMargin)).toFixed(2));
}

/**
 * Printed barcode bar height in inches for a given label page height.
 * Formula: pageHeight × 0.48 (rounded to 2 decimals).
 */
export function getBarcodeBarHeightIn(pageHeightIn = 1) {
    const height = Math.max(
        MIN_LABEL_HEIGHT_IN,
        Math.min(pageHeightIn ?? 1, MAX_LABEL_HEIGHT_IN),
    );

    return Number((height * BARCODE_HEIGHT_RATIO).toFixed(2));
}

/** Bar height in CSS pixels (96 DPI) for the label page height. */
export function getBarcodeBarHeightPx(pageHeightIn = 1) {
    return Math.max(
        10,
        Math.round(getBarcodeBarHeightIn(pageHeightIn) * PRINT_DPI),
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

/**
 * Printed text size for header + footer. Multi-line variant labels get a
 * small boost so name/code/price stay the same (larger) size.
 */
export function getLabelFooterFontSize(fontSize, headerLineCount = 1) {
    const fs = clampLabelFontSize(fontSize);
    const lines = Math.max(1, headerLineCount);

    if (lines <= 1) {
        return fs;
    }

    return Math.min(MAX_LABEL_FONT_PX, fs + LABEL_FOOTER_FONT_BOOST_PX);
}

/**
 * Fixed vertical space used by padding, header lines, gaps, and footer text.
 * Barcode bar height is NOT included — callers add that separately.
 */
export function getLabelTextChromePx(fontSize, headerLineCount = 1) {
    const lines = Math.max(1, headerLineCount);
    const fs = getLabelFooterFontSize(fontSize, lines);
    const lineHeightPx = getLabelLineHeight(fs);
    const headerBlockPx = lines * lineHeightPx + LABEL_HEADER_PAD_TOP_PX;
    const sectionGap = getNameBarcodeGap(fs);
    const footerBlockPx =
        lineHeightPx * 2 + getBarcodePriceGap() + LABEL_CODE_PRICE_GAP_PX;

    return LABEL_PADDING_Y_PX + headerBlockPx + sectionGap + footerBlockPx;
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
    const baseFs = clampLabelFontSize(fontSize ?? settings.fontSize);
    const fw = settings.fontWeight === 'bold';
    const headerLines = row ? getLabelHeaderLineCount(row) : 1;
    const fs = getLabelFooterFontSize(baseFs, headerLines);
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
    return Math.max(
        Math.floor(MIN_BARCODE_BAR_HEIGHT_PX * 0.75),
        getBarcodeBarHeightPx(heightIn),
    );
}

/**
 * Minimum label height in pixels for the requested font size and row content.
 */
export function getRequiredLabelHeightPx(settings, row, fontSize = null) {
    const fs = clampLabelFontSize(fontSize ?? settings.fontSize);
    const headerLines = row ? getLabelHeaderLineCount(row) : 1;
    // Use the caller's height for the barcode target (resolveLabelSettings passes
    // the user-requested height so auto-grow does not chase pageHeight × 0.48).
    const barHeightPx = getTargetBarcodeBarHeightPx(settings.height ?? 1);

    return getLabelTextChromePx(fs, headerLines) + barHeightPx;
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
    const requestedHeight = clampLabelHeight(settings.height);
    const requestedWidth = clampLabelWidth(settings.width);
    let height = requestedHeight;
    let width = requestedWidth;

    if (settings.autoHeight === true) {
        const labelRows = rows.length > 0 ? rows : [null];

        for (const row of labelRows) {
            // Keep barcode target tied to the user-requested height so extra
            // header lines (variants) grow the page for text, not for a taller bar.
            const context = {
                ...settings,
                fontSize,
                height: requestedHeight,
                width: requestedWidth,
            };

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
        // Barcode strip always follows the Width field — not auto-grown page width.
        requestedWidth,
        requestedHeight,
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
 * Shrinks the barcode before failing — price/header text must remain visible.
 */
export function labelLayoutFitsAtFontSize(settings, row, fontSize) {
    const { width, height } = getContentLayoutSettings(settings);
    const labelHeightPx = height * PRINT_DPI;
    const headerLines = row ? getLabelHeaderLineCount(row) : 1;
    const textChromePx = getLabelTextChromePx(fontSize, headerLines);
    const availableForBar = Math.floor(labelHeightPx - textChromePx);

    // Need room for text chrome plus a scannable-enough barcode strip.
    if (availableForBar < 10) {
        return false;
    }

    return labelTextFitsWidth(
        row,
        fontSize,
        width * PRINT_DPI - LABEL_PADDING_X_PX,
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
    if (px <= 0) {
        return 0;
    }

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
 * Never returns a bar taller than the remaining space — that clips the price.
 */
export function calculateBarcodeBarHeight(settings, row = null, fontSize = null) {
    const { height } = getContentLayoutSettings(settings);
    const resolvedFontSize =
        fontSize ?? getEffectiveLabelFontSize(settings, row);
    const labelHeightPx = height * PRINT_DPI;
    const headerLines = row ? getLabelHeaderLineCount(row) : 1;
    const textChromePx = getLabelTextChromePx(resolvedFontSize, headerLines);
    const maxBarHeight = Math.floor(labelHeightPx - textChromePx);
    const preferredMin = Math.max(10, Math.floor(labelHeightPx * 0.22));

    if (maxBarHeight <= preferredMin) {
        return Math.max(10, maxBarHeight);
    }

    return Math.max(
        preferredMin,
        Math.floor(maxBarHeight * BARCODE_HEIGHT_USAGE_RATIO),
    );
}

/**
 * Bar height for labels and preview.
 * Uses pageHeight × 0.48, clamped so header/footer text still fits.
 */
export function getLabelBarcodeBarHeight(settings, row = null) {
    const layout = getContentLayoutSettings(settings);
    const planned = getBarcodeBarHeightPx(layout.height);
    const maxFit = calculateBarcodeBarHeight(settings, row);

    return Math.max(10, Math.min(planned, maxFit));
}

/**
 * CSS width for the barcode wrapper based on the user Width field − 0.20".
 * Auto-grown page width (for long variant text) must not stretch the bars.
 */
export function getLabelBarcodeWidthIn(settings) {
    const pageWidthForBars =
        settings.requestedWidth ?? getContentLayoutSettings(settings).width;

    return getBarcodeWidthIn(pageWidthForBars);
}

/**
 * Equal left/right inset so the barcode strip is centered on the page.
 * @param {number} pageWidthIn - Actual printed page width (may be auto-grown).
 * @param {number|null} barcodeWidthIn - Barcode strip width; defaults to pageWidth − 0.20.
 */
export function getBarcodeSideMarginIn(pageWidthIn = 1.5, barcodeWidthIn = null) {
    const width = clampLabelWidth(pageWidthIn);
    const barcodeWidth = Math.min(
        width,
        barcodeWidthIn ?? getBarcodeWidthIn(width),
    );

    return Number(Math.max(0, (width - barcodeWidth) / 2).toFixed(3));
}

/**
 * CSS height for the barcode bars based on page height × 0.48.
 */
export function getLabelBarcodeHeightIn(settings, row = null) {
    const barHeightPx = getLabelBarcodeBarHeight(settings, row);

    return Number((barHeightPx / PRINT_DPI).toFixed(2));
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
 * Apply CSS-controlled printed size. Library `width` is bar thickness only;
 * wrapper/SVG CSS width & height control the physical label size.
 *
 * Prefer absolute pixel width/height attributes. Percentage SVG widths often
 * resolve against the viewport in print and shift/clip the barcode.
 */
export function finalizeBarcodeSvg(svgEl, { width = '100%', height = null } = {}) {
    if (!svgEl) {
        return;
    }

    const widthValue =
        typeof width === 'number'
            ? `${Math.max(1, Math.round(width))}px`
            : width;
    const heightValue =
        height == null
            ? null
            : typeof height === 'number'
              ? `${Math.max(1, Math.round(height))}px`
              : height;

    svgEl.setAttribute('width', widthValue);
    if (heightValue != null) {
        svgEl.setAttribute('height', heightValue);
    }

    svgEl.style.width = widthValue;
    svgEl.style.maxWidth = '100%';
    svgEl.style.minWidth = '0';
    svgEl.style.height = heightValue == null ? 'auto' : heightValue;
    svgEl.style.display = 'block';
    svgEl.style.flexShrink = '0';
    svgEl.style.margin = '0';
    svgEl.setAttribute('preserveAspectRatio', 'none');
    svgEl.setAttribute('shape-rendering', 'crispEdges');
}

/**
 * Measure the barcode wrapper and force the SVG to that exact pixel box.
 * Keeps left/right margins equal; overflow clips when the barcode is too wide.
 */
export function fitBarcodeSvgToWrapper(svgEl, wrapEl, barHeightPx) {
    if (!svgEl || !wrapEl) {
        return;
    }

    const rect = wrapEl.getBoundingClientRect();
    const widthPx = Math.max(1, Math.round(rect.width || wrapEl.clientWidth || 0));
    const heightPx = Math.max(10, Math.round(barHeightPx));

    finalizeBarcodeSvg(svgEl, { width: widthPx, height: heightPx });
}

/** Shared next-barcode / JsBarcode options for Code 128 labels. */
export function getBarcodeRenderOptions(barHeightPx) {
    return {
        format: 'CODE128',
        width: BARCODE_MODULE_WIDTH,
        height: Math.max(10, Math.round(barHeightPx)),
        displayValue: false,
        margin: BARCODE_QUIET_MARGIN_PX,
        // Keep side quiet zones for scanning; pull the footer up tightly.
        marginBottom: 0,
        background: '#ffffff',
        lineColor: '#000000',
    };
}

export function buildPrintHtml(rows, settings) {
    const resolved = resolveLabelSettings(settings, rows);
    const { width, height, fontWeight, copies } = resolved;
    const fw = fontWeight === 'bold' ? 700 : 400;
    const barcodeWidthIn = getLabelBarcodeWidthIn(resolved);
    const defaultBarHeightPx = getLabelBarcodeBarHeight(resolved);
    const defaultBarcodeHeightIn = getLabelBarcodeHeightIn(resolved);
    const renderOptions = getBarcodeRenderOptions(defaultBarHeightPx);

    const labels = rows
        .flatMap((row) => Array.from({ length: copies }, () => row))
        .map((row) => {
            const price = getEffectivePrice(row) ?? 0;
            const effectiveFontSize = getEffectiveLabelFontSize(resolved, row);
            const headerLineCount = getLabelHeaderLineCount(row);
            const textFontSize = getLabelFooterFontSize(
                effectiveFontSize,
                headerLineCount,
            );
            const barHeightPx = getLabelBarcodeBarHeight(resolved, row);
            const headerHtml = buildLabelHeaderHtml(
                row,
                textFontSize,
                fontWeight,
            );
            const codeLine = getLabelCodeLine(row);
            const lineHeight = getLabelLineHeight(textFontSize);
            const sectionGap = getNameBarcodeGap(textFontSize);

            return `
      <div class="label">
        <div class="label-content">
          <div class="label-stack">
            <div class="label-header" style="margin-bottom:${sectionGap}px;">${headerHtml}</div>
            <div class="bars-wrap">
              <svg class="bars" data-code="${escapeHtmlAttr(row.code)}" data-bar-height="${barHeightPx}"></svg>
            </div>
            <div class="footer" style="margin-top:${sectionGap}px;">
              <div class="label-line label-code" style="font-size:${textFontSize}px;line-height:${lineHeight}px;">${escapeHtml(codeLine)}</div>
              <div class="label-line label-price" style="font-size:${textFontSize}px;font-weight:700;line-height:${lineHeight}px;">${escapeHtml(formatLabelPrice(price))}</div>
            </div>
          </div>
        </div>
      </div>`;
        })
        .join('');

    // Equal left/right inset from the page edge (not from padded content).
    // Barcode strip uses the Width field; page may be wider for long variant text.
    const printedBarcodeWidthIn = Number(
        Math.min(barcodeWidthIn, width).toFixed(2),
    );
    const sideMarginIn = getBarcodeSideMarginIn(width, printedBarcodeWidthIn);
    // Content area is already inset by LABEL_PAGE_MARGIN_PX; keep text aligned
    // with the barcode strip without stacking an extra page margin.
    const textSidePadIn = Math.max(
        0,
        Number((sideMarginIn - LABEL_PAGE_MARGIN_PX / PRINT_DPI).toFixed(4)),
    );

    return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Barcode Labels</title>
  <script src="${JSBARCODE_CDN}" data-jsbarcode></script>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    /* Exact width×height — do not add orientation keywords (they rotate content). */
    @page { size: ${width}in ${height}in; margin: 0; }
    html, body {
      width: ${width}in;
      height: auto;
      margin: 0;
      padding: 0;
      background: white;
    }
    .page { display: block; width: ${width}in; }
    /*
     * Equal page margin on all sides. Header/footer use the same section gap.
     * The whole content block is centered top/bottom/left/right.
     */
    .label {
      width: ${width}in;
      height: ${height}in;
      min-height: ${height}in;
      max-width: ${width}in;
      max-height: ${height}in;
      border: 1px solid #ccc;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: ${LABEL_PAGE_MARGIN_PX}px;
      overflow: hidden;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .label-content {
      width: 100%;
      max-width: 100%;
      max-height: 100%;
      min-width: 0;
      min-height: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .label-stack {
      width: 100%;
      min-width: 0;
      min-height: 0;
      max-width: 100%;
      max-height: 100%;
      display: flex;
      flex-direction: column;
      align-items: stretch;
      justify-content: center;
      flex: 0 1 auto;
      overflow: hidden;
    }
    .label-header {
      flex: 0 0 auto;
      text-align: center;
      width: 100%;
      min-width: 0;
      max-width: 100%;
      overflow: hidden;
      margin: 0;
      padding: 0 ${textSidePadIn}in;
    }
    .label-line {
      font-weight: ${fw};
      font-family: sans-serif;
      text-align: center;
      white-space: nowrap;
      flex: 0 0 auto;
      max-width: 100%;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .label-code {
      margin-bottom: ${LABEL_CODE_PRICE_GAP_PX}px;
    }
    .label-price {
      font-weight: 700;
    }
    .bars-wrap {
      width: ${printedBarcodeWidthIn}in;
      max-width: 100%;
      min-width: 0;
      min-height: 0;
      margin: 0 auto;
      flex: 0 1 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      box-sizing: border-box;
      line-height: 0;
    }
    .bars {
      display: block;
      width: 100%;
      max-width: 100%;
      min-width: 0;
      max-height: 100%;
      height: ${defaultBarcodeHeightIn}in;
      margin: 0;
    }
    .footer {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 0 0 auto;
      margin: 0;
      width: 100%;
      min-width: 0;
      max-width: 100%;
      overflow: hidden;
      padding: 0 ${textSidePadIn}in;
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
        max-width: ${width}in;
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
    function fitBarcodeSvgToWrapper(svg, wrap, barHeightPx) {
      var rect = wrap.getBoundingClientRect();
      var widthPx = Math.max(1, Math.round(rect.width || wrap.clientWidth || 0));
      var heightPx = Math.max(10, Math.round(barHeightPx));
      svg.setAttribute('width', widthPx);
      svg.setAttribute('height', heightPx);
      svg.style.width = widthPx + 'px';
      svg.style.height = heightPx + 'px';
      svg.style.maxWidth = '100%';
      svg.style.minWidth = '0';
      svg.style.display = 'block';
      svg.style.flexShrink = '0';
      svg.style.margin = '0';
      svg.setAttribute('preserveAspectRatio', 'none');
      svg.setAttribute('shape-rendering', 'crispEdges');
    }

    function fitBarcode(wrap) {
      var svg = wrap.querySelector('.bars');
      if (!svg || !window.JsBarcode) return;
      var code = svg.getAttribute('data-code') || '';
      var barHeight = Math.max(10, parseFloat(svg.getAttribute('data-bar-height') || '${defaultBarHeightPx}'));
      window.JsBarcode(svg, code, {
        format: 'CODE128',
        width: ${renderOptions.width},
        height: barHeight,
        displayValue: false,
        margin: ${renderOptions.margin},
        marginBottom: ${renderOptions.marginBottom ?? 0},
        background: '#ffffff',
        lineColor: '#000000',
      });
      fitBarcodeSvgToWrapper(svg, wrap, barHeight);
    }

    function printWhenReady() {
      document.querySelectorAll('.bars-wrap').forEach(fitBarcode);
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          setTimeout(function() {
            window.print();
            window.close();
          }, 200);
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
