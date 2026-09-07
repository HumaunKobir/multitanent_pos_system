<?php

use App\Enums\CommonStatus;
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
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('sku_code')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->json('variation_data')->nullable();
            $table->tinyInteger('status')->default(CommonStatus::Active->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
