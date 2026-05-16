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
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            // 1=Sale, 5=Sale_Return, 10=Exchange
            $table->tinyInteger('type')->default(1);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('child_id')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('sells')->nullOnDelete();
            $table->foreign('child_id')->references('id')->on('sells')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sells');
    }
};
