<?php

namespace App\Console\Commands;

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\InventoryAccountingService;
use Illuminate\Console\Command;

class CloseOpeningBalanceClearingCommand extends Command
{
    protected $signature = 'accounting:close-opening-balance-clearing
                            {--dry-run : Show clearing balances without posting close entries}';

    protected $description = "Transfer Opening Balance Clearing residuals into Owner's Capital for every chart panel";

    public function handle(InventoryAccountingService $accounting): int
    {
        $clearingAccounts = ChartOfAccount::query()
            ->where('account_number', SystemAccountKey::OpeningBalanceClearing->accountNumber())
            ->where('is_system', true)
            ->get();

        if ($clearingAccounts->isEmpty()) {
            $this->info('No Opening Balance Clearing accounts found.');

            return self::SUCCESS;
        }

        $closed = 0;
        $skipped = 0;
        $date = now()->format('Y-m-d');
        $dryRun = (bool) $this->option('dry-run');

        foreach ($clearingAccounts as $clearing) {
            $balance = round((float) $clearing->current_balance, 2);
            $branchId = $clearing->source_type === Branch::class ? (int) $clearing->source_id : null;
            $label = $branchId === null ? 'global panel' : "branch #{$branchId}";

            if (abs($balance) < 0.005) {
                $this->line("  <fg=gray>SKIP</> {$label}: already zero");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  <fg=yellow>DRY</> {$label}: would close ".number_format($balance, 2));
                $closed++;

                continue;
            }

            $accounting->closeOpeningBalanceClearingToCapital(
                $date,
                ChartOfAccount::class,
                $clearing->id,
                $branchId,
            );

            $this->line('  <fg=green>CLOSED</> '.$label.': '.number_format($balance, 2).' → Owner\'s Capital');
            $closed++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Dry run complete.' : 'Done.')." {$closed} closed, {$skipped} already zero.");

        return self::SUCCESS;
    }
}
