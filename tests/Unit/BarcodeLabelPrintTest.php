<?php

use Tests\TestCase;

uses(TestCase::class);

test('barcode print html scales with width safety margin and avoids clipping styles', function () {
    $projectRoot = base_path();

    $script = <<<'JS'
import { buildPrintHtml, BARCODE_WIDTH_SAFETY_RATIO } from './resources/js/lib/barcode-label.js';

if (BARCODE_WIDTH_SAFETY_RATIO >= 1) {
    process.exit(1);
}

const html = buildPrintHtml(
    [{ code: 'TEST123456', product: { name: 'Test Product' } }],
    { width: 2, height: 1.25, fontSize: 8, fontWeight: 'normal', copies: 1 },
);

if (! html.includes(String(BARCODE_WIDTH_SAFETY_RATIO))) {
    process.exit(2);
}

if (html.includes('max-width: 100%')) {
    process.exit(3);
}

if (! html.includes('overflow: visible')) {
    process.exit(4);
}

console.log('ok');
JS;

    $output = shell_exec('cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script));

    expect(trim($output ?? ''))->toBe('ok');
});
