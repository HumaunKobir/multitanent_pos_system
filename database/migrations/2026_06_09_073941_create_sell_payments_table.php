<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sell_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sell_id')->constrained('sells')->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['sell_id', 'payment_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_payments');
    }
};
