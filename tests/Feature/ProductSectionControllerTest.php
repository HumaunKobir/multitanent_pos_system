<?php

use App\Enums\BlockType;
use App\Enums\LayoutType;
use App\Models\Product;
use App\Models\ProductSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function productSectionAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

test('superadmin can view product sections index', function () {
    $this->actingAs(productSectionAdmin())
        ->get(route('setting.productsection.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/setting/product-section/index'));
});

test('superadmin can create item block product section', function () {
    $product = Product::factory()->create(['status' => 1]);
    $name = 'Item Section '.fake()->unique()->numerify('####');

    $this->actingAs(productSectionAdmin())
        ->post(route('setting.productsection.store'), [
            'name' => $name,
            'layout_type' => (string) LayoutType::Image_Block->value,
            'block_type' => (string) BlockType::Item->value,
            'block_per_line' => '4',
            'status' => '1',
            'description' => 'Featured products',
            'button_text' => 'View All',
            'items' => [$product->id],
        ])
        ->assertRedirect(route('setting.productsection.index'));

    $section = ProductSection::query()->where('name', $name)->first();

    expect($section)->not->toBeNull()
        ->and($section->block_type)->toBe(BlockType::Item)
        ->and($section->items)->toBe([$product->id])
        ->and($section->description)->toBe('Featured products')
        ->and($section->button_text)->toBe('View All');
});

test('superadmin can create image block product section with uploaded image', function () {
    Storage::fake('public');

    $name = 'Image Section '.fake()->unique()->numerify('####');
    $image = UploadedFile::fake()->image('banner.jpg', 800, 400);

    $this->actingAs(productSectionAdmin())
        ->post(route('setting.productsection.store'), [
            'name' => $name,
            'layout_type' => (string) LayoutType::Image_Block->value,
            'block_type' => (string) BlockType::Image->value,
            'block_per_line' => '2',
            'status' => '1',
            'image_name' => ['Summer Sale'],
            'button_text' => ['Shop Now'],
            'link' => ['/products'],
            'description' => ['Up to 50% off'],
            'images' => [$image],
        ])
        ->assertRedirect(route('setting.productsection.index'))
        ->assertSessionHasNoErrors();

    $section = ProductSection::query()->where('name', $name)->first();

    expect($section)->not->toBeNull()
        ->and($section->block_type)->toBe(BlockType::Image)
        ->and($section->images)->toHaveCount(1)
        ->and($section->images[0]['image_name'])->toBe('Summer Sale')
        ->and($section->images[0]['button_text'])->toBe('Shop Now')
        ->and($section->images[0]['link'])->toBe('/products')
        ->and($section->images[0]['description'])->toBe('Up to 50% off')
        ->and($section->images[0]['image'])->toStartWith('product-sections/');

    Storage::disk('public')->assertExists($section->images[0]['image']);
});

test('creating image block without image returns validation error', function () {
    $name = 'Missing Image '.fake()->unique()->numerify('####');

    $this->actingAs(productSectionAdmin())
        ->post(route('setting.productsection.store'), [
            'name' => $name,
            'layout_type' => (string) LayoutType::Image_Block->value,
            'block_type' => (string) BlockType::Image->value,
            'block_per_line' => '2',
            'status' => '1',
            'image_name' => ['Banner'],
        ])
        ->assertSessionHasErrors('images');

    expect(ProductSection::query()->where('name', $name)->exists())->toBeFalse();
});

test('invalid image type is rejected when creating image block', function () {
    Storage::fake('public');

    $name = 'Bad Image '.fake()->unique()->numerify('####');

    $this->actingAs(productSectionAdmin())
        ->post(route('setting.productsection.store'), [
            'name' => $name,
            'layout_type' => (string) LayoutType::Image_Block->value,
            'block_type' => (string) BlockType::Image->value,
            'block_per_line' => '2',
            'status' => '1',
            'image_name' => ['Banner'],
            'images' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasErrors('images.0');

    expect(ProductSection::query()->where('name', $name)->exists())->toBeFalse();
});

test('superadmin can update image block with a new image', function () {
    Storage::fake('public');

    $section = ProductSection::query()->create([
        'name' => 'Existing Banner '.fake()->unique()->numerify('####'),
        'block_per_line' => 2,
        'layout_type' => LayoutType::Image_Block,
        'block_type' => BlockType::Image,
        'images' => [
            [
                'image_name' => 'Old Banner',
                'image' => 'product-sections/old.jpg',
                'button_text' => 'Old CTA',
                'link' => '/old',
                'description' => 'Old text',
            ],
        ],
        'status' => 1,
        'serial' => 9990,
    ]);

    Storage::disk('public')->put('product-sections/old.jpg', 'old-image');

    $newImage = UploadedFile::fake()->image('new-banner.webp', 600, 300);

    $this->actingAs(productSectionAdmin())
        ->patch(route('setting.productsection.update', $section), [
            'name' => 'Updated Banner',
            'block_type' => (string) BlockType::Image->value,
            'block_per_line' => '3',
            'status' => '1',
            'image_name' => ['New Banner'],
            'button_text' => ['Buy Now'],
            'link' => ['/new'],
            'description' => ['Fresh promo'],
            'images' => [$newImage],
        ])
        ->assertRedirect(route('setting.productsection.index'))
        ->assertSessionHasNoErrors();

    $section->refresh();

    expect($section->name)->toBe('Updated Banner')
        ->and($section->images[0]['image_name'])->toBe('New Banner')
        ->and($section->images[0]['image'])->toStartWith('product-sections/')
        ->and($section->images[0]['image'])->not->toBe('product-sections/old.jpg');

    Storage::disk('public')->assertMissing('product-sections/old.jpg');
    Storage::disk('public')->assertExists($section->images[0]['image']);
});

test('creating item block without products returns validation error', function () {
    $this->actingAs(productSectionAdmin())
        ->post(route('setting.productsection.store'), [
            'name' => 'No Products '.fake()->unique()->numerify('####'),
            'layout_type' => (string) LayoutType::Slider->value,
            'block_type' => (string) BlockType::Item->value,
            'block_per_line' => '4',
            'status' => '1',
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});
