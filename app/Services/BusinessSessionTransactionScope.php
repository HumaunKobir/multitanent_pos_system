<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BusinessSession;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Damage;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\ProductInitialStock;
use App\Models\Purchase;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\StockAdjustment;
use App\Models\StockDistribution;
use App\Models\StockDistributionProduct;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BusinessSessionTransactionScope
{
    /**
     * @return array<class-string, class-string<Model>>
     */
    public function branchScopedSourceMap(): array
    {
        return [
            Purchase::class => Purchase::class,
            Sell::class => Sell::class,
            SaleReturn::class => SaleReturn::class,
            Damage::class => Damage::class,
            StockAdjustment::class => StockAdjustment::class,
            StockDistribution::class => StockDistribution::class,
            StockDistributionProduct::class => StockDistributionProduct::class,
            SupplierPayment::class => SupplierPayment::class,
            CustomerPayment::class => CustomerPayment::class,
            ProductExchange::class => ProductExchange::class,
            Product::class => Product::class,
            Supplier::class => Supplier::class,
            Customer::class => Customer::class,
            ChartOfAccount::class => ChartOfAccount::class,
            ProductInitialStock::class => ProductInitialStock::class,
            User::class => User::class,
        ];
    }

    /**
     * @param  Builder<Ledger>  $query
     * @return Builder<Ledger>
     */
    public function scopeLedgerForBranch(Builder $query, ?int $branchId): Builder
    {
        if ($branchId === null) {
            return $query;
        }

        $branchAccountIds = $this->branchAccountIdsSubquery($branchId);

        return $query->where(function (Builder $branchQuery) use ($branchId, $branchAccountIds) {
            $branchQuery->where(function (Builder $inner) use ($branchId) {
                $inner->where('source_type', Voucher::class)
                    ->whereIn(
                        'source_id',
                        Voucher::query()->where('branch_id', $branchId)->select('id'),
                    );
            });

            foreach ($this->branchScopedSourceMap() as $sourceType => $modelClass) {
                $branchQuery->orWhere(function (Builder $inner) use ($branchId, $sourceType, $modelClass) {
                    $inner->where('source_type', $sourceType)
                        ->whereIn(
                            'source_id',
                            $this->branchSourceIdsSubquery($modelClass, $branchId),
                        );
                });
            }

            $branchQuery->orWhere(function (Builder $inner) use ($branchId) {
                $inner->where('source_type', StockDistribution::class)
                    ->whereIn(
                        'source_id',
                        StockDistribution::query()->where('to_branch_id', $branchId)->select('id'),
                    );
            });

            $branchQuery->orWhereIn('account_id', $branchAccountIds);
        });
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        $isMainBranch = $branchId === null || Branch::isMainBranch($branchId);
        $mainBranchId = Branch::resolveMainBranchId();
        $branchAccountIds = $this->branchAccountIdsSubquery($branchId);

        return $query->where(function (Builder $branchQuery) use ($branchId, $isMainBranch, $mainBranchId, $branchAccountIds) {
            // Vouchers
            $branchQuery->where(function (Builder $inner) use ($branchId, $isMainBranch, $mainBranchId) {
                $inner->where('source_type', Voucher::class)
                    ->whereIn(
                        'source_id',
                        Voucher::query()
                            ->when(
                                $isMainBranch,
                                fn ($q) => $q->where(fn ($sub) => $sub->whereNull('branch_id')->orWhere('branch_id', $mainBranchId)),
                                fn ($q) => $q->where('branch_id', $branchId),
                            )
                            ->select('id'),
                    );
            });

            // Branch scoped sources
            foreach ($this->branchScopedSourceMap() as $sourceType => $modelClass) {
                $branchQuery->orWhere(function (Builder $inner) use ($branchId, $isMainBranch, $sourceType, $modelClass) {
                    $inner->where('source_type', $sourceType)
                        ->whereIn(
                            'source_id',
                            $isMainBranch
                                ? $this->headOfficeSourceIdsSubquery($modelClass)
                                : $this->branchSourceIdsSubquery($modelClass, $branchId),
                        );
                });
            }

            // Stock distribution
            $branchQuery->orWhere(function (Builder $inner) use ($branchId, $isMainBranch, $mainBranchId) {
                $inner->where('source_type', StockDistribution::class)
                    ->whereIn(
                        'source_id',
                        StockDistribution::query()
                            ->when(
                                $isMainBranch,
                                fn ($q) => $q->where(fn ($sub) => $sub->whereNull('from_branch_id')->orWhere('from_branch_id', $mainBranchId)->orWhereNull('to_branch_id')->orWhere('to_branch_id', $mainBranchId)),
                                fn ($q) => $q->where(fn ($sub) => $sub->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId)),
                            )
                            ->select('id'),
                    );
            });

            // Fallback account scope ONLY for generic/manual transactions without a defined source type
            $branchQuery->orWhere(function (Builder $inner) use ($branchAccountIds) {
                $knownSources = array_merge(
                    [Voucher::class, StockDistribution::class],
                    array_keys($this->branchScopedSourceMap())
                );
                $inner->whereNotIn('source_type', $knownSources)
                    ->where(function ($q) use ($branchAccountIds) {
                        $q->whereIn('debit_account_id', $branchAccountIds)
                            ->orWhereIn('credit_account_id', $branchAccountIds);
                    });
            });
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function branchSourceIdsSubquery(string $modelClass, int $branchId): Builder
    {
        if ($modelClass === ChartOfAccount::class) {
            return ChartOfAccount::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $branchId)
                ->select('id');
        }

        return $modelClass::query()->where('branch_id', $branchId)->select('id');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function headOfficeSourceIdsSubquery(string $modelClass): Builder
    {
        $mainBranchId = Branch::resolveMainBranchId();

        if ($modelClass === ChartOfAccount::class) {
            return ChartOfAccount::query()
                ->where(function ($q) use ($mainBranchId) {
                    $q->where(function ($inner) {
                        $inner->whereNull('source_type')->whereNull('source_id');
                    })->orWhere(function ($inner) use ($mainBranchId) {
                        $inner->where('source_type', Branch::class)->where('source_id', $mainBranchId);
                    });
                })
                ->select('id');
        }

        return $modelClass::query()
            ->where(function ($q) use ($mainBranchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $mainBranchId);
            })
            ->select('id');
    }

    /**
     * @return Builder<ChartOfAccount>
     */
    private function branchAccountIdsSubquery(?int $branchId): Builder
    {
        $query = ChartOfAccount::query()->select('id');

        if ($branchId === null || Branch::isMainBranch($branchId)) {
            $mainBranchId = Branch::resolveMainBranchId();

            return $query->where(function ($q) use ($mainBranchId) {
                $q->where(function ($inner) {
                    $inner->whereNull('source_type')->whereNull('source_id');
                })->orWhere(function ($inner) use ($mainBranchId) {
                    $inner->where('source_type', Branch::class)->where('source_id', $mainBranchId);
                });
            });
        }

        return $query->where('source_type', Branch::class)->where('source_id', $branchId);
    }

    /**
     * @param  Builder<Transaction>  $branchQuery
     * @param  Builder<ChartOfAccount>  $branchAccountIds
     */
    private function applyAccountScope(Builder $branchQuery, Builder $branchAccountIds): void
    {
        $branchQuery->orWhere(function (Builder $inner) use ($branchAccountIds) {
            $inner->whereIn('debit_account_id', $branchAccountIds)
                ->orWhereIn('credit_account_id', $branchAccountIds);
        });
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeWithinSessionWindow(Builder $query, BusinessSession $session): Builder
    {
        $query->where('created_at', '>=', $session->started_at);

        if ($session->closed_at !== null) {
            $query->where('created_at', '<=', $session->closed_at);
        }

        return $query;
    }

    public function syncSessionTransactions(BusinessSession $session): int
    {
        $validBranchTxIds = Transaction::query()
            ->where('business_session_id', $session->id)
            ->tap(fn ($q) => $this->scopeForBranch($q, $session->branch_id))
            ->pluck('id');

        return Transaction::query()
            ->where('business_session_id', $session->id)
            ->where(function ($q) use ($session, $validBranchTxIds) {
                $q->where('created_at', '<', $session->started_at)
                    ->orWhere('performed_by_type', '!=', User::class)
                    ->orWhere('performed_by_id', '!=', $session->started_by_user_id)
                    ->orWhereNull('performed_by_id')
                    ->orWhereNotIn('id', $validBranchTxIds);

                if ($session->closed_at !== null) {
                    $q->orWhere('created_at', '>', $session->closed_at);
                }
            })
            ->update(['business_session_id' => null]);
    }
}
