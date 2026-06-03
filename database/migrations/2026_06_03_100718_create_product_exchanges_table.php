<?php

use App\Enums\ReceivedPaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('sell_id')->unique()->constrained('sells')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('date');
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('price_difference', 12, 2)->default(0);
            $table->tinyInteger('payment_type')->default(ReceivedPaymentMethod::Cash->value);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('product_exchange_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('product_exchange_id')->constrained('product_exchanges')->cascadeOnDelete();
            $table->foreignId('sell_product_id')->nullable()->constrained('sell_products')->nullOnDelete();
            $table->foreignId('old_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('old_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->decimal('old_quantity', 12, 2);
            $table->decimal('old_unit_price', 12, 2)->default(0);
            $table->json('old_batches')->nullable();
            $table->foreignId('new_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('new_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->decimal('new_quantity', 12, 2);
            $table->decimal('new_unit_price', 12, 2)->default(0);
            $table->json('new_batches')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_exchange_products');
        Schema::dropIfExists('product_exchanges');
    }
};
