<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->renameColumn('type', 'purchase_type');
            $table->unsignedBigInteger('parent_purchase_id')->nullable()->after('supplier_id');
            $table->decimal('demarace', 12, 2)->default(0)->after('paid_amount');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->renameColumn('unit_price', 'purchase_price');
            $table->renameColumn('quantity', 'available');
            $table->foreignId('supplier_id')->nullable()->after('product_id')->constrained('suppliers')->nullOnDelete();
            $table->string('serial')->nullable()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->renameColumn('purchase_type', 'type');
            $table->dropColumn(['parent_purchase_id', 'demarace']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->renameColumn('purchase_price', 'unit_price');
            $table->renameColumn('available', 'quantity');
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'serial']);
        });
    }
};
