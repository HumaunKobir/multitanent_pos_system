<?php

use App\Support\FaqContent;

test('faq content parser extracts heading and paragraph pairs', function () {
    $items = FaqContent::parse(
        '<h3>How do I order?</h3><p>Add items to cart and checkout.</p><h3>Delivery time?</h3><p>Usually 2-5 days.</p>',
    );

    expect($items)->toHaveCount(2)
        ->and($items[0]['question'])->toBe('How do I order?')
        ->and($items[0]['answer'])->toContain('Add items to cart')
        ->and($items[1]['question'])->toBe('Delivery time?');
});

test('faq content parser extracts details elements', function () {
    $items = FaqContent::parse(
        '<details><summary>Can I return items?</summary><p>Yes, within 7 days.</p></details>',
    );

    expect($items)->toHaveCount(1)
        ->and($items[0]['question'])->toBe('Can I return items?')
        ->and($items[0]['answer'])->toContain('within 7 days');
});

test('faq content defaults are returned when html is empty', function () {
    expect(FaqContent::parse(''))->toBe([])
        ->and(FaqContent::defaults())->toHaveCount(6);
});
