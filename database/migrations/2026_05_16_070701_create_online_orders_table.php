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
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->text('address');
            $table->string('payment_method')->default('cod'); // cod | sslcommerz | bkash
            $table->decimal('delivery_charge', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tailor_price', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('payment_status')->default('pending'); // pending | Paid
            $table->unsignedInteger('city_id')->nullable();
            $table->unsignedInteger('zone_id')->nullable();
            $table->unsignedInteger('area_id')->nullable();
            $table->string('courier')->nullable();
            $table->tinyInteger('status')->default(1); // 1=Pending,2=Processing,3=Shipping,5=Delivered,6=Canceled
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
