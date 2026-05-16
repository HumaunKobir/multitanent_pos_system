<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->date('date');
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->tinyInteger('type')->default(\App\Enums\SaleType::Sale->value);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sells');
    }
};
