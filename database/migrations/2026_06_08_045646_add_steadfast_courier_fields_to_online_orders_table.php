<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->string('courier_invoice')->nullable()->after('courier');
            $table->unsignedBigInteger('courier_consignment_id')->nullable()->after('courier_invoice');
            $table->string('courier_tracking_code')->nullable()->after('courier_consignment_id');
            $table->string('courier_status')->nullable()->after('courier_tracking_code');
            $table->timestamp('courier_sent_at')->nullable()->after('courier_status');

            $table->index('courier_consignment_id');
            $table->index('courier_tracking_code');
            $table->unique('courier_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropUnique(['courier_invoice']);
            $table->dropIndex(['courier_consignment_id']);
            $table->dropIndex(['courier_tracking_code']);
            $table->dropColumn([
                'courier_invoice',
                'courier_consignment_id',
                'courier_tracking_code',
                'courier_status',
                'courier_sent_at',
            ]);
        });
    }
};
