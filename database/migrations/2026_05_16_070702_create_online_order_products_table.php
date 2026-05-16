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
        Schema::create('online_order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained('online_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->restrictOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->boolean('tailor_service')->default(false);
            $table->decimal('tailor_price', 10, 2)->default(0);
            $table->json('tailormeasurement')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_order_products');
    }
};
