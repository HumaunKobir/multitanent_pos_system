<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PurchaseReportService
{
    /**
     * @return list<array{id: int, label: string}>
     */
    public function supplierOptions(): array
    {
        return Supplier::query()
            ->when($this->resolveBranchFilter(null), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'company_name'])
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'label' => $this->supplierLabel($supplier),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function branchOptions(): array
    {
        return Branch::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'label' => $branch->name,
            ])
            ->all();
    }

    public function canFilterByBranch(): bool
    {
        $branchId = $this->userBranchId();

        return $branchId === null || Branch::isMainBranch($branchId);
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     supplier_summaries: list<array<string, mixed>>,
     *     totals: array<string, float|int>,
     *     supplier: array{id: int, name: string, phone: string|null, company_name: string|null}|null
     * }
     */
    public function build(
        ?int $supplierId,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $filterBranchId = null,
    ): array {
        $branchId = $this->resolveBranchFilter($filterBranchId);

        $purchases = $this->purchaseQuery($supplierId, $dateFrom, $dateTo, $branchId)
            ->with(['supplier:id,name,phone,company_name', 'branch:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $rows = $purchases
            ->map(fn (Purchase $purchase) => $this->mapPurchaseRow($purchase))
            ->values()
            ->all();

        $supplierSummaries = $this->supplierSummaries($purchases);

        $supplier = null;
        if ($supplierId !== null) {
            $model = Supplier::query()->find($supplierId);
            if ($model !== null) {
                $supplier = [
                    'id' => $model->id,
                    'name' => $model->name,
                    'phone' => $model->phone,
                    'company_name' => $model->company_name,
                ];
            }
        }

        return [
            'rows' => $rows,
            'supplier_summaries' => $supplierSummaries,
            'totals' => $this->totalsFromRows($rows),
            'supplier' => $supplier,
        ];
    }

    /**
     * @param  Collection<int, Purchase>  $purchases
     * @return list<array<string, mixed>>
     */
    private function supplierSummaries(Collection $purchases): array
    {
        return $purchases
            ->groupBy(fn (Purchase $purchase) => $purchase->supplier_id ?? 0)
            ->map(function (Collection $group) {
                /** @var Purchase $first */
                $first = $group->first();
                $supplier = $first->supplier;

                $rows = $group->map(fn (Purchase $purchase) => $this->mapPurchaseRow($purchase))->values();
                $totals = $this->totalsFromRows($rows->all());

                return [
                    'supplier_id' => $supplier?->id,
                    'supplier_name' => $supplier?->name ?? 'Unknown supplier',
                    'supplier_phone' => $supplier?->phone,
                    'supplier_company' => $supplier?->company_name,
                    'invoice_count' => $totals['invoice_count'],
                    'gross_amount' => $totals['gross_amount'],
                    'discount' => $totals['discount'],
                    'vat' => $totals['vat'],
                    'net_amount' => $totals['net_amount'],
                    'paid_amount' => $totals['paid_amount'],
                    'due_amount' => $totals['due_amount'],
                ];
            })
            ->sortBy('supplier_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     invoice_count: int,
     *     gross_amount: float,
     *     discount: float,
     *     vat: float,
     *     net_amount: float,
     *     paid_amount: float,
     *     due_amount: float
     * }
     */
    private function totalsFromRows(array $rows): array
    {
        $sum = static fn (string $key): float => round(
            array_sum(array_map(fn (array $row) => (float) ($row[$key] ?? 0), $rows)),
            2,
        );

        return [
            'invoice_count' => count($rows),
            'gross_amount' => $sum('gross_amount'),
            'discount' => $sum('discount'),
            'vat' => $sum('vat'),
            'net_amount' => $sum('net_amount'),
            'paid_amount' => $sum('paid_amount'),
            'due_amount' => $sum('due_amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPurchaseRow(Purchase $purchase): array
    {
        $net = round($purchase->net_amount, 2);

        return [
            'id' => $purchase->id,
            'date' => $purchase->date?->format('Y-m-d'),
            'invoice' => $purchase->invoice_number,
            'supplier_id' => $purchase->supplier_id,
            'supplier_name' => $purchase->supplier?->name ?? '—',
            'supplier_phone' => $purchase->supplier?->phone,
            'branch_name' => $purchase->branch?->name ?? '—',
            'gross_amount' => round((float) $purchase->gross_amount, 2),
            'discount' => round((float) $purchase->discount, 2),
            'vat' => round((float) $purchase->vat, 2),
            'net_amount' => $net,
            'paid_amount' => round((float) $purchase->paid_amount, 2),
            'due_amount' => round((float) $purchase->due_amount, 2),
            'payment_type' => $purchase->payment_type?->name ?? '—',
        ];
    }

    private function purchaseQuery(
        ?int $supplierId,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $branchId,
    ): Builder {
        return Purchase::query()
            ->purchase()
            ->when($supplierId !== null, fn (Builder $q) => $q->where('supplier_id', $supplierId))
            ->when($dateFrom, fn (Builder $q) => $q->whereDate('date', '>=', Carbon::parse($dateFrom)->toDateString()))
            ->when($dateTo, fn (Builder $q) => $q->whereDate('date', '<=', Carbon::parse($dateTo)->toDateString()))
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId));
    }

    private function supplierLabel(Supplier $supplier): string
    {
        $parts = [$supplier->name];

        if (filled($supplier->company_name)) {
            $parts[] = $supplier->company_name;
        }

        if (filled($supplier->phone)) {
            $parts[] = $supplier->phone;
        }

        return implode(' · ', $parts);
    }

    private function resolveBranchFilter(?int $filterBranchId): ?int
    {
        if ($this->canFilterByBranch()) {
            return $filterBranchId;
        }

        return $this->userBranchId();
    }

    private function userBranchId(): ?int
    {
        return Auth::user()?->branch_id;
    }
}
