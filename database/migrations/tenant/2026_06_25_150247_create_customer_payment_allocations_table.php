<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();
            $table->foreignId('sell_id')->constrained('sells')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['customer_payment_id', 'sell_id'], 'customer_payment_alloc_sell_unique');
            $table->index('sell_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_allocations');
    }
};
