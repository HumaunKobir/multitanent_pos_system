<?php

use Tests\TestCase;

uses(TestCase::class);

test('barcode print html uses label page size and encodes code 128 for scanning', function () {
    $projectRoot = base_path();

    $script = <<<'JS'
import {
    buildPrintHtml,
    BARCODE_WIDTH_SAFETY_RATIO,
    encodeCode128B,
} from './resources/js/lib/barcode-label.js';

if (BARCODE_WIDTH_SAFETY_RATIO >= 1) {
    process.exit(1);
}

const encoded = encodeCode128B('TEST123456');
if (! encoded.startsWith(String.fromCharCode(204))) {
    process.exit(2);
}
if (! encoded.endsWith(String.fromCharCode(206))) {
    process.exit(3);
}

const html = buildPrintHtml(
    [{ code: 'TEST123456', product: { name: 'Test Product' } }],
    { width: 2, height: 1.25, fontSize: 8, fontWeight: 'normal', copies: 1 },
);

if (! html.includes('@page { size: 2in 1.25in; margin: 0; }')) {
    process.exit(4);
}

if (html.includes('scaleX(')) {
    process.exit(5);
}

if (! html.includes(encoded)) {
    process.exit(6);
}

if (! html.includes('data-max-bar-height')) {
    process.exit(7);
}

if (! html.includes('flex: 1 1 0')) {
    process.exit(8);
}

if (html.includes('wrap.style.height')) {
    process.exit(9);
}

console.log('ok');
JS;

    $output = shell_exec('cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script));

    expect(trim($output ?? ''))->toBe('ok');
});
