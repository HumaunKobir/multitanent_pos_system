<?php

use App\Support\StorageUrl;

test('storage url returns null for empty paths', function () {
    expect(StorageUrl::public(null))->toBeNull();
    expect(StorageUrl::public(''))->toBeNull();
});

test('storage url builds a root-relative public path', function () {
    $url = StorageUrl::public('products/sample.jpg');

    expect($url)->toBe('/storage/products/sample.jpg');
});

test('storage url leaves absolute urls unchanged', function () {
    $url = StorageUrl::public('https://cdn.example.com/image.jpg');

    expect($url)->toBe('https://cdn.example.com/image.jpg');
});

test('storage url leaves root-relative paths unchanged', function () {
    expect(StorageUrl::public('/storage/products/sample.jpg'))
        ->toBe('/storage/products/sample.jpg');
});
