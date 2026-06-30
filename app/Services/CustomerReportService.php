<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sell;
use Illuminate\Support\Facades\Auth;

class CustomerReportService
{
    public function __construct(private PartyPaymentAllocationService $allocations) {}

    /**
     * @return array{
     *     customer: array<string, mixed>,
     *     sales: list<array<string, mixed>>,
     *     collections: list<array<string, mixed>>,
     *     due_sales: list<array<string, mixed>>,
     *     totals: array<string, float>
     * }
     */
    public function build(Customer $customer): array
    {
        $customer->loadMissing('branch:id,name');

        $sales = Sell::query()
            ->sale()
            ->where('customer_id', $customer->id)
            ->when($this->branchId(), fn ($query, int $branchId) => $query->where('branch_id', $branchId))
            ->with('products:id,sell_id,discount')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $collections = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->when($this->branchId(), fn ($query, int $branchId) => $query->where('branch_id', $branchId))
            ->with('createdBy:id,name')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $dueSales = $this->allocations->dueSalesForCustomer($customer);

        $salesNet = round($sales->sum(fn (Sell $sell) => $sell->net_amount), 2);
        $salesPaid = round($sales->sum(fn (Sell $sell) => (float) $sell->paid_amount), 2);
        $collectionsTotal = round($collections->sum(fn (CustomerPayment $payment) => (float) $payment->amount), 2);
        $currentDueTotal = round($dueSales->sum(fn (array $sale) => (float) $sale['due_amount']), 2);

        return [
            'customer' => [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'branch' => $customer->branch?->name,
                'status' => $customer->status->name,
                'registration_type' => $customer->registration_type->name,
                'coin_balance' => (float) $customer->point,
                'due_balance' => (float) $customer->balance,
                'is_default' => $customer->is_default ? 'Yes' : 'No',
            ],
            'sales' => $sales->map(function (Sell $sell) {
                $net = $sell->net_amount;
                $paid = (float) $sell->paid_amount;

                return [
                    'date' => $sell->date?->format('Y-m-d'),
                    'invoice_number' => $sell->invoice_number,
                    'gross_amount' => round((float) $sell->gross_amount, 2),
                    'discount' => round($sell->indexDiscountTotal(), 2),
                    'vat' => round((float) $sell->vat, 2),
                    'net_amount' => round($net, 2),
                    'paid_amount' => round($paid, 2),
                    'due_amount' => round(max(0, $net - $paid), 2),
                    'comment' => $sell->comment,
                ];
            })->all(),
            'collections' => $collections->map(fn (CustomerPayment $payment) => [
                'date' => $payment->date?->format('Y-m-d'),
                'invoice_number' => $payment->invoice_number,
                'amount' => round((float) $payment->amount, 2),
                'comment' => $payment->comment,
                'created_by' => $payment->createdBy?->name,
            ])->all(),
            'due_sales' => $dueSales->all(),
            'totals' => [
                'sales_net' => $salesNet,
                'sales_paid' => $salesPaid,
                'sales_due' => round(max(0, $salesNet - $salesPaid), 2),
                'collections' => $collectionsTotal,
                'current_due' => $currentDueTotal,
                'account_balance' => (float) $customer->balance,
            ],
        ];
    }

    private function branchId(): ?int
    {
        return Auth::user()?->branch_id;
    }
}
