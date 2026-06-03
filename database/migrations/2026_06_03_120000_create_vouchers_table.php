<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type')->index();
            $table->string('voucher_no')->unique();
            $table->date('date')->index();
            $table->string('transaction_reference')->nullable();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('from_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->text('narration')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
