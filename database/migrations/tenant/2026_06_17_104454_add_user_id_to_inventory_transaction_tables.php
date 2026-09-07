<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'sells',
        'purchases',
        'sale_returns',
        'purchase_returns',
        'damages',
        'product_exchanges',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $blueprint->index(['branch_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['branch_id', 'user_id']);
                $blueprint->dropConstrainedForeignId('user_id');
            });
        }
    }
};
