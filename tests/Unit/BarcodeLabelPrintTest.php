<?php

use Tests\TestCase;

uses(TestCase::class);

test('barcode print html uses label page size and jsbarcode svg renderer', function () {
    $projectRoot = base_path();

    $script = <<<'JS'
import { buildPrintHtml, BARCODE_WIDTH_SAFETY_RATIO, JSBARCODE_CDN, getLabelTitle, getLabelProductName, getLabelColor, getLabelCodeLine, getLabelSkuLine, getEffectiveLabelFontSize, getLabelBarcodeBarHeight, getLabelContentDimensions, LIST_BARCODE_BAR_HEIGHT, formatLabelPrice } from './resources/js/lib/barcode-label.js';

if (BARCODE_WIDTH_SAFETY_RATIO >= 1) {
    process.exit(1);
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

const smallLabelSettings = { width: 1.5, height: 1, fontSize: 8, fontWeight: 'normal', copies: 1 };

if (getEffectiveLabelFontSize(smallLabelSettings, duplicateCodeRow) > 7) {
    process.exit(24);
}

if (getLabelBarcodeBarHeight(smallLabelSettings, duplicateCodeRow) !== LIST_BARCODE_BAR_HEIGHT) {
    process.exit(25);
}

const widePageSettings = { width: 2.5, height: 1, fontSize: 8, fontWeight: 'normal', copies: 1 };
const wideContent = getLabelContentDimensions(widePageSettings);

if (wideContent.width !== 2.5 || wideContent.height !== 1) {
    process.exit(26);
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

if (! wideHtml.includes('width: 2.5in')) {
    process.exit(30);
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

if (! html.includes('data-max-bar-height="28"')) {
    process.exit(6);
}

if (html.includes('flex: 1 1 0')) {
    process.exit(7);
}

if (html.includes('Libre Barcode 128')) {
    process.exit(8);
}

console.log('ok');
JS;

    $output = shell_exec('cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script));

    expect(trim($output ?? ''))->toBe('ok');
});
