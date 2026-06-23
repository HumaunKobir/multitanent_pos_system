export const PRINT_DPI = 96;
export const NAME_BARCODE_GAP_PX = 6;
export const BARCODE_PRICE_GAP_PX = 1;
/** Minimum bar height for reliable scanner reads (~8.5mm at 96 DPI). */
export const MIN_BARCODE_BAR_HEIGHT_PX = 32;
/** Floor when scaling barcode width to fit the label. */
export const MIN_BARCODE_FONT_PX = 18;
/**
 * Leave horizontal headroom so barcode quiet zones are not clipped in print
 * (browser/print engines often render slightly wider than on-screen measurement).
 */
export const BARCODE_WIDTH_SAFETY_RATIO = 0.86;
/** Use most of the vertical space between name and price for bar height. */
export const BARCODE_HEIGHT_USAGE_RATIO = 0.98;

export function getNameBarcodeGap(fontSize) {
    return Math.max(NAME_BARCODE_GAP_PX, Math.ceil(fontSize * 0.5));
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

    return `Price: ${amount.toFixed(2)}`;
}

export function getLabelName(row) {
    return row?.product?.name ?? row?.name ?? 'Product Name';
}

export function getLabelTitle(row) {
    const baseName = getLabelName(row);

    return row?.code ? `${baseName} - ${row.code}` : baseName;
}

/**
 * Fit barcode height inside the label without clipping name/price rows.
 */
export function calculateBarcodeBarHeight(settings) {
    const { height, fontSize } = settings;
    const labelHeightPx = height * PRINT_DPI;
    const paddingY = 6;
    const nameLinePx = Math.ceil(fontSize * 1.2);
    const nameBarcodeGapPx = getNameBarcodeGap(fontSize);
    const barcodePriceGapPx = getBarcodePriceGap();
    const footerLinePx = fontSize + barcodePriceGapPx;
    const maxBarHeight =
        labelHeightPx -
        paddingY -
        nameLinePx -
        nameBarcodeGapPx -
        footerLinePx;

    if (maxBarHeight <= MIN_BARCODE_BAR_HEIGHT_PX) {
        return Math.max(12, maxBarHeight);
    }

    return Math.max(
        MIN_BARCODE_BAR_HEIGHT_PX,
        Math.floor(maxBarHeight * BARCODE_HEIGHT_USAGE_RATIO),
    );
}

/**
 * Fit barcode width with scaleX so bar height (font-size) stays scannable.
 */
export function fitBarcodeToContainer(barEl, containerEl, maxBarHeight, { fill = false } = {}) {
    if (!barEl || !containerEl) {
        return maxBarHeight;
    }

    const targetWidth = containerEl.clientWidth * BARCODE_WIDTH_SAFETY_RATIO;
    const availableHeight = containerEl.clientHeight;
    const heightFromContainer =
        fill && availableHeight > 8
            ? Math.floor(availableHeight * BARCODE_HEIGHT_USAGE_RATIO)
            : maxBarHeight;
    const baseHeight = Math.max(
        MIN_BARCODE_BAR_HEIGHT_PX,
        Math.min(maxBarHeight, heightFromContainer),
    );

    barEl.style.transform = 'none';
    barEl.style.transformOrigin = 'center center';
    barEl.style.width = 'auto';
    barEl.style.maxWidth = 'none';
    barEl.style.display = 'inline-block';
    barEl.style.fontSize = `${baseHeight}px`;

    const textWidth = barEl.scrollWidth;

    if (textWidth > targetWidth && targetWidth > 0 && textWidth > 0) {
        barEl.style.transform = `scaleX(${targetWidth / textWidth})`;
    }

    return barEl.offsetHeight || baseHeight;
}

export function buildPrintHtml(rows, settings) {
    const { width, height, fontSize, fontWeight, copies } = settings;
    const fw = fontWeight === 'bold' ? 700 : 400;
    const barHeight = calculateBarcodeBarHeight(settings);
    const nameBarcodeGap = getNameBarcodeGap(fontSize);
    const barcodePriceGap = getBarcodePriceGap();

    const labels = rows
        .flatMap((row) => Array.from({ length: copies }, () => row))
        .map((row) => {
            const price = getEffectivePrice(row) ?? 0;
            const labelName = getLabelTitle(row);

            return `
      <div class="label">
        <div class="label-inner">
          <div class="name">${labelName}</div>
          <div class="bars-wrap">
            <div class="bars" data-max-bar-height="${barHeight}">${row.code}</div>
          </div>
          <div class="footer">
            <span>${formatLabelPrice(price)}</span>
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
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
      align-items: stretch;
      justify-content: center;
      padding: 3px 5px;
      overflow: hidden;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .label-inner {
      width: 100%;
      height: 100%;
      min-height: 0;
      display: flex;
      flex-direction: column;
      justify-content: stretch;
      gap: 0;
    }
    .name {
      font-size: ${fontSize}px;
      font-weight: ${fw};
      font-family: sans-serif;
      text-align: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.2;
      flex-shrink: 0;
      margin-bottom: ${nameBarcodeGap}px;
    }
    .bars-wrap {
      width: 100%;
      flex: 1 1 0;
      min-height: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      padding: 0 6px;
      box-sizing: border-box;
    }
    .bars {
      font-family: 'Libre Barcode 128', monospace;
      font-weight: ${fw};
      font-size: ${barHeight}px;
      line-height: 1;
      white-space: nowrap;
      display: inline-block;
    }
    .footer {
      display: flex;
      justify-content: center;
      font-size: ${fontSize}px;
      font-weight: ${fw};
      font-family: monospace;
      line-height: 1;
      flex-shrink: 0;
      margin-top: ${barcodePriceGap}px;
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
      var el = wrap.querySelector('.bars');
      if (!el) return;
      var maxBarHeight = parseFloat(el.getAttribute('data-max-bar-height') || '${barHeight}');
      var targetWidth = wrap.clientWidth * ${BARCODE_WIDTH_SAFETY_RATIO};
      var availableHeight = wrap.clientHeight;
      var heightFromContainer = availableHeight > 8
        ? Math.floor(availableHeight * ${BARCODE_HEIGHT_USAGE_RATIO})
        : maxBarHeight;
      var baseHeight = Math.max(
        ${MIN_BARCODE_BAR_HEIGHT_PX},
        Math.min(maxBarHeight, heightFromContainer)
      );
      el.style.transform = 'none';
      el.style.transformOrigin = 'center center';
      el.style.width = 'auto';
      el.style.maxWidth = 'none';
      el.style.display = 'inline-block';
      el.style.fontSize = baseHeight + 'px';
      var tw = el.scrollWidth;
      if (tw > targetWidth && targetWidth > 0 && tw > 0) {
        el.style.transform = 'scaleX(' + (targetWidth / tw) + ')';
      }
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

    document.fonts.ready.then(printWhenReady).catch(printWhenReady);
  <\/script>
</body>
</html>`;
}
