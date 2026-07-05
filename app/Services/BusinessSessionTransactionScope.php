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
        $branchAccountIds = $this->branchAccountIdsSubquery($branchId);

        if ($branchId === null) {
            return $query->where(function (Builder $branchQuery) use ($branchAccountIds) {
                $branchQuery->where(function (Builder $inner) {
                    $inner->where('source_type', Voucher::class)
                        ->whereIn(
                            'source_id',
                            Voucher::query()->whereNull('branch_id')->select('id'),
                        );
                });

                foreach ($this->branchScopedSourceMap() as $sourceType => $modelClass) {
                    $branchQuery->orWhere(function (Builder $inner) use ($sourceType, $modelClass) {
                        $inner->where('source_type', $sourceType)
                            ->whereIn(
                                'source_id',
                                $this->headOfficeSourceIdsSubquery($modelClass),
                            );
                    });
                }

                $branchQuery->orWhere(function (Builder $inner) {
                    $inner->where('source_type', StockDistribution::class)
                        ->whereIn(
                            'source_id',
                            StockDistribution::query()->whereNull('from_branch_id')->select('id'),
                        );
                });

                $this->applyAccountScope($branchQuery, $branchAccountIds);
            });
        }

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

            $this->applyAccountScope($branchQuery, $branchAccountIds);
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
        if ($modelClass === ChartOfAccount::class) {
            return ChartOfAccount::query()
                ->whereNull('source_type')
                ->whereNull('source_id')
                ->select('id');
        }

        return $modelClass::query()->whereNull('branch_id')->select('id');
    }

    /**
     * @return Builder<ChartOfAccount>
     */
    private function branchAccountIdsSubquery(?int $branchId): Builder
    {
        $query = ChartOfAccount::query()->select('id');

        if ($branchId === null) {
            return $query->whereNull('source_type')->whereNull('source_id');
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
        return Transaction::query()
            ->where('business_session_id', $session->id)
            ->where('created_at', '<', $session->started_at)
            ->update(['business_session_id' => null]);
    }
}
