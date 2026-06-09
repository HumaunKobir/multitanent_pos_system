<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ReceivedPaymentMethod;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleReturnController extends Controller
{
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(
        private InventoryStockService $stock,
        private InventoryAccountingService $accounting,
        private InventoryCostService $costService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.sale-return.view');

        $returns = SaleReturn::query()->ownBranch()
            ->with(['customer:id,name', 'sell:id'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/sale-return/index', [
            'returns' => $returns,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('inventory.sale-return.create');

        return Inertia::render('admin/inventory/sale-return/create', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.sale-return.create');

        $data = $request->validate([
            'sell_id' => ['required', 'exists:sells,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sell_product_id' => ['required', 'exists:sell_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentType = ReceivedPaymentMethod::from((int) $data['payment_type']);
        $paymentAccountId = $paymentType === ReceivedPaymentMethod::Cash
            ? $this->resolvePaymentAccountId($request, (float) $data['paid_amount'])
            : null;

        try {
            DB::transaction(function () use ($data, $branchId, $paymentAccountId, $paymentType) {
                $parent = Sell::query()
                    ->ownBranch()
                    ->sale()
                    ->with(['products'])
                    ->lockForUpdate()
                    ->findOrFail($data['sell_id']);

                if ($parent->hasAnyDiscount()) {
                    throw new \RuntimeException('Sales with a discount cannot be returned.');
                }

                $returnedByLine = $this->returnedQuantities($parent->id);
                $grossAmount = 0.0;
                $lines = [];

                foreach ($data['items'] as $item) {
                    $returnQty = (float) $item['quantity'];

                    if ($returnQty <= 0) {
                        continue;
                    }

                    $sellProduct = $parent->products
                        ->firstWhere('id', (int) $item['sell_product_id']);

                    if (! $sellProduct) {
                        throw new \RuntimeException('Invalid sale line.');
                    }

                    $maxReturn = (float) $sellProduct->quantity - ($returnedByLine[$sellProduct->id] ?? 0);

                    if ($returnQty > $maxReturn) {
                        throw new \RuntimeException('Return quantity exceeds available quantity.');
                    }

                    $batchMap = $this->scaleBatchMapForReturn($sellProduct->batches ?? [], $returnQty);

                    if ($batchMap !== []) {
                        $this->stock->restoreFromBatchMap(
                            $batchMap,
                            fn (Batch $batch, float $qty) => $batch->saleReturnStock($qty)
                        );
                    } elseif ($sellProduct->variation_id) {
                        $this->stock->restoreVariation((int) $sellProduct->variation_id, $returnQty);
                    } else {
                        throw new \RuntimeException('Unable to restore stock for a sale line.');
                    }

                    $grossAmount += $returnQty * (float) $sellProduct->unit_price;

                    $lines[] = [
                        'branch_id' => $branchId,
                        'sell_product_id' => $sellProduct->id,
                        'product_id' => $sellProduct->product_id,
                        'variation_id' => $sellProduct->variation_id,
                        'quantity' => $returnQty,
                        'unit_price' => $sellProduct->unit_price,
                        'batches' => $batchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with return quantity greater than zero is required.');
                }

                $paidAmount = min((float) $data['paid_amount'], $grossAmount);

                $saleReturn = SaleReturn::create([
                    'branch_id' => $branchId,
                    'sell_id' => $parent->id,
                    'customer_id' => $parent->customer_id,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'paid_amount' => $paidAmount,
                    'payment_type' => $paymentType,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $saleReturn->products()->create($line);
                }

                if ($parent->customer_id && $paymentType === ReceivedPaymentMethod::Customer_Account && $paidAmount > 0) {
                    Customer::whereKey($parent->customer_id)->increment('balance', $paidAmount);
                }

                $saleReturn->load('products');
                $this->accounting->postSaleReturn(
                    $saleReturn->fresh(['customer', 'sell']),
                    $paymentAccountId,
                    $this->costService->costForSaleReturn($saleReturn),
                );
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to create sale return.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.sale-return.index')
            ->with('success', 'Sale return created successfully.');
    }

    public function show(SaleReturn $saleReturn): Response
    {
        $this->authorize('inventory.sale-return.view');
        $this->authorizeBranch($saleReturn);

        $saleReturn->load([
            'customer',
            'sell',
            'products.product',
            'products.variation',
        ]);

        return Inertia::render('admin/inventory/sale-return/show', [
            'saleReturn' => $saleReturn,
        ]);
    }

    public function edit(SaleReturn $saleReturn): Response
    {
        $this->authorize('inventory.sale-return.update');
        $this->authorizeBranch($saleReturn);

        $saleReturn->load(['customer', 'sell', 'products.product']);

        $parent = Sell::query()
            ->ownBranch()
            ->sale()
            ->with(['products.product'])
            ->findOrFail($saleReturn->sell_id);

        if ($parent->hasAnyDiscount()) {
            abort(403, 'Sales with a discount cannot be returned.');
        }

        $returnedByLine = $this->returnedQuantities($parent->id, $saleReturn->id);
        $linesOnReturn = $saleReturn->products->keyBy('sell_product_id');

        $items = $parent->products
            ->map(function ($sp) use ($returnedByLine, $linesOnReturn) {
                $maxReturn = max(0, (float) $sp->quantity - ($returnedByLine[$sp->id] ?? 0));
                $current = $linesOnReturn->get($sp->id);

                if ($maxReturn <= 0 && ! $current) {
                    return null;
                }

                return [
                    'sell_product_id' => $sp->id,
                    'product_name' => $sp->product?->name,
                    'product_code' => $sp->product?->code,
                    'unit_price' => (float) $sp->unit_price,
                    'max_return_quantity' => (int) $maxReturn,
                    'quantity' => $current ? (string) (int) $current->quantity : '0',
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('admin/inventory/sale-return/edit', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccounts(),
            'saleReturn' => [
                'id' => $saleReturn->id,
                'sell_id' => $saleReturn->sell_id,
                'sale_invoice' => $saleReturn->sell?->invoice_number,
                'customer_name' => $saleReturn->customer?->name,
                'date' => optional($saleReturn->date)->format('Y-m-d'),
                'comment' => $saleReturn->comment,
                'paid_amount' => (string) $saleReturn->paid_amount,
                'payment_type' => $saleReturn->payment_type?->value,
                'items' => $items,
            ],
        ]);
    }

    public function update(Request $request, SaleReturn $saleReturn): RedirectResponse
    {
        $this->authorizeBranch($saleReturn);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sell_product_id' => ['required', 'exists:sell_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentType = ReceivedPaymentMethod::from((int) $data['payment_type']);
        $paymentAccountId = $paymentType === ReceivedPaymentMethod::Cash
            ? $this->resolvePaymentAccountId($request, (float) $data['paid_amount'])
            : null;

        try {
            DB::transaction(function () use ($saleReturn, $data, $branchId, $paymentAccountId, $paymentType) {
                $this->accounting->reverseFor($saleReturn);
                $saleReturn->load(['products']);

                $this->rollbackSaleReturn($saleReturn);
                $saleReturn->products()->delete();

                $parent = Sell::query()
                    ->ownBranch()
                    ->sale()
                    ->with(['products'])
                    ->lockForUpdate()
                    ->findOrFail($saleReturn->sell_id);

                if ($parent->hasAnyDiscount()) {
                    throw new \RuntimeException('Sales with a discount cannot be returned.');
                }

                $returnedByLine = $this->returnedQuantities($parent->id, $saleReturn->id);
                $grossAmount = 0.0;
                $lines = [];

                foreach ($data['items'] as $item) {
                    $returnQty = (float) $item['quantity'];

                    if ($returnQty <= 0) {
                        continue;
                    }

                    $sellProduct = $parent->products
                        ->firstWhere('id', (int) $item['sell_product_id']);

                    if (! $sellProduct) {
                        throw new \RuntimeException('Invalid sale line.');
                    }

                    $maxReturn = (float) $sellProduct->quantity - ($returnedByLine[$sellProduct->id] ?? 0);

                    if ($returnQty > $maxReturn) {
                        throw new \RuntimeException('Return quantity exceeds available quantity.');
                    }

                    $batchMap = $this->scaleBatchMapForReturn($sellProduct->batches ?? [], $returnQty);

                    if ($batchMap !== []) {
                        $this->stock->restoreFromBatchMap(
                            $batchMap,
                            fn (Batch $batch, float $qty) => $batch->saleReturnStock($qty)
                        );
                    } elseif ($sellProduct->variation_id) {
                        $this->stock->restoreVariation((int) $sellProduct->variation_id, $returnQty);
                    } else {
                        throw new \RuntimeException('Unable to restore stock for a sale line.');
                    }

                    $grossAmount += $returnQty * (float) $sellProduct->unit_price;

                    $lines[] = [
                        'branch_id' => $branchId,
                        'sell_product_id' => $sellProduct->id,
                        'product_id' => $sellProduct->product_id,
                        'variation_id' => $sellProduct->variation_id,
                        'quantity' => $returnQty,
                        'unit_price' => $sellProduct->unit_price,
                        'batches' => $batchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with return quantity greater than zero is required.');
                }

                $paidAmount = min((float) $data['paid_amount'], $grossAmount);

                $saleReturn->update([
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'paid_amount' => $paidAmount,
                    'payment_type' => $paymentType,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $saleReturn->products()->create($line);
                }

                if ($parent->customer_id && $paymentType === ReceivedPaymentMethod::Customer_Account && $paidAmount > 0) {
                    Customer::whereKey($parent->customer_id)->increment('balance', $paidAmount);
                }

                $saleReturn->load('products');
                $this->accounting->postSaleReturn(
                    $saleReturn->fresh(['customer', 'sell']),
                    $paymentAccountId,
                    $this->costService->costForSaleReturn($saleReturn),
                );
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update sale return.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.sale-return.index')
            ->with('success', 'Sale return updated successfully.');
    }

    public function destroy(SaleReturn $saleReturn): RedirectResponse
    {
        $this->authorizeBranch($saleReturn);

        $saleReturn->load(['products']);

        try {
            DB::transaction(function () use ($saleReturn) {
                $this->accounting->reverseFor($saleReturn);
                $this->rollbackSaleReturn($saleReturn);
                $saleReturn->products()->delete();
                $saleReturn->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Unable to delete sale return.');
        }

        return redirect()->route('inventory.sale-return.index')
            ->with('success', 'Sale return deleted successfully.');
    }

    private function authorizeBranch(SaleReturn $saleReturn): void
    {
        $branchId = Auth::user()?->branch_id;
        if ($branchId !== null && $saleReturn->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function rollbackSaleReturn(SaleReturn $saleReturn): void
    {
        foreach ($saleReturn->products as $line) {
            $qty = (float) $line->quantity;

            if ($line->variation_id) {
                $this->stock->deductVariation((int) $line->variation_id, $qty);
            } else {
                $this->stock->deductFromBatchMap(
                    $line->batches ?? [],
                    fn (Batch $batch, float $batchQty) => $batch->outStock($batchQty)
                );
            }
        }

        if ($saleReturn->customer_id
            && $saleReturn->payment_type === ReceivedPaymentMethod::Customer_Account
            && (float) $saleReturn->paid_amount > 0) {
            Customer::whereKey($saleReturn->customer_id)->decrement('balance', (float) $saleReturn->paid_amount);
        }
    }

    /** @return array<int, float> */
    private function returnedQuantities(int $sellId, ?int $excludeSaleReturnId = null): array
    {
        $quantities = [];

        $returns = SaleReturn::where('sell_id', $sellId)->with('products')->get();

        foreach ($returns as $return) {
            if ($excludeSaleReturnId !== null && $return->id === $excludeSaleReturnId) {
                continue;
            }

            foreach ($return->products as $line) {
                $quantities[$line->sell_product_id] = ($quantities[$line->sell_product_id] ?? 0) + (float) $line->quantity;
            }
        }

        return $quantities;
    }

    /**
     * @param  array<int|string, float>  $batches
     * @return array<int|string, float>
     */
    private function scaleBatchMapForReturn(array $batches, float $returnQty): array
    {
        if ($batches === []) {
            return [];
        }

        $remaining = $returnQty;
        $scaled = [];

        foreach ($batches as $batchId => $soldQty) {
            if ($remaining <= 0) {
                break;
            }

            $restore = min((float) $soldQty, $remaining);
            if ($restore > 0) {
                $scaled[$batchId] = $restore;
                $remaining -= $restore;
            }
        }

        return $scaled;
    }
}
