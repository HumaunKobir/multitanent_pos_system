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
        Schema::create('online_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->text('address');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->decimal('delivery_charge', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tailor_price', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('payment_status')->default('Pending')->comment('Pending | Paid');
            $table->string('courier')->nullable();
            $table->tinyInteger('status')->default(\App\Enums\OrderStatus::Pending);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_orders');
    }
};
