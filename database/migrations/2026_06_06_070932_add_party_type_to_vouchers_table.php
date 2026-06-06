<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['party_id']);
            $table->string('party_type')->nullable()->after('party_id');
        });

        DB::table('vouchers')
            ->whereNotNull('party_id')
            ->whereNull('party_type')
            ->update(['party_type' => Party::class]);
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('party_type');
            $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
        });
    }
};
