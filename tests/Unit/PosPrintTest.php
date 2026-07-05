<?php

use Tests\TestCase;

uses(TestCase::class);

test('pos print shows name suffix code or barcode in code column', function () {
    $projectRoot = base_path();

    $script = <<<'JS'
import {
    buildPosItemCode,
    buildPosItemDisplayName,
} from './resources/js/lib/pos-print-items.js';

const slashProduct = {
    product: { name: 'Half Shirt / 12345678', code: '9876543' },
    name: 'Half Shirt / 12345678',
    quantity: 1,
};

if (buildPosItemDisplayName(slashProduct) !== 'Half Shirt') {
    process.exit(1);
}

if (buildPosItemCode(slashProduct) !== '12345678') {
    process.exit(2);
}

const barcodeOnlyProduct = {
    product: { name: 'Premium Cotton Long Sleeve Casual Shirt', code: '5551234' },
    name: 'Premium Cotton Long Sleeve Casual Shirt',
    quantity: 1,
};

if (buildPosItemDisplayName(barcodeOnlyProduct) !== 'Premium Cotton Long Sleeve Casual Shirt') {
    process.exit(3);
}

if (buildPosItemCode(barcodeOnlyProduct) !== '5551234') {
    process.exit(4);
}

const noCodeProduct = {
    product: { name: 'Simple Tee', code: '' },
    name: 'Simple Tee',
    quantity: 1,
};

if (buildPosItemCode(noCodeProduct) !== '') {
    process.exit(5);
}

const variantProduct = {
    product: { name: 'Half Shirt / 12345678', code: '9876543' },
    name: 'Half Shirt / 12345678',
    variation_id: 10,
    variant: { name: 'White-2XL', sku: 'MPO01-WHT-2XL' },
    quantity: 1,
};

if (buildPosItemDisplayName(variantProduct) !== 'Half Shirt (Variant: White-2XL)') {
    process.exit(6);
}

if (buildPosItemCode(variantProduct) !== '12345678') {
    process.exit(7);
}

console.log('ok');
JS;

    $output = shell_exec('cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script));

    expect(trim($output ?? ''))->toBe('ok');
});
