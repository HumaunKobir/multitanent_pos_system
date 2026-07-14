<?php

use Tests\TestCase;

uses(TestCase::class);

test('resolve barcode match picks product barcode sku and variants', function () {
    $projectRoot = base_path();

    $script = <<<'JS'
import { resolveBarcodeMatch } from './resources/js/lib/resolve-barcode-match.js';

const simple = {
    id: 1,
    code: 'PRD-1',
    has_variations: false,
    barcodes: [{ code: '5551234', product_variation_id: null }],
    variations: [],
};

const variantProduct = {
    id: 2,
    code: 'PRD-2',
    has_variations: true,
    barcodes: [
        { code: 'VB9001', product_variation_id: 10 },
        { code: 'VB9002', product_variation_id: 11 },
    ],
    variations: [
        { id: 10, sku: 'RED-S', label: 'Red S' },
        { id: 11, sku: 'BLUE-M', label: 'Blue M' },
    ],
};

const byBarcode = resolveBarcodeMatch([simple], '5551234');
if (!byBarcode || byBarcode.product.id !== 1 || byBarcode.variation !== null) {
    process.exit(1);
}

const bySku = resolveBarcodeMatch([variantProduct], 'BLUE-M');
if (!bySku || bySku.variation?.id !== 11) {
    process.exit(2);
}

const byVariantBarcode = resolveBarcodeMatch([variantProduct], 'VB9001');
if (!byVariantBarcode || byVariantBarcode.variation?.id !== 10) {
    process.exit(3);
}

const needsPick = resolveBarcodeMatch([variantProduct], 'PRD-2');
if (!needsPick?.needsVariantPick) {
    process.exit(4);
}

process.exit(0);
JS;

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(['node', '--input-type=module', '-'], $descriptors, $pipes, $projectRoot);

    expect($process)->toBeResource();

    fwrite($pipes[0], $script);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    expect($exitCode)->toBe(0, "stdout: {$stdout}\nstderr: {$stderr}");
});
