<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            // polymorphic: Account / Supplier / Customer
            $table->nullableMorphs('transactionable');
            // polymorphic: Purchase / Sell / Expense / Income / etc.
            $table->nullableMorphs('reference');
            $table->tinyInteger('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
