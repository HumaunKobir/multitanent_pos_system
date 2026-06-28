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
            $table->morphs('source');
            $table->nullableMorphs('performed_by');
            $table->date('date')->index();
            $table->decimal('amount', 15, 2);
            $table->foreignId('debit_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('credit_account_id')->nullable()->constrained('chart_of_accounts');
             $table->boolean('debit_decrease')->default(false);
            $table->boolean('credit_decrease')->default(false);
            $table->string('description')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
