<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\BranchSubscriptionService;
use App\Services\SubscriptionInvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessSubscriptionBillingCommand extends Command
{
    protected $signature = 'subscription:process-billing';

    protected $description = 'Process automated SaaS subscription billing cycles, generate invoices, and apply advance balances.';

    public function handle(
        BranchSubscriptionService $subscriptionService,
        SubscriptionInvoiceService $invoiceService,
    ): int {
        $this->info('Starting automated subscription billing processing...');

        $mainBranchId = Branch::resolveMainBranchId();
        $branches = Branch::query()
            ->where('id', '!=', $mainBranchId)
            ->where(function ($q) {
                $q->whereNull('subscription_status')
                    ->orWhere('subscription_status', '!=', 'lifetime');
            })
            ->get();

        $processedCount = 0;
        $invoicesGenerated = 0;

        foreach ($branches as $branch) {
            $cycleDays = $subscriptionService->resolveCycleDays($branch);
            if (! $cycleDays || $cycleDays <= 0) {
                continue;
            }

            $fee = (float) ($branch->subscription_fee ?? 1500);
            $startDate = $branch->subscription_starts_at ? Carbon::parse($branch->subscription_starts_at)->startOfDay() : Carbon::today();
            $today = Carbon::today();

            // Iterate over all elapsed billing cycles from start date up to current date
            $cursorStart = $startDate->copy();
            while ($cursorStart->lte($today)) {
                $cursorEnd = $cursorStart->copy()->addDays($cycleDays);

                $invoice = $invoiceService->generateInvoice(
                    $branch,
                    $cursorStart->toDateString(),
                    $cursorEnd->toDateString(),
                    $fee
                );

                if ($invoice->wasRecentlyCreated) {
                    $invoicesGenerated++;
                }

                $cursorStart = $cursorEnd->copy();
            }

            $subscriptionService->syncOverdueLiability($branch);
            $processedCount++;
        }

        $this->info("Completed billing processing. Processed {$processedCount} branches. Generated {$invoicesGenerated} invoices.");

        return self::SUCCESS;
    }
}
