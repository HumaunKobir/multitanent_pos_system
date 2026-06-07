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

        $currentAssets = ChartOfAccount::query()
            ->where('account_number', 'SYS:current_assets')
            ->first();

        if ($currentAssets === null) {
            return;
        }

        $cashAndBankId = SystemAccountService::id(SystemAccountKey::CashAndBank);

        ChartOfAccount::query()
            ->where('parent_id', $currentAssets->id)
            ->where('is_system', false)
            ->where('type', SystemAccountKey::CashAndBank->accountType())
            ->update(['parent_id' => $cashAndBankId]);
    }

    public function down(): void
    {
        $currentAssets = ChartOfAccount::query()
            ->where('account_number', 'SYS:current_assets')
            ->first();

        if ($currentAssets === null) {
            return;
        }

        $cashAndBankId = SystemAccountService::id(SystemAccountKey::CashAndBank);

        ChartOfAccount::query()
            ->where('parent_id', $cashAndBankId)
            ->where('is_system', false)
            ->update(['parent_id' => $currentAssets->id]);
    }
};
