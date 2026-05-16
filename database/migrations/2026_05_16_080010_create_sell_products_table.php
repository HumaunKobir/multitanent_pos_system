<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sell_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('sell_id')->constrained('sells')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->restrictOnDelete();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->json('batches')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_products');
    }
};
