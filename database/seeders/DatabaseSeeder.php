<?php

namespace Database\Seeders;

use App\Models\ConfigDictionary;
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

    }
}
