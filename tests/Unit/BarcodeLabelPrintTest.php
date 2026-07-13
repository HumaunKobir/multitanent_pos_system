<?php

use Tests\TestCase;

uses(TestCase::class);

test('barcode print html uses label page size and next-barcode sizing rules', function () {
    $projectRoot = base_path();

    $script = <<<'JS'
import {
    buildPrintHtml,
    BARCODE_HORIZONTAL_MARGIN_IN,
    BARCODE_HEIGHT_RATIO,
    JSBARCODE_CDN,
    getLabelTitle,
    getLabelProductName,
    getLabelColor,
    getLabelCodeLine,
    getLabelSkuLine,
    getEffectiveLabelFontSize,
    getLabelBarcodeBarHeight,
    getLabelBarcodeWidthIn,
    getLabelBarcodeHeightIn,
    getBarcodeWidthIn,
    getBarcodeBarHeightIn,
    getBarcodeBarHeightPx,
    getBarcodeSideMarginIn,
    getLabelContentDimensions,
    getLabelHeaderLineCount,
    getLabelTextChromePx,
    getRequiredLabelHeightIn,
    getRequiredLabelWidthIn,
    formatLabelPrice,
    finalizeBarcodeSvg,
    fitBarcodeSvgToWrapper,
    resolveLabelSettings,
} from './resources/js/lib/barcode-label.js';

if (BARCODE_HORIZONTAL_MARGIN_IN !== 0.2) {
    process.exit(1);
}

if (BARCODE_HEIGHT_RATIO !== 0.48) {
    process.exit(39);
}

if (getBarcodeWidthIn(1.5) !== 1.3) {
    process.exit(40);
}

if (getBarcodeWidthIn(1.6) !== 1.4) {
    process.exit(41);
}

if (getBarcodeWidthIn(2) !== 1.8) {
    process.exit(42);
}

if (getBarcodeSideMarginIn(1.5) !== 0.1) {
    process.exit(65);
}

if (getBarcodeSideMarginIn(2) !== 0.1) {
    process.exit(66);
}

if (getBarcodeSideMarginIn(2, 1.3) !== 0.35) {
    process.exit(76);
}

if (getBarcodeBarHeightIn(1) !== 0.48) {
    process.exit(43);
}

if (getBarcodeBarHeightIn(1.2) !== 0.58) {
    process.exit(44);
}

if (getBarcodeBarHeightPx(1) !== 46) {
    process.exit(45);
}

const variantRow = {
    code: '1076399',
    product: { name: 'Mens-Polo/Slim Fit', code: 'MPO01' },
    variation: {
        sku: 'MPO01 435-0401 MH102022',
        price: 850,
        variation_data: { label: 'White-2XL', Color: 'White', Size: '2XL' },
    },
};

if (getLabelTitle(variantRow) !== 'Mens-Polo - White-2XL - MPO01 435-0401 MH102022') {
    process.exit(9);
}

if (getLabelProductName(variantRow) !== 'MENS-POLO') {
    process.exit(11);
}

if (getLabelColor(variantRow) !== 'WHITE') {
    process.exit(12);
}

if (getLabelCodeLine(variantRow) !== '1076399 (2XL)') {
    process.exit(13);
}

if (formatLabelPrice(850) !== 'TK: 850.00') {
    process.exit(14);
}

const simpleRow = {
    code: '5551234',
    product: {
        name: 'Basic Tee/Regular Fit',
        code: 'BT-01',
        sale_price: 500,
        color_labels: ['Red'],
        size_labels: ['L'],
    },
};

if (getLabelColor(simpleRow) !== 'RED') {
    process.exit(15);
}

if (getLabelCodeLine(simpleRow) !== '5551234 (L)') {
    process.exit(16);
}

const labelOnlyVariant = {
    code: '9988776',
    product: { name: 'T-Shirt' },
    variation: {
        sku: 'TS-BLK-S',
        variation_data: { label: 'Black-S / 30' },
    },
};

if (getLabelColor(labelOnlyVariant) !== 'BLACK') {
    process.exit(21);
}

if (getLabelCodeLine(labelOnlyVariant) !== '9988776 (S)') {
    process.exit(22);
}

const duplicateCodeRow = {
    code: '34241131',
    product: { name: 'Product Name', code: '34241131', sale_price: 500 },
};

if (getLabelSkuLine(duplicateCodeRow) !== null) {
    process.exit(23);
}

const smallLabelSettings = { width: 1.5, height: 1, fontSize: 8, fontWeight: 'normal', copies: 1, autoHeight: false };

if (getEffectiveLabelFontSize(smallLabelSettings, duplicateCodeRow) !== 8) {
    process.exit(24);
}

if (getLabelBarcodeBarHeight(smallLabelSettings, duplicateCodeRow) !== 46) {
    process.exit(25);
}

if (getLabelBarcodeWidthIn(smallLabelSettings) !== 1.3) {
    process.exit(46);
}

if (getLabelBarcodeHeightIn(smallLabelSettings, duplicateCodeRow) !== 0.48) {
    process.exit(47);
}

const variantCrowdedSettings = {
    width: 1.5,
    height: 1,
    fontSize: 9,
    fontWeight: 'bold',
    copies: 1,
    autoHeight: false,
};
const crowdedVariantBarHeight = getLabelBarcodeBarHeight(variantCrowdedSettings, variantRow);
const variantChrome =
    getLabelTextChromePx(9, getLabelHeaderLineCount(variantRow));

if (crowdedVariantBarHeight + variantChrome > 1 * 96) {
    process.exit(71);
}

if (getLabelHeaderLineCount(variantRow) < 3) {
    process.exit(72);
}

const variantAuto = resolveLabelSettings(
    { ...variantCrowdedSettings, autoHeight: true },
    [variantRow],
);

if (variantAuto.height <= 1) {
    process.exit(73);
}

const variantAutoBar = getLabelBarcodeBarHeight(variantAuto, variantRow);
const variantAutoChrome = getLabelTextChromePx(
    getEffectiveLabelFontSize(variantAuto, variantRow),
    getLabelHeaderLineCount(variantRow),
);

if (variantAutoBar + variantAutoChrome > variantAuto.height * 96 + 0.5) {
    process.exit(74);
}

if (getLabelBarcodeWidthIn(variantAuto) !== getBarcodeWidthIn(variantCrowdedSettings.width)) {
    process.exit(77);
}

if (
    variantAuto.width > variantCrowdedSettings.width &&
    getLabelBarcodeWidthIn(variantAuto) >= getBarcodeWidthIn(variantAuto.width)
) {
    process.exit(78);
}

const largeFontSettings = {
    width: 1.5,
    height: 1,
    fontSize: 16,
    fontWeight: 'bold',
    copies: 1,
    autoHeight: false,
};
const largeFontSize = getEffectiveLabelFontSize(largeFontSettings, duplicateCodeRow);
const largeFontBar = getLabelBarcodeBarHeight(
    { ...largeFontSettings, fontSize: largeFontSize },
    duplicateCodeRow,
);
const largeFontChrome = getLabelTextChromePx(
    largeFontSize,
    getLabelHeaderLineCount(duplicateCodeRow),
);

if (largeFontBar + largeFontChrome > 1 * 96) {
    process.exit(75);
}

const widePageSettings = { width: 2.5, height: 1, fontSize: 8, fontWeight: 'normal', copies: 1, autoHeight: false };
const wideContent = getLabelContentDimensions(widePageSettings);

if (wideContent.width !== 2.5 || wideContent.height !== 1) {
    process.exit(26);
}

if (getLabelBarcodeWidthIn(widePageSettings) !== 2.3) {
    process.exit(48);
}

if (getLabelBarcodeBarHeight(widePageSettings, duplicateCodeRow) !== getLabelBarcodeBarHeight(smallLabelSettings, duplicateCodeRow)) {
    process.exit(49);
}

if (getEffectiveLabelFontSize(widePageSettings, duplicateCodeRow) !== getEffectiveLabelFontSize(smallLabelSettings, duplicateCodeRow)) {
    process.exit(27);
}

const wideHtml = buildPrintHtml([duplicateCodeRow], widePageSettings);

if (! wideHtml.includes('@page { size: 2.5in 1in; margin: 0; }')) {
    process.exit(28);
}

if (! wideHtml.includes('.label-content')) {
    process.exit(29);
}

if (! wideHtml.includes('.label-stack')) {
    process.exit(56);
}

if (! wideHtml.includes('margin-top: auto') || ! wideHtml.includes('margin-bottom: auto')) {
    process.exit(57);
}

if (! wideHtml.includes('min-width: 0')) {
    process.exit(58);
}

if (! wideHtml.includes('fitBarcodeSvgToWrapper')) {
    process.exit(59);
}

if (! wideHtml.includes('getBoundingClientRect')) {
    process.exit(63);
}

if (wideHtml.includes('landscape') || wideHtml.includes('portrait')) {
    process.exit(67);
}

if (! wideHtml.includes('width: 2.5in')) {
    process.exit(30);
}

if (! wideHtml.includes('width: 2.3in')) {
    process.exit(50);
}

const tallPageSettings = { width: 1.5, height: 1.2, fontSize: 8, fontWeight: 'normal', copies: 1, autoHeight: false };

if (getLabelBarcodeWidthIn(tallPageSettings) !== 1.3) {
    process.exit(51);
}

if (getBarcodeBarHeightIn(1.2) !== 0.58) {
    process.exit(52);
}

const html = buildPrintHtml(
    [variantRow],
    smallLabelSettings,
);

if (! html.includes('@page { size: 1.5in 1in; margin: 0; }')) {
    process.exit(2);
}

if (! html.includes(JSBARCODE_CDN)) {
    process.exit(3);
}

if (! html.includes('<svg class="bars" data-code="1076399"')) {
    process.exit(4);
}

if (! html.includes('MENS-POLO')) {
    process.exit(10);
}

if (! html.includes('WHITE')) {
    process.exit(17);
}

if (! html.includes('Slim Fit')) {
    process.exit(18);
}

if (! html.includes('TK: 850.00')) {
    process.exit(19);
}

if (! html.includes('1076399 (2XL)')) {
    process.exit(20);
}

if (! html.includes('JsBarcode')) {
    process.exit(5);
}

if (! html.includes('width: 1.3in')) {
    process.exit(53);
}

if (! html.includes('height: 0.48in')) {
    process.exit(54);
}

if (! html.includes('preserveAspectRatio')) {
    process.exit(55);
}

const variantBarHeight = getLabelBarcodeBarHeight(smallLabelSettings, variantRow);

if (! html.includes(`data-bar-height="${variantBarHeight}"`)) {
    process.exit(6);
}

if (html.includes('flex: 1 1 0')) {
    process.exit(7);
}

if (html.includes('Libre Barcode 128')) {
    process.exit(8);
}

const autoHeightSettings = {
    width: 1.5,
    height: 1,
    fontSize: 15,
    fontWeight: 'bold',
    copies: 1,
    autoHeight: true,
};

const resolvedAuto = resolveLabelSettings(autoHeightSettings, [variantRow]);

if (resolvedAuto.height <= 1) {
    process.exit(31);
}

if (getEffectiveLabelFontSize(resolvedAuto, variantRow) !== 15) {
    process.exit(32);
}

const requiredHeight = getRequiredLabelHeightIn(autoHeightSettings, variantRow, 15);

if (resolvedAuto.height < requiredHeight) {
    process.exit(33);
}

const requiredWidth = getRequiredLabelWidthIn(autoHeightSettings, variantRow, 15);

if (resolvedAuto.width < requiredWidth) {
    process.exit(38);
}

const autoHtml = buildPrintHtml([variantRow], autoHeightSettings);

if (! autoHtml.includes(`@page { size: ${resolvedAuto.width}in ${resolvedAuto.height}in; margin: 0; }`)) {
    process.exit(34);
}

if (! autoHtml.includes('font-size:15px')) {
    process.exit(35);
}

const svg = {
    style: {},
    attrs: {},
    setAttribute(name, value) {
        this.attrs[name] = value;
    },
};
finalizeBarcodeSvg(svg, { width: 125, height: 46 });
if (svg.style.width !== '125px' || svg.style.height !== '46px') {
    process.exit(36);
}
if (svg.style.display !== 'block') {
    process.exit(37);
}
if (svg.attrs.width !== '125px' || svg.attrs.height !== '46px') {
    process.exit(60);
}
if (svg.attrs.preserveAspectRatio !== 'none') {
    process.exit(61);
}
if (svg.style.minWidth !== '0') {
    process.exit(62);
}

const wrap = {
    clientWidth: 130,
    getBoundingClientRect() {
        return { width: 130 };
    },
};
const fitted = {
    style: {},
    attrs: {},
    setAttribute(name, value) {
        this.attrs[name] = value;
    },
};
fitBarcodeSvgToWrapper(fitted, wrap, 46);
if (fitted.attrs.width !== '130px' || fitted.attrs.height !== '46px') {
    process.exit(64);
}

const tallPageHtml = buildPrintHtml(
    [duplicateCodeRow],
    { width: 1, height: 1.5, fontSize: 8, fontWeight: 'normal', copies: 1, autoHeight: false },
);

if (! tallPageHtml.includes('@page { size: 1in 1.5in; margin: 0; }')) {
    process.exit(68);
}

if (tallPageHtml.includes('landscape') || tallPageHtml.includes('portrait')) {
    process.exit(69);
}

if (! tallPageHtml.includes('overflow: hidden')) {
    process.exit(70);
}

console.log('ok');
JS;

    $output = shell_exec('cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script));

    expect(trim($output ?? ''))->toBe('ok');
});
