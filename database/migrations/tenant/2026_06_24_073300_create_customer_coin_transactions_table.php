<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sell_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->decimal('coins', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['sell_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_coin_transactions');
    }
};
