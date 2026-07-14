<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\PurchaseReceivedPayment;
use App\Http\Controllers\Concerns\AuthorizesBranchUserRecords;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnPayment;
use App\Models\StockDistribution;
use App\Models\Supplier;
use App\Services\InventoryAccountingService;
use App\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseReturnController extends Controller
{
    use AuthorizesBranchUserRecords;
    use ProvidesPaymentAccounts;

    public function __construct(
        private InventoryStockService $stock,
        private InventoryAccountingService $accounting,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.purchase-return.view');

        $returns = PurchaseReturn::query()->ownBranchUser()
            ->with(['supplier:id,name,company_name', 'purchase:id'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('invoice_sequence', 'like', "%{$s}%")
                    ->orWhere('serial', 'like', "%{$s}%")
                    ->orWhere('id', 'like', "%{$s}%")
                    ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$s}%")
                        ->orWhere('company_name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/purchase-return/index', [
            'returns' => $returns,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('inventory.purchase-return.create');

        $branchId = Auth::user()?->branch_id;

        return Inertia::render('admin/inventory/purchase-return/create', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccountsForBranch($branchId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.purchase-return.create');

        $data = $request->validate([
            'purchase_id' => ['required', 'exists:purchases,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_product_id' => ['required', 'exists:purchase_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($data, $branchId) {
                $parent = Purchase::query()
                    ->ownBranchUser()
                    ->purchase()
                    ->with(['purchaseProducts', 'supplier'])
                    ->lockForUpdate()
                    ->findOrFail($data['purchase_id']);

                $this->assertNotHeldByBranch($parent->id);

                $returnedQtyByLine = $this->returnedQuantities($parent->id);
                $grossAmount = 0.0;
                $lines = [];

                foreach ($data['items'] as $item) {
                    $returnQty = (float) $item['quantity'];

                    if ($returnQty <= 0) {
                        continue;
                    }

                    $purchaseProduct = $parent->purchaseProducts
                        ->firstWhere('id', (int) $item['purchase_product_id']);

                    if (! $purchaseProduct) {
                        throw new \RuntimeException('Invalid purchase line.');
                    }

                    $maxReturn = (float) $purchaseProduct->quantity
                        - ($returnedQtyByLine[$purchaseProduct->id] ?? 0);

                    if ($returnQty > $maxReturn) {
                        throw new \RuntimeException('Return quantity exceeds available quantity.');
                    }

                    $batchMap = $this->buildReturnBatchMap($purchaseProduct, $returnQty, $branchId);

                    if ($purchaseProduct->variation_id) {
                        $this->stock->deductVariation((int) $purchaseProduct->variation_id, $returnQty);
                    }

                    $grossAmount += $returnQty * (float) $purchaseProduct->unit_price;

                    $lines[] = [
                        'branch_id' => $branchId,
                        'purchase_product_id' => $purchaseProduct->id,
                        'product_id' => $purchaseProduct->product_id,
                        'variation_id' => $purchaseProduct->variation_id,
                        'quantity' => $returnQty,
                        'unit_price' => $purchaseProduct->unit_price,
                        'batches' => $batchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with return quantity greater than zero is required.');
                }

                $adjustments = $this->purchaseReturnAdjustments(
                    $parent,
                    $grossAmount,
                    array_key_exists('discount', $data) ? (float) $data['discount'] : null,
                );
                $payment = $this->resolvePurchaseReturnPayment($data, $adjustments['net']);

                [$payment, $purchaseDueOffset] = $this->applyPurchaseDueOffset($parent, $payment);

                $purchaseReturn = PurchaseReturn::create([
                    'branch_id' => $branchId,
                    'user_id' => $this->currentUserId(),
                    'purchase_id' => $parent->id,
                    'supplier_id' => $parent->supplier_id,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $adjustments['discount'],
                    'vat' => $adjustments['vat'],
                    'paid_amount' => $payment['paid_amount'],
                    'due_amount' => $payment['due_amount'],
                    'purchase_due_offset' => $purchaseDueOffset,
                    'payment_type' => $payment['payment_type'],
                    'payment_account_id' => $payment['payment_account_id'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $purchaseReturn->products()->create($line);
                }

                // Offset portion already reduced supplier balance inside applyPurchaseDueOffset.
                // Return due is tracked in AccountsReceivable (not supplier balance) until received.
                $this->accounting->postPurchaseReturn($purchaseReturn->fresh(['supplier']), $payment['payment_account_id']);
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to create purchase return.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.purchase-return.index')
            ->with('success', 'Purchase return created successfully.');
    }

    public function show(PurchaseReturn $purchaseReturn): Response
    {
        $this->authorize('inventory.purchase-return.view');
        $this->authorizeBranchUserRecord($purchaseReturn);

        $purchaseReturn->load([
            'supplier',
            'purchase',
            'products.product',
            'products.variation',
            'payments',
        ]);

        return Inertia::render('admin/inventory/purchase-return/show', [
            'purchaseReturn' => $purchaseReturn,
            'paymentAccounts' => $this->paymentAccountsForBranch($purchaseReturn->branch_id),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function edit(PurchaseReturn $purchaseReturn): Response
    {
        $this->authorize('inventory.purchase-return.update');
        $this->authorizeBranchUserRecord($purchaseReturn);

        $purchaseReturn->load(['supplier', 'purchase', 'products.product']);

        $parent = Purchase::query()
            ->ownBranchUser()
            ->purchase()
            ->with(['purchaseProducts.product'])
            ->findOrFail($purchaseReturn->purchase_id);

        $returnedByLine = $this->returnedQuantities($parent->id, $purchaseReturn->id);
        $linesOnReturn = $purchaseReturn->products->keyBy('purchase_product_id');

        $items = $parent->purchaseProducts
            ->map(function ($pp) use ($returnedByLine, $linesOnReturn) {
                $maxReturn = max(0, (float) $pp->quantity - ($returnedByLine[$pp->id] ?? 0));
                $current = $linesOnReturn->get($pp->id);

                if ($maxReturn <= 0 && ! $current) {
                    return null;
                }

                return [
                    'purchase_product_id' => $pp->id,
                    'product_name' => $pp->product?->name,
                    'product_code' => $pp->product?->code,
                    'unit_price' => (float) $pp->unit_price,
                    'max_return_quantity' => (int) $maxReturn,
                    'quantity' => $current ? (string) (int) $current->quantity : '0',
                ];
            })
            ->filter()
            ->values();

        $parentTaxable = max(0, (float) $parent->gross_amount - (float) $parent->discount);
        $parentVatPercent = $parentTaxable > 0
            ? ((float) $parent->vat / $parentTaxable) * 100
            : 0;

        return Inertia::render('admin/inventory/purchase-return/edit', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccountsForBranch($purchaseReturn->branch_id),
            'purchaseReturn' => [
                'id' => $purchaseReturn->id,
                'invoice_number' => $purchaseReturn->invoice_number,
                'purchase_id' => $purchaseReturn->purchase_id,
                'purchase_invoice' => $purchaseReturn->purchase?->invoice_number,
                'supplier_name' => $this->supplierDisplayName($purchaseReturn->supplier),
                'date' => optional($purchaseReturn->date)->format('Y-m-d'),
                'comment' => $purchaseReturn->comment,
                'paid_amount' => (string) $purchaseReturn->paid_amount,
                'payment_type' => $purchaseReturn->payment_type?->value,
                'payment_account_id' => $purchaseReturn->payment_account_id,
                'gross_amount' => (float) $purchaseReturn->gross_amount,
                'discount' => (float) $purchaseReturn->discount,
                'vat' => (float) $purchaseReturn->vat,
                'purchase_gross_amount' => (float) $parent->gross_amount,
                'purchase_discount' => (float) $parent->discount,
                'purchase_vat' => (float) $parent->vat,
                'purchase_vat_percent' => $parentVatPercent,
                // Restore the offset so the edit form reflects what purchase due will be
                // available after rollback (rollback adds purchase_due_offset back to purchase.due_amount).
                'purchase_paid_amount' => (float) $parent->paid_amount,
                'purchase_due_amount' => (float) $parent->due_amount + (float) $purchaseReturn->purchase_due_offset,
                'items' => $items,
            ],
        ]);
    }

    public function update(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('inventory.purchase-return.update');
        $this->authorizeBranchUserRecord($purchaseReturn);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_product_id' => ['required', 'exists:purchase_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($purchaseReturn, $data, $branchId) {
                $purchaseReturn->load(['products', 'purchase.supplier']);

                $this->accounting->reverseFor($purchaseReturn);
                $this->rollbackPurchaseReturn($purchaseReturn);
                $purchaseReturn->products()->delete();

                $parent = Purchase::query()
                    ->ownBranchUser()
                    ->purchase()
                    ->with(['purchaseProducts', 'supplier'])
                    ->lockForUpdate()
                    ->findOrFail($purchaseReturn->purchase_id);

                $this->assertNotHeldByBranch($parent->id);

                $returnedQtyByLine = $this->returnedQuantities($parent->id, $purchaseReturn->id);
                $grossAmount = 0.0;
                $lines = [];

                foreach ($data['items'] as $item) {
                    $returnQty = (float) $item['quantity'];

                    if ($returnQty <= 0) {
                        continue;
                    }

                    $purchaseProduct = $parent->purchaseProducts
                        ->firstWhere('id', (int) $item['purchase_product_id']);

                    if (! $purchaseProduct) {
                        throw new \RuntimeException('Invalid purchase line.');
                    }

                    $maxReturn = (float) $purchaseProduct->quantity
                        - ($returnedQtyByLine[$purchaseProduct->id] ?? 0);

                    if ($returnQty > $maxReturn) {
                        throw new \RuntimeException('Return quantity exceeds available quantity.');
                    }

                    $batchMap = $this->buildReturnBatchMap($purchaseProduct, $returnQty, $branchId);

                    if ($purchaseProduct->variation_id) {
                        $this->stock->deductVariation((int) $purchaseProduct->variation_id, $returnQty);
                    }

                    $grossAmount += $returnQty * (float) $purchaseProduct->unit_price;

                    $lines[] = [
                        'branch_id' => $branchId,
                        'purchase_product_id' => $purchaseProduct->id,
                        'product_id' => $purchaseProduct->product_id,
                        'variation_id' => $purchaseProduct->variation_id,
                        'quantity' => $returnQty,
                        'unit_price' => $purchaseProduct->unit_price,
                        'batches' => $batchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with return quantity greater than zero is required.');
                }

                $adjustments = $this->purchaseReturnAdjustments(
                    $parent,
                    $grossAmount,
                    array_key_exists('discount', $data) ? (float) $data['discount'] : null,
                );
                $payment = $this->resolvePurchaseReturnPayment($data, $adjustments['net']);

                [$payment, $purchaseDueOffset] = $this->applyPurchaseDueOffset($parent, $payment);

                $purchaseReturn->update([
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $adjustments['discount'],
                    'vat' => $adjustments['vat'],
                    'paid_amount' => $payment['paid_amount'],
                    'due_amount' => $payment['due_amount'],
                    'purchase_due_offset' => $purchaseDueOffset,
                    'payment_type' => $payment['payment_type'],
                    'payment_account_id' => $payment['payment_account_id'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $purchaseReturn->products()->create($line);
                }

                // Offset portion already reduced supplier balance inside applyPurchaseDueOffset.
                // Return due is tracked in AccountsReceivable (not supplier balance) until received.
                $this->accounting->postPurchaseReturn($purchaseReturn->fresh(['supplier']), $payment['payment_account_id']);
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update purchase return.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.purchase-return.index')
            ->with('success', 'Purchase return updated successfully.');
    }

    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('inventory.purchase-return.delete');
        $this->authorizeBranchUserRecord($purchaseReturn);

        $purchaseReturn->load(['products', 'payments']);

        try {
            DB::transaction(function () use ($purchaseReturn) {
                // Reverse each received payment before reversing the return itself.
                foreach ($purchaseReturn->payments as $payment) {
                    $this->accounting->reverseFor($payment);
                    $payment->delete();
                }

                $this->accounting->reverseFor($purchaseReturn);
                $this->rollbackPurchaseReturn($purchaseReturn);
                $purchaseReturn->products()->delete();
                $purchaseReturn->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to delete purchase return.');
        }

        return redirect()->route('inventory.purchase-return.index')
            ->with('success', 'Purchase return deleted successfully.');
    }

    public function receivePayment(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('inventory.purchase-return.update');
        $this->authorizeBranchUserRecord($purchaseReturn);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $amount = round((float) $data['amount'], 2);
        $currentDue = round((float) $purchaseReturn->due_amount, 2);

        if ($amount > $currentDue + 0.009) {
            return back()->withErrors(['amount' => "Amount cannot exceed the remaining due of ৳{$currentDue}."])->withInput();
        }

        try {
            DB::transaction(function () use ($purchaseReturn, $data, $amount) {
                $serial = 'INVRP'.str_pad((string) (PurchaseReturnPayment::max('id') + 1), 8, '0', STR_PAD_LEFT);

                $payment = PurchaseReturnPayment::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'supplier_id' => $purchaseReturn->supplier_id,
                    'branch_id' => $purchaseReturn->branch_id,
                    'date' => $data['date'],
                    'amount' => $amount,
                    'payment_account_id' => (int) $data['payment_account_id'],
                    'serial' => $serial,
                ]);

                $purchaseReturn->decrement('due_amount', $amount);
                $purchaseReturn->increment('received_amount', $amount);

                $this->accounting->postPurchaseReturnPayment($payment->load(['purchaseReturn', 'supplier']));
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to record payment: '.$e->getMessage());
        }

        return back()->with('success', 'Payment received successfully.');
    }

    /**
     * Prevent returning a purchase whose distributed stock is still held at a branch.
     * The branch must first return the stock to the main warehouse.
     */
    private function assertNotHeldByBranch(int $purchaseId): void
    {
        $branches = StockDistribution::branchesHoldingReceivedStockForPurchase($purchaseId);

        if ($branches !== []) {
            throw new \RuntimeException(
                'This purchase cannot be returned yet. Its stock was received by '
                .implode(', ', $branches)
                .'. That branch must return the stock to the main warehouse first.'
            );
        }
    }

    private function supplierDisplayName(?Supplier $supplier): ?string
    {
        if ($supplier === null) {
            return null;
        }

        $company = trim((string) $supplier->company_name);
        $person = trim((string) $supplier->name);

        if ($company !== '' && $person !== '') {
            return "{$company} ({$person})";
        }

        return $company !== '' ? $company : ($person !== '' ? $person : null);
    }

    private function rollbackPurchaseReturn(PurchaseReturn $purchaseReturn): void
    {
        foreach ($purchaseReturn->products as $line) {
            $qty = (float) $line->quantity;

            foreach ($line->batches ?? [] as $batchId => $batchQty) {
                $batch = Batch::whereKey($batchId)->lockForUpdate()->first();
                if ($batch) {
                    $batch->increment('available', (float) $batchQty);
                    $batch->refresh();
                    $batch->inStock((float) $batchQty);
                }
            }

            if ($line->variation_id) {
                $this->stock->restoreVariation((int) $line->variation_id, $qty);
            }
        }

        // Restore any amount that was offset against the original purchase's due.
        $purchaseDueOffset = (float) $purchaseReturn->purchase_due_offset;

        if ($purchaseDueOffset > 0) {
            Purchase::whereKey($purchaseReturn->purchase_id)->increment('due_amount', $purchaseDueOffset);
            if ($purchaseReturn->supplier_id) {
                Supplier::whereKey($purchaseReturn->supplier_id)->increment('balance', $purchaseDueOffset);
            }
        }

        // Return due was tracked in AccountsReceivable (not supplier balance), so no supplier
        // balance change is needed here. The AccountsReceivable entry is reversed via reverseFor().
    }

    /**
     * When the original purchase still has unpaid due, offset the return amount against
     * that due rather than creating a new due on the purchase return. The offset amount
     * is debited from the supplier's running balance and deducted from purchase.due_amount.
     *
     * Returns the adjusted payment array and the offset amount applied.
     *
     * @param  array{payment_type: PurchaseReceivedPayment, payment_account_id: ?int, paid_amount: float, due_amount: float}  $payment
     * @return array{0: array{payment_type: PurchaseReceivedPayment, payment_account_id: ?int, paid_amount: float, due_amount: float}, 1: float}
     */
    private function applyPurchaseDueOffset(Purchase $parent, array $payment): array
    {
        $purchaseDue = round((float) $parent->due_amount, 2);

        if ($purchaseDue <= 0) {
            return [$payment, 0.0];
        }

        $offset = round(min($purchaseDue, $payment['due_amount']), 2);

        if ($offset <= 0) {
            return [$payment, 0.0];
        }

        // Reduce the original purchase's outstanding due.
        Purchase::whereKey($parent->id)->decrement('due_amount', $offset);

        // Settle the offset portion against the supplier's running account balance.
        if ($parent->supplier_id) {
            Supplier::whereKey($parent->supplier_id)->decrement('balance', $offset);
        }

        $payment['due_amount'] = round(max(0, $payment['due_amount'] - $offset), 2);

        return [$payment, $offset];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   payment_type: PurchaseReceivedPayment,
     *   payment_account_id: ?int,
     *   paid_amount: float,
     *   due_amount: float
     * }
     */
    private function resolvePurchaseReturnPayment(array $data, float $netAmount): array
    {
        $paymentType = PurchaseReceivedPayment::from((int) $data['payment_type']);
        $netAmount = round(max(0, $netAmount), 2);

        if ($paymentType === PurchaseReceivedPayment::Supplier_Account) {
            return [
                'payment_type' => $paymentType,
                'payment_account_id' => null,
                'paid_amount' => 0.0,
                'due_amount' => $netAmount,
            ];
        }

        $paidAmount = round(min(max(0, (float) $data['paid_amount']), $netAmount), 2);
        $paymentAccountId = isset($data['payment_account_id']) ? (int) $data['payment_account_id'] : null;

        if ($paidAmount > 0 && $paymentAccountId === null) {
            throw new \RuntimeException('Select a cash or bank account for the refund amount.');
        }

        return [
            'payment_type' => $paymentType,
            'payment_account_id' => $paymentAccountId,
            'paid_amount' => $paidAmount,
            'due_amount' => round(max(0, $netAmount - $paidAmount), 2),
        ];
    }

    /**
     * @return array{discount: float, vat: float, net: float, vat_percent: float}
     */
    private function purchaseReturnAdjustments(Purchase $parent, float $grossAmount, ?float $discount = null): array
    {
        $parentGross = (float) $parent->gross_amount;
        $parentDiscount = (float) $parent->discount;

        // Null = default to the return's proportional share of the parent purchase discount.
        // A submitted value is treated as the return-level discount (already scaled / edited).
        if ($discount === null) {
            $discount = $parentGross > 0
                ? round(max(0, $parentDiscount) * $grossAmount / $parentGross, 2)
                : 0.0;
        } else {
            $discount = round(max(0, $discount), 2);
        }

        $discount = min($discount, max(0, $grossAmount));

        $parentTaxable = max(0, $parentGross - $parentDiscount);
        $vatPercent = $parentTaxable > 0 ? ((float) $parent->vat / $parentTaxable) * 100 : 0;
        $taxableAmount = max(0, $grossAmount - $discount);
        $vat = round($taxableAmount * $vatPercent / 100, 2);

        return [
            'discount' => $discount,
            'vat' => $vat,
            'net' => $taxableAmount + $vat,
            'vat_percent' => $vatPercent,
        ];
    }

    /** @return array<int, float> */
    private function returnedQuantities(int $purchaseId, ?int $excludePurchaseReturnId = null): array
    {
        $returnedQtyByLine = [];

        $returns = PurchaseReturn::where('purchase_id', $purchaseId)->with('products')->get();

        foreach ($returns as $return) {
            if ($excludePurchaseReturnId !== null && $return->id === $excludePurchaseReturnId) {
                continue;
            }

            foreach ($return->products as $line) {
                if (! $line->purchase_product_id) {
                    continue;
                }

                $returnedQtyByLine[$line->purchase_product_id] = ($returnedQtyByLine[$line->purchase_product_id] ?? 0)
                    + (float) $line->quantity;
            }
        }

        return $returnedQtyByLine;
    }

    /**
     * @return array<int|string, float>
     */
    private function buildReturnBatchMap(PurchaseProduct $line, float $returnQty, ?int $branchId): array
    {
        $batches = $line->batches ?? [];

        if ($batches === []) {
            return $this->stock->deductFifo(
                $branchId,
                (int) $line->product_id,
                $returnQty,
                fn (Batch $batch, float $qty) => $batch->purchaseReturnStock($qty)
            );
        }

        $remaining = $returnQty;
        $batchMap = [];

        foreach ($batches as $batchId => $availableOnLine) {
            if ($remaining <= 0) {
                break;
            }

            $batch = Batch::whereKey($batchId)->lockForUpdate()->first();
            if (! $batch) {
                continue;
            }

            $deduct = min((float) $availableOnLine, (float) $batch->available, $remaining);
            if ($deduct <= 0) {
                continue;
            }

            $batchMap[$batchId] = $deduct;
            $remaining -= $deduct;
        }

        if ($batchMap !== []) {
            $this->stock->deductFromBatchMap(
                $batchMap,
                fn (Batch $batch, float $qty) => $batch->purchaseReturnStock($qty)
            );
        }

        if ($remaining > 0) {
            $extra = $this->stock->deductFifo(
                $branchId,
                (int) $line->product_id,
                $remaining,
                fn (Batch $batch, float $qty) => $batch->purchaseReturnStock($qty)
            );

            foreach ($extra as $batchId => $qty) {
                $batchMap[$batchId] = ($batchMap[$batchId] ?? 0) + $qty;
            }
        }

        return $batchMap;
    }
}
