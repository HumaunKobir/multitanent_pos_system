<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collectioncategory;
use App\Models\Color;
use App\Models\ConfigDictionary;
use App\Models\Product;
use App\Models\ProductSection;
use App\Models\Size;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@coolness.com',
            'password' => bcrypt('123456789'),
        ]);

        // Site settings
        ConfigDictionary::setMany([
            'website_name' => 'Coolness Point',
            'phone' => '01XXXXXXXXX',
            'email' => 'info@coolnesspoint.com',
            'address' => 'ঢাকা, বাংলাদেশ',
            'topnotice1' => 'বিনামূল্যে ডেলিভারি ৳১৫০০+ অর্ডারে!',
            'about_us' => '<p>Coolness Point একটি প্রিমিয়াম ফ্যাশন ব্র্যান্ড।</p>',
        ]);

        // Categories
        $categories = ['পুরুষ', 'মহিলা', 'শিশু', 'এক্সেসরিজ'];
        foreach ($categories as $cat) {
            Category::create(['name' => $cat]);
        }

        // Colors & sizes
        foreach (['লাল', 'নীল', 'সবুজ', 'সাদা', 'কালো'] as $c) {
            Color::create(['name' => $c]);
        }
        foreach (['S', 'M', 'L', 'XL', 'XXL'] as $s) {
            Size::create(['name' => $s]);
        }

        // Collections
        foreach (['Summer', 'Winter', 'Eid', 'Casual', 'Formal'] as $tag) {
            Collectioncategory::create(['name' => $tag]);
        }

        // Demo products
        $products = [];
        for ($i = 1; $i <= 8; $i++) {
            $products[] = Product::create([
                'category_id' => rand(1, 4),
                'name' => "ডেমো পণ্য $i",
                'sale_price' => rand(500, 3000),
                'discount_price' => $i % 2 === 0 ? rand(300, 499) : 0,
                'colors' => ['লাল', 'নীল'],
                'sizes' => ['M', 'L', 'XL'],
                'tags' => ['Summer', 'Casual'],
                'visible' => 'yes',
                'status' => 1,
            ]);
        }

        // Product section
        ProductSection::create([
            'name' => 'নতুন কালেকশন',
            'description' => 'সর্বশেষ ফ্যাশন কালেকশন',
            'button_text' => 'সব দেখুন',
            'block_per_line' => 4,
            'layout_type' => 1,
            'block_type' => 2,
            'items' => array_column($products, 'id'),
            'status' => 1,
            'serial' => 1,
        ]);

        // Slider
        Slider::create([
            'name' => 'Hero Banner',
            'image' => 'https://placehold.co/1600x600/1a1a2e/white?text=Coolness+Point',
            'status' => 1,
        ]);
    }
}
