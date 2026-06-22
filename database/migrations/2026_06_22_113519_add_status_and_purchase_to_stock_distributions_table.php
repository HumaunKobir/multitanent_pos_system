<?php

use App\Enums\StockDistributionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')
                ->default(StockDistributionStatus::Pending->value)
                ->after('comment');
            $table->timestamp('received_at')->nullable()->after('status');
            $table->foreignId('received_by_user_id')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->after('received_by_user_id')->constrained('purchases')->nullOnDelete();
        });

        DB::table('stock_distributions')->update([
            'status' => StockDistributionStatus::Received->value,
            'received_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_id');
            $table->dropConstrainedForeignId('received_by_user_id');
            $table->dropColumn(['status', 'received_at']);
        });
    }
};
