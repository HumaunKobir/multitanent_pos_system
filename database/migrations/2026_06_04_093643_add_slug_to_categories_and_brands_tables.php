<?php

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        foreach (Category::query()->cursor() as $category) {
            if (empty($category->slug)) {
                $category->slug = Category::generateUniqueSlug($category->name);
                $category->saveQuietly();
            }
        }

        foreach (Brand::query()->cursor() as $brand) {
            if (empty($brand->slug)) {
                $brand->slug = Brand::generateUniqueSlug($brand->name);
                $brand->saveQuietly();
            }
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->unique()->change();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('slug')->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
