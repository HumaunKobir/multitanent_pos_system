<?php

namespace App\Services;

use App\Enums\PurchaseType;
use App\Models\Purchase;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PurchaseListService
{
    public function __construct(
        private SupplierFundedStockAdjustmentService $supplierAdjustments,
    ) {}

    public function paginatedIndex(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $rows = $this->filteredRows($request);
        $page = max(1, (int) $request->input('page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function filteredRows(Request $request, ?int $limit = null): Collection
    {
        $purchaseRows = $this->purchaseQuery($request)
            ->with([
                'supplier:id,name,company_name,phone',
                'purchaseReturns:id,purchase_id,invoice_sequence',
            ])
            ->latest()
            ->when($limit !== null, fn (Builder $q) => $q->limit($limit))
            ->get()
            ->map(fn (Purchase $purchase) => $this->mapPurchaseRow($purchase));

        $adjustmentRows = $this->stockAdjustmentRows($request, $limit);

        return $purchaseRows
            ->merge($adjustmentRows)
            ->sort(function (array $a, array $b): int {
                $dateCompare = strcmp($b['date'] ?? '', $a['date'] ?? '');

                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return ($b['sort_id'] ?? 0) <=> ($a['sort_id'] ?? 0);
            })
            ->values();
    }

    private function purchaseQuery(Request $request): Builder
    {
        return $this->applyDateFilters(
            Purchase::query()->visibleInBranchCatalog()
                ->purchaseOrInitialStock()
                ->when($request->search, fn (Builder $q, string $search) => $q->where(function (Builder $q) use ($search) {
                    $q->where('invoice_sequence', 'like', "%{$search}%")
                        ->orWhere('serial', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                })),
            $request,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function stockAdjustmentRows(Request $request, ?int $limit = null): Collection
    {
        $search = $request->filled('search') ? (string) $request->input('search') : null;

        $adjustments = $this->applyDateFilters(
            $this->supplierAdjustments->branchQuery(
                $request->filled('date_from') ? $request->date('date_from')?->toDateString() : null,
                $request->filled('date_to') ? $request->date('date_to')?->toDateString() : null,
            )
                ->with([
                    'products.product:id,initial_stock_supplier_id,purchase_price,name',
                    'products.variation:id,purchase_price',
                ])
                ->when($search, fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                    $q->where('invoice_sequence', 'like', "%{$search}%")
                        ->orWhere('serial', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('products.product', fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('initialStockSupplier', fn (Builder $q) => $q
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('company_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")));
                })),
            $request,
        )
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->when($limit !== null, fn (Builder $q) => $q->limit($limit))
            ->get();

        $rows = collect();

        foreach ($this->supplierAdjustments->fundedEntries($adjustments) as $entry) {
            $rows->push($this->mapStockAdjustmentRow(
                $entry['adjustment'],
                $entry['supplier'],
                $entry['signed_amount'],
            ));
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPurchaseRow(Purchase $purchase): array
    {
        $latestReturn = $purchase->purchaseReturns->sortByDesc('id')->first();
        $isRegularPurchase = $purchase->purchase_type === PurchaseType::Purchase;

        return [
            ...$purchase->toArray(),
            'row_key' => 'purchase-'.$purchase->id,
            'sort_id' => $purchase->id,
            'row_type' => 'purchase',
            'purchase_type_label' => $purchase->purchase_type?->label() ?? 'Purchase',
            'can_edit' => $isRegularPurchase
                && $purchase->isMutableByCurrentUser()
                && $this->canEditPurchase($purchase),
            'can_delete' => $isRegularPurchase && $purchase->isMutableByCurrentUser(),
            'has_return' => $latestReturn !== null,
            'return_invoice_number' => $latestReturn?->invoice_number,
            'show_route' => 'inventory.purchase.show',
            'action_prefix' => 'inventory.purchase',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStockAdjustmentRow(
        StockAdjustment $adjustment,
        Supplier $supplier,
        float $signedAmount,
    ): array {
        $amount = round($signedAmount, 2);

        return [
            'id' => $adjustment->id,
            'row_key' => "stock-adjustment-{$adjustment->id}-{$supplier->id}",
            'sort_id' => $adjustment->id,
            'row_type' => 'stock_adjustment',
            'invoice_number' => $adjustment->invoice_number,
            'date' => $adjustment->date?->format('Y-m-d'),
            'purchase_type' => 'stock_adjustment',
            'purchase_type_label' => 'Stock Adjustment ('.$adjustment->type->label().')',
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'phone' => $supplier->phone,
            ],
            'gross_amount' => $amount,
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => 0,
            'due_amount' => $amount,
            'comment' => $adjustment->comment,
            'can_edit' => false,
            'can_delete' => false,
            'has_return' => false,
            'return_invoice_number' => null,
            'show_route' => 'inventory.stock-adjustment.show',
            'action_prefix' => 'inventory.stock-adjustment',
        ];
    }

    private function applyDateFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when(
                $request->filled('date_from'),
                fn (Builder $q) => $q->whereDate('date', '>=', $request->date('date_from')),
            )
            ->when(
                $request->filled('date_to'),
                fn (Builder $q) => $q->whereDate('date', '<=', $request->date('date_to')),
            );
    }

    private function canEditPurchase(Purchase $purchase): bool
    {
        $netAmount = round((float) $purchase->gross_amount + (float) $purchase->vat - (float) $purchase->discount, 2);
        $paidAmount = round((float) $purchase->paid_amount, 2);

        return ! ($netAmount > 0 && $paidAmount + 0.01 >= $netAmount);
    }
}
