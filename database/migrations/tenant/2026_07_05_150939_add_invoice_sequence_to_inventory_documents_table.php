<?php

use App\Enums\SaleType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $tables = [
        'sells' => 'INVS',
        'purchases' => 'INVP',
        'sale_returns' => 'INVSR',
        'purchase_returns' => 'INVPR',
        'damages' => 'INVD',
        'product_exchanges' => 'INVX',
    ];

    public function up(): void
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->unsignedInteger('invoice_sequence')->nullable()->after('branch_id');
                $table->index(['branch_id', 'invoice_sequence']);
            });
        }

        foreach ($this->tables as $table => $prefix) {
            $this->backfillInvoiceSequences($table, $prefix, $table === 'sells' ? SaleType::Sale->value : null);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropIndex(['branch_id', 'invoice_sequence']);
                $table->dropColumn('invoice_sequence');
            });
        }
    }

    private function backfillInvoiceSequences(string $table, string $prefix, ?int $onlyType = null): void
    {
        $query = DB::table($table)
            ->select('id', 'branch_id')
            ->orderBy('id');

        if ($onlyType !== null) {
            $query->where('type', $onlyType);
        }

        $records = $query
            ->get()
            ->groupBy('branch_id');

        foreach ($records as $branchId => $branchRecords) {
            foreach ($branchRecords->values() as $index => $record) {
                $sequence = $index + 1;

                $update = ['invoice_sequence' => $sequence];

                if (Schema::hasColumn($table, 'serial')) {
                    $update['serial'] = $prefix.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
                }

                DB::table($table)->where('id', $record->id)->update($update);
            }
        }
    }
};
