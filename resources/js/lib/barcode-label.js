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

const CODE128_START_B = 204;
const CODE128_STOP = 206;

// #region agent log
function debugLog(location, message, data, hypothesisId) {
    fetch('http://127.0.0.1:7682/ingest/b2b77a02-47d0-43f6-ab11-689e8f32c576', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Debug-Session-Id': '601285',
        },
        body: JSON.stringify({
            sessionId: '601285',
            location,
            message,
            data,
            timestamp: Date.now(),
            hypothesisId,
        }),
    }).catch(() => {});
}
// #endregion

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

    // #region agent log
    debugLog('barcode-label.js:encodeCode128B', 'encoded barcode', {
        inputLen: text.length,
        outputLen: encoded.length,
        startChar: encoded.charCodeAt(0),
        stopChar: encoded.charCodeAt(encoded.length - 1),
        checksumValue: checksum,
        checksumChar: encoded.charCodeAt(encoded.length - 2),
    }, 'A');
    // #endregion

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
    let height = Math.max(
        MIN_BARCODE_BAR_HEIGHT_PX,
        Math.min(maxBarHeight, heightFromContainer),
    );

    barEl.style.transform = 'none';
    barEl.style.transformOrigin = 'center center';
    barEl.style.width = 'auto';
    barEl.style.maxWidth = 'none';
    barEl.style.display = 'inline-block';

    const minHeight = Math.max(MIN_BARCODE_BAR_HEIGHT_PX, MIN_BARCODE_FONT_PX);

    do {
        barEl.style.fontSize = `${height}px`;

        if (barEl.scrollWidth <= targetWidth || height <= minHeight) {
            break;
        }

        height -= 1;
    } while (height >= minHeight);

    const fontFamily = getComputedStyle(barEl).fontFamily;
    const fontLoaded = document.fonts?.check
        ? document.fonts.check(`16px ${fontFamily}`)
        : null;

    // #region agent log
    debugLog('barcode-label.js:fitBarcodeToContainer', 'fit result', {
        fill,
        maxBarHeight,
        containerW: containerEl.clientWidth,
        containerH: containerEl.clientHeight,
        targetWidth,
        finalFontSize: height,
        scrollWidth: barEl.scrollWidth,
        offsetHeight: barEl.offsetHeight,
        widthOverflow: barEl.scrollWidth > targetWidth,
        fontFamily,
        fontLoaded,
        transform: barEl.style.transform || 'none',
    }, 'B,C,D');
    // #endregion

    return barEl.offsetHeight || height;
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
            const encodedCode = formatBarcodeForLibre128(row.code);
            const escapedCode = escapeHtml(encodedCode);

            // #region agent log
            debugLog('barcode-label.js:buildPrintHtml', 'print label barcode', {
                rawCodeLen: String(row.code ?? '').length,
                encodedLen: encodedCode.length,
                escapedLen: escapedCode.length,
                htmlCorrupted: encodedCode !== escapedCode,
                settings: { width, height, fontSize, copies },
                barHeight,
            }, 'E');
            // #endregion

            return `
      <div class="label">
        <div class="label-inner">
          <div class="name">${escapeHtml(labelName)}</div>
          <div class="bars-wrap">
            <div class="bars" data-max-bar-height="${barHeight}">${escapedCode}</div>
          </div>
          <div class="footer">
            <span>${escapeHtml(formatLabelPrice(price))}</span>
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
      var height = Math.max(
        ${MIN_BARCODE_BAR_HEIGHT_PX},
        Math.min(maxBarHeight, heightFromContainer)
      );
      var minHeight = Math.max(${MIN_BARCODE_BAR_HEIGHT_PX}, ${MIN_BARCODE_FONT_PX});
      el.style.transform = 'none';
      el.style.transformOrigin = 'center center';
      el.style.width = 'auto';
      el.style.maxWidth = 'none';
      el.style.display = 'inline-block';
      do {
        el.style.fontSize = height + 'px';
        if (el.scrollWidth <= targetWidth || height <= minHeight) {
          break;
        }
        height -= 1;
      } while (height >= minHeight);
      fetch('http://127.0.0.1:7682/ingest/b2b77a02-47d0-43f6-ab11-689e8f32c576',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'601285'},body:JSON.stringify({sessionId:'601285',location:'print-window:fitBarcode',message:'print fit result',data:{containerW:wrap.clientWidth,containerH:wrap.clientHeight,targetWidth:targetWidth,finalFontSize:height,scrollWidth:el.scrollWidth,offsetHeight:el.offsetHeight,widthOverflow:el.scrollWidth>targetWidth,textLen:el.textContent.length,startChar:el.textContent.charCodeAt(0),stopChar:el.textContent.charCodeAt(el.textContent.length-1)},timestamp:Date.now(),hypothesisId:'B,C,E'})}).catch(function(){});
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
