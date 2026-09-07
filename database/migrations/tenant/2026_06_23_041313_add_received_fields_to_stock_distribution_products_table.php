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
        Schema::table('stock_distribution_products', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('destination_batches');
            $table->foreignId('received_by_user_id')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
        });

        DB::table('stock_distributions')
            ->where('status', StockDistributionStatus::Received->value)
            ->orderBy('id')
            ->each(function (object $distribution): void {
                DB::table('stock_distribution_products')
                    ->where('stock_distribution_id', $distribution->id)
                    ->update([
                        'received_at' => $distribution->received_at ?? $distribution->updated_at,
                        'received_by_user_id' => $distribution->received_by_user_id,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('stock_distribution_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by_user_id');
            $table->dropColumn('received_at');
        });
    }
};
