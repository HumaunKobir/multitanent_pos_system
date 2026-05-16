<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->foreignId('brand_id')->nullable()->constrained('brands');
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->foreignId('warranty_id')->nullable()->constrained('warranties');
            $table->string('name');
            $table->string('bn_name')->nullable();
            $table->string('slug')->unique();
            $table->string('code')->nullable()->unique();
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->decimal('discount_price', 10, 2)->default(0);
            $table->decimal('wholesale_price', 10, 2)->default(0);
            $table->string('wholesale_title')->nullable();
            $table->string('type')->default('notstitch'); // stitch | notstitch
            $table->string('tailor_option')->default('no'); // yes | no
            $table->decimal('tailor_price', 10, 2)->default(0);
            $table->json('colors')->nullable();
            $table->json('sizes')->nullable();
            $table->json('tailormeasurement')->nullable();
            $table->json('tags')->nullable();
            $table->string('image')->nullable();
            $table->string('chest_size_image')->nullable();
            $table->string('youtube_link')->nullable();
            $table->text('description')->nullable();
            $table->text('bn_description')->nullable();
            $table->text('delivery_info')->nullable();
            $table->text('bn_delivery_info')->nullable();
            $table->string('visible')->default('yes'); // yes | no
            $table->string('availabe_area')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
