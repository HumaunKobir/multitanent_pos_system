<?php

use App\Enums\SystemAccountKey;
use App\Models\ChartOfAccount;
use App\Services\SystemAccountService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemAccountService::seed();
    }

    public function down(): void
    {
        $cashAndBank = ChartOfAccount::query()
            ->where('account_number', SystemAccountKey::CashAndBank->accountNumber())
            ->first();

        if ($cashAndBank !== null) {
            $cashAndBank->update(['parent_id' => null]);
        }
    }
};
