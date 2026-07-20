<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Voucher contacts are polymorphic (Supplier / Customer), not Party rows.
            $table->dropForeign(['party_id']);
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreign('party_id')
                ->references('id')
                ->on('parties')
                ->nullOnDelete();
        });
    }
};
