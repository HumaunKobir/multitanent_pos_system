<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->decimal('due_amount', 12, 2)->default(0)->after('paid_amount');
        });

        DB::table('sale_returns')->orderBy('id')->each(function (object $row): void {
            $net = round(max(0, (float) $row->gross_amount + (float) $row->vat_amount - (float) $row->discount_amount), 2);
            $due = round(max(0, $net - (float) $row->paid_amount), 2);

            DB::table('sale_returns')->where('id', $row->id)->update(['due_amount' => $due]);
        });
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('due_amount');
        });
    }
};
