<?php

use App\Support\StorageUrl;
use Tests\TestCase;

uses(TestCase::class);

test('storage url helper returns null for empty path', function () {
    expect(StorageUrl::public(null))->toBeNull();
    expect(StorageUrl::public(''))->toBeNull();
});

test('storage url helper prefixes public disk paths', function () {
    $url = StorageUrl::public('products/sample.jpg');

    expect($url)->toContain('/storage/products/sample.jpg');
});

test('storage url helper leaves absolute urls unchanged', function () {
    $url = StorageUrl::public('https://cdn.example.com/image.jpg');

    expect($url)->toBe('https://cdn.example.com/image.jpg');
});
