<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->decimal('earn_spend_amount', 15, 2)->default(100);
            $table->decimal('earn_coins', 15, 2)->default(1);
            $table->decimal('coin_value', 15, 2)->default(1);
            $table->decimal('min_redeem_coins', 15, 2)->default(0);
            $table->decimal('max_redeem_percent', 5, 2)->default(50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_settings');
    }
};
