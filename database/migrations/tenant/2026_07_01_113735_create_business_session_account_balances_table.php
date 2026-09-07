<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_session_account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_session_id')->index();
            $table->foreignId('account_id')->index();
            $table->string('account_name');
            $table->unsignedTinyInteger('account_type');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->decimal('total_received', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('total_transfer_in', 15, 2)->default(0);
            $table->decimal('total_transfer_out', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['business_session_id', 'account_id'], 'bs_account_balances_session_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_session_account_balances');
    }
};
