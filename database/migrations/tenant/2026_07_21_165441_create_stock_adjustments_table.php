<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->string('serial')->nullable();
            $table->unsignedBigInteger('invoice_sequence')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->json('batches')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_products');
        Schema::dropIfExists('stock_adjustments');
    }
};
