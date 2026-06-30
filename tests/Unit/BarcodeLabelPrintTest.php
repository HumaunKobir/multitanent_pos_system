<?php

use Tests\TestCase;

uses(TestCase::class);

test('barcode print html uses label page size and jsbarcode svg renderer', function () {
    $projectRoot = base_path();

    $script = <<<'JS'
import { buildPrintHtml, BARCODE_WIDTH_SAFETY_RATIO, JSBARCODE_CDN, getLabelTitle } from './resources/js/lib/barcode-label.js';

if (BARCODE_WIDTH_SAFETY_RATIO >= 1) {
    process.exit(1);
}

const variantRow = {
    code: '12345678',
    product: { name: 'T-Shirt' },
    variation: {
        sku: 'BLK-S-30',
        variation_data: { label: 'Black-S / 30' },
    },
};

if (getLabelTitle(variantRow) !== 'T-Shirt - Black-S / 30 - BLK-S-30') {
    process.exit(9);
}

const html = buildPrintHtml(
    [variantRow],
    { width: 1.5, height: 1, fontSize: 8, fontWeight: 'normal', copies: 1 },
);

if (! html.includes('@page { size: 1.5in 1in; margin: 0; }')) {
    process.exit(2);
}

if (! html.includes(JSBARCODE_CDN)) {
    process.exit(3);
}

if (! html.includes('<svg class="bars" data-code="12345678"')) {
    process.exit(4);
}

if (! html.includes('T-Shirt - Black-S / 30 - BLK-S-30')) {
    process.exit(10);
}

if (! html.includes('JsBarcode')) {
    process.exit(5);
}

if (! html.includes('data-max-bar-height')) {
    process.exit(6);
}

if (! html.includes('flex: 1 1 0')) {
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
