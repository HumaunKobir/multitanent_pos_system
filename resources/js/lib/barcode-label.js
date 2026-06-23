export const PRINT_DPI = 96;
export const NAME_BARCODE_GAP_PX = 6;
export const BARCODE_PRICE_GAP_PX = 1;
/** Minimum bar height for reliable scanner reads (~7mm at 96 DPI). */
export const MIN_BARCODE_BAR_HEIGHT_PX = 28;
/** Floor when scaling barcode width to fit the label. */
export const MIN_BARCODE_FONT_PX = 18;
/**
 * Leave horizontal headroom so barcode quiet zones are not clipped in print
 * (browser/print engines often render slightly wider than on-screen measurement).
 */
export const BARCODE_WIDTH_SAFETY_RATIO = 0.92;

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
    const paddingY = 8;
    const nameLinePx = Math.ceil(fontSize * 1.2) + 1;
    const nameBarcodeGapPx = getNameBarcodeGap(fontSize);
    const barcodePriceGapPx = getBarcodePriceGap();
    const footerLinePx = fontSize + barcodePriceGapPx;
    const maxBarHeight = labelHeightPx - paddingY - nameLinePx - nameBarcodeGapPx - footerLinePx;

    if (maxBarHeight <= MIN_BARCODE_BAR_HEIGHT_PX) {
        return Math.max(12, maxBarHeight);
    }

    const targetBarHeight = Math.floor(maxBarHeight * 0.7);

    return Math.max(
        MIN_BARCODE_BAR_HEIGHT_PX,
        Math.min(targetBarHeight, maxBarHeight),
    );
}

/**
 * Scale Libre Barcode 128 text to container width using font-size (print-safe).
 */
export function fitBarcodeToContainer(barEl, containerEl, baseHeight) {
    if (!barEl || !containerEl) {
        return baseHeight;
    }

    const targetWidth = containerEl.clientWidth * BARCODE_WIDTH_SAFETY_RATIO;

    barEl.style.transform = 'none';
    barEl.style.width = 'auto';
    barEl.style.maxWidth = 'none';
    barEl.style.display = 'inline-block';
    barEl.style.fontSize = `${baseHeight}px`;

    if (barEl.scrollWidth > targetWidth && targetWidth > 0) {
        const scale = targetWidth / barEl.scrollWidth;
        let fontSize = Math.max(
            MIN_BARCODE_FONT_PX,
            Math.floor(baseHeight * scale),
        );

        barEl.style.fontSize = `${fontSize}px`;

        while (barEl.scrollWidth > targetWidth && fontSize > MIN_BARCODE_FONT_PX) {
            fontSize -= 1;
            barEl.style.fontSize = `${fontSize}px`;
        }
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
          <div class="bars-wrap" style="height:${barHeight}px">
            <div class="bars" data-bar-height="${barHeight}">${row.code}</div>
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
    html, body { width: 100%; background: white; }
    .page { display: flex; flex-wrap: wrap; }
    .label {
      width: ${width}in;
      height: ${height}in;
      border: 1px solid #ccc;
      display: flex;
      align-items: stretch;
      justify-content: center;
      padding: 4px 6px;
      overflow: hidden;
      page-break-inside: avoid;
    }
    .label-inner {
      width: 100%;
      min-height: 0;
      display: flex;
      flex-direction: column;
      justify-content: center;
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
      flex: 0 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: visible;
      padding: 0 4px;
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
      @page { margin: 0; }
      body { margin: 0; }
      .label { border: none; }
    }
  </style>
</head>
<body>
  <div class="page">${labels}</div>
  <script>
    function fitBarcode(wrap) {
      var el = wrap.querySelector('.bars');
      if (!el) return;
      var baseHeight = parseFloat(el.getAttribute('data-bar-height') || '${barHeight}');
      var targetWidth = wrap.clientWidth * ${BARCODE_WIDTH_SAFETY_RATIO};
      el.style.transform = 'none';
      el.style.width = 'auto';
      el.style.maxWidth = 'none';
      el.style.display = 'inline-block';
      el.style.fontSize = baseHeight + 'px';
      if (el.scrollWidth > targetWidth && targetWidth > 0) {
        var scale = targetWidth / el.scrollWidth;
        var fontSize = Math.max(${MIN_BARCODE_FONT_PX}, Math.floor(baseHeight * scale));
        el.style.fontSize = fontSize + 'px';
        while (el.scrollWidth > targetWidth && fontSize > ${MIN_BARCODE_FONT_PX}) {
          fontSize -= 1;
          el.style.fontSize = fontSize + 'px';
        }
      }
      wrap.style.height = Math.max(el.offsetHeight + 2, 12) + 'px';
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
