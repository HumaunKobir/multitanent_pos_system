<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('category can be created and updated with image using numeric id', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['branch_id' => null]);
    $name = 'Summer Wear '.uniqid();
    $image = UploadedFile::fake()->image('category.jpg');

    $this->actingAs($admin)
        ->post(route('setting.category.store'), [
            'name' => $name,
            'status' => '1',
            'image' => $image,
        ])
        ->assertRedirect(route('setting.category.index'));

    $category = Category::query()->where('name', $name)->firstOrFail();

    expect($category->image)->not->toBeNull();
    Storage::disk('public')->assertExists($category->image);

    $replacement = UploadedFile::fake()->image('category-updated.jpg');

    $this->actingAs($admin)
        ->patch(route('setting.category.update', $category->id), [
            'name' => $name.' Updated',
            'status' => '1',
            'image' => $replacement,
        ])
        ->assertRedirect(route('setting.category.index'));

    $category->refresh();

    expect($category->name)->toBe($name.' Updated');
    Storage::disk('public')->assertExists($category->image);
});

test('brand can be created and updated with image using numeric id', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['branch_id' => null]);
    $name = 'Nova Label '.uniqid();
    $image = UploadedFile::fake()->image('brand.jpg');

    $this->actingAs($admin)
        ->post(route('setting.brand.store'), [
            'name' => $name,
            'status' => '1',
            'image' => $image,
        ])
        ->assertRedirect(route('setting.brand.index'));

    $brand = Brand::query()->where('name', $name)->firstOrFail();

    expect($brand->image)->not->toBeNull();
    Storage::disk('public')->assertExists($brand->image);

    $replacement = UploadedFile::fake()->image('brand-updated.jpg');

    $this->actingAs($admin)
        ->patch(route('setting.brand.update', $brand->id), [
            'name' => $name.' Updated',
            'status' => '1',
            'image' => $replacement,
        ])
        ->assertRedirect(route('setting.brand.index'));

    $brand->refresh();

    expect($brand->name)->toBe($name.' Updated');
    Storage::disk('public')->assertExists($brand->image);
});

test('tag can be created and updated with image using numeric id', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['branch_id' => null]);
    $name = 'Featured '.uniqid();
    $image = UploadedFile::fake()->image('tag.jpg');

    $this->actingAs($admin)
        ->post(route('setting.tag.store'), [
            'name' => $name,
            'status' => '1',
            'image' => $image,
        ])
        ->assertRedirect(route('setting.tag.index'));

    $tag = Tag::query()->where('name', $name)->firstOrFail();

    expect($tag->image)->not->toBeNull();
    Storage::disk('public')->assertExists($tag->image);

    $replacement = UploadedFile::fake()->image('tag-updated.jpg');

    $this->actingAs($admin)
        ->patch(route('setting.tag.update', $tag->id), [
            'name' => $name.' Updated',
            'status' => '1',
            'image' => $replacement,
        ])
        ->assertRedirect(route('setting.tag.index'));

    $tag->refresh();

    expect($tag->name)->toBe($name.' Updated');
    Storage::disk('public')->assertExists($tag->image);
});

test('category can be updated by numeric id when slug differs from id', function () {
    $admin = User::factory()->create(['branch_id' => null]);
    $category = Category::factory()->create(['name' => 'Binding Check']);

    expect($category->slug)->not->toBe((string) $category->id);

    $this->actingAs($admin)
        ->patch('/setting/category/'.$category->id, [
            'name' => 'Should Not Work',
            'status' => '1',
        ])
        ->assertRedirect(route('setting.category.index'));

    expect($category->fresh()->name)->toBe('Should Not Work');
});

test('category brand and tag can be deleted by numeric id', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['branch_id' => null]);

    $category = Category::factory()->create(['name' => 'Delete Category '.uniqid()]);
    $category->update(['image' => UploadedFile::fake()->image('category.jpg')->store('categories', 'public')]);

    $brand = Brand::factory()->create(['name' => 'Delete Brand '.uniqid()]);
    $brand->update(['image' => UploadedFile::fake()->image('brand.jpg')->store('brands', 'public')]);

    $tag = Tag::query()->create([
        'name' => 'Delete Tag '.uniqid(),
        'branch_id' => null,
        'status' => 1,
    ]);
    $tag->update(['image' => UploadedFile::fake()->image('tag.jpg')->store('tags', 'public')]);

    expect($category->slug)->not->toBe((string) $category->id);
    expect($brand->slug)->not->toBe((string) $brand->id);

    $this->actingAs($admin)
        ->delete(route('setting.category.destroy', $category->id))
        ->assertRedirect(route('setting.category.index'));

    $this->actingAs($admin)
        ->delete(route('setting.brand.destroy', $brand->id))
        ->assertRedirect(route('setting.brand.index'));

    $this->actingAs($admin)
        ->delete(route('setting.tag.destroy', $tag->id))
        ->assertRedirect(route('setting.tag.index'));

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
    expect(Brand::query()->whereKey($brand->id)->exists())->toBeFalse();
    expect(Tag::query()->whereKey($tag->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($category->image);
    Storage::disk('public')->assertMissing($brand->image);
    Storage::disk('public')->assertMissing($tag->image);
});

test('category index hides broken image paths that are missing on disk', function () {
    $admin = User::factory()->create(['branch_id' => null]);
    $category = Category::factory()->create([
        'name' => 'Broken Image '.uniqid(),
        'image' => 'categories/missing-file.jpg',
    ]);

    $this->actingAs($admin)
        ->get(route('setting.category.index', ['search' => $category->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/setting/category/index')
            ->where('categories.data', function ($rows) use ($category) {
                $row = collect($rows)->firstWhere('id', $category->id);

                return $row !== null
                    && $row['image'] === 'categories/missing-file.jpg'
                    && $row['image_url'] === null;
            })
        );
});

test('category image upload persists to public disk', function () {
    $admin = User::factory()->create(['branch_id' => null]);
    $name = 'Persisted Category '.uniqid();
    $image = UploadedFile::fake()->image('persisted.jpg');

    $this->actingAs($admin)
        ->post(route('setting.category.store'), [
            'name' => $name,
            'status' => '1',
            'image' => $image,
        ])
        ->assertRedirect(route('setting.category.index'));

    $category = Category::query()->where('name', $name)->firstOrFail();

    expect($category->image)->not->toBeNull();
    expect(Storage::disk('public')->exists($category->image))->toBeTrue();

    Storage::disk('public')->delete($category->image);
    $category->delete();
});

test('brand image upload persists to public disk', function () {
    $admin = User::factory()->create(['branch_id' => null]);
    $name = 'Persisted Brand '.uniqid();
    $image = UploadedFile::fake()->image('persisted-brand.webp');

    $this->actingAs($admin)
        ->post(route('setting.brand.store'), [
            'name' => $name,
            'status' => '1',
            'image' => $image,
        ])
        ->assertRedirect(route('setting.brand.index'));

    $brand = Brand::query()->where('name', $name)->firstOrFail();

    expect($brand->image)->not->toBeNull();
    expect(Storage::disk('public')->exists($brand->image))->toBeTrue();

    Storage::disk('public')->delete($brand->image);
    $brand->delete();
});
