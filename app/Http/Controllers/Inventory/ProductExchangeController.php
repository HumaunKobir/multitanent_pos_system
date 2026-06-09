<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ReceivedPaymentMethod;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\ProductExchange;
use App\Models\Sell;
use App\Services\InventoryAccountingService;
use App\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductExchangeController extends Controller
{
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(
        private InventoryStockService $stock,
        private InventoryAccountingService $accounting,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.product-exchange.view');

        $exchanges = ProductExchange::query()->ownBranch()
            ->with(['customer:id,name', 'sell:id'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/product-exchange/index', [
            'exchanges' => $exchanges,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('inventory.product-exchange.create');

        return Inertia::render('admin/inventory/product-exchange/create', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.product-exchange.create');

        $data = $request->validate([
            'sell_id' => ['required', 'exists:sells,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sell_product_id' => ['required', 'exists:sell_products,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
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
                    throw new \RuntimeException('Sales with a discount cannot be exchanged.');
                }

                if (ProductExchange::where('sell_id', $parent->id)->exists()) {
                    throw new \RuntimeException('This sale has already been exchanged.');
                }

                $grossAmount = 0.0;
                $priceDifference = 0.0;
                $lines = [];

                foreach ($data['items'] as $item) {
                    $qty = (int) $item['quantity'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $sellProduct = $parent->products
                        ->firstWhere('id', (int) $item['sell_product_id']);

                    if (! $sellProduct) {
                        throw new \RuntimeException('Invalid sale line.');
                    }

                    if ($qty > (float) $sellProduct->quantity) {
                        throw new \RuntimeException('Exchange quantity exceeds sold quantity.');
                    }

                    $oldBatchMap = $this->scaleBatchMapForQty($sellProduct->batches ?? [], $qty);

                    if ($oldBatchMap !== []) {
                        $this->stock->restoreFromBatchMap(
                            $oldBatchMap,
                            fn (Batch $batch, float $batchQty) => $batch->saleReturnStock($batchQty)
                        );
                    } elseif ($sellProduct->variation_id) {
                        $this->stock->restoreVariation((int) $sellProduct->variation_id, $qty);
                    }

                    $newProductId = (int) $item['product_id'];
                    $newVariationId = $item['variation_id'] ? (int) $item['variation_id'] : null;
                    $newUnitPrice = (float) $item['unit_price'];
                    $newBatchMap = [];

                    if ($newVariationId) {
                        $this->stock->deductVariation($newVariationId, $qty);
                    } else {
                        $newBatchMap = $this->stock->deductFifo(
                            $branchId,
                            $newProductId,
                            $qty,
                            fn (Batch $batch, float $deductQty) => $batch->exchangeStock($deductQty)
                        );
                    }

                    $oldTotal = $qty * (float) $sellProduct->unit_price;
                    $newTotal = $qty * $newUnitPrice;
                    $grossAmount += $newTotal;
                    $priceDifference += $newTotal - $oldTotal;

                    $lines[] = [
                        'branch_id' => $branchId,
                        'sell_product_id' => $sellProduct->id,
                        'old_product_id' => $sellProduct->product_id,
                        'old_variation_id' => $sellProduct->variation_id,
                        'old_quantity' => $qty,
                        'old_unit_price' => $sellProduct->unit_price,
                        'old_batches' => $oldBatchMap,
                        'new_product_id' => $newProductId,
                        'new_variation_id' => $newVariationId,
                        'new_quantity' => $qty,
                        'new_unit_price' => $newUnitPrice,
                        'new_batches' => $newBatchMap,
                    ];
                }

                $paymentType = ReceivedPaymentMethod::from((int) $data['payment_type']);

                $exchange = ProductExchange::create([
                    'branch_id' => $branchId,
                    'sell_id' => $parent->id,
                    'customer_id' => $parent->customer_id,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'paid_amount' => (float) $data['paid_amount'],
                    'price_difference' => $priceDifference,
                    'payment_type' => $paymentType,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $exchange->products()->create($line);
                }

                if ($parent->customer_id && $paymentType === ReceivedPaymentMethod::Customer_Account) {
                    if ($priceDifference > 0) {
                        Customer::whereKey($parent->customer_id)->decrement('balance', $priceDifference);
                    } elseif ($priceDifference < 0) {
                        Customer::whereKey($parent->customer_id)->increment('balance', abs($priceDifference));
                    }
                }

                $this->accounting->postExchange($exchange->fresh(['customer', 'sell', 'products']), $paymentAccountId);
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to create product exchange.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.product-exchange.index')
            ->with('success', 'Product exchange created successfully.');
    }

    public function show(ProductExchange $productExchange): Response
    {
        $this->authorize('inventory.product-exchange.view');
        $this->authorizeBranch($productExchange);

        $productExchange->load([
            'customer',
            'sell.products.product',
            'products.oldProduct',
            'products.newProduct',
            'products.oldVariation',
            'products.newVariation',
        ]);

        return Inertia::render('admin/inventory/product-exchange/show', [
            'exchange' => $productExchange,
        ]);
    }

    public function edit(ProductExchange $productExchange): Response
    {
        $this->authorize('inventory.product-exchange.update');
        $this->authorizeBranch($productExchange);

        $productExchange->load([
            'customer',
            'sell',
            'products.oldProduct',
            'products.newProduct',
            'products.newVariation',
        ]);

        $parent = $productExchange->sell;

        if (! $parent || $parent->hasAnyDiscount()) {
            abort(403, 'This exchange cannot be edited.');
        }

        $items = $productExchange->products->map(fn ($line) => [
            'sell_product_id' => $line->sell_product_id,
            'old_product_name' => $line->oldProduct?->name,
            'old_product_code' => $line->oldProduct?->code,
            'old_unit_price' => (float) $line->old_unit_price,
            'sold_quantity' => (int) $line->old_quantity,
            'quantity' => (string) (int) $line->new_quantity,
            'new_product_id' => $line->new_product_id,
            'new_product_name' => $line->newProduct?->name,
            'new_product_code' => $line->newProduct?->code,
            'new_variation_id' => $line->new_variation_id,
            'new_variation_label' => $line->newVariation?->variation_data['label'] ?? null,
            'new_unit_price' => (string) $line->new_unit_price,
        ])->values();

        return Inertia::render('admin/inventory/product-exchange/edit', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccounts(),
            'exchange' => [
                'id' => $productExchange->id,
                'sell_id' => $productExchange->sell_id,
                'sale_invoice' => $parent->invoice_number,
                'customer_name' => $productExchange->customer?->name,
                'date' => optional($productExchange->date)->format('Y-m-d'),
                'comment' => $productExchange->comment,
                'paid_amount' => (string) $productExchange->paid_amount,
                'payment_type' => $productExchange->payment_type?->value,
                'items' => $items,
            ],
        ]);
    }

    public function update(Request $request, ProductExchange $productExchange): RedirectResponse
    {
        $this->authorize('inventory.product-exchange.update');
        $this->authorizeBranch($productExchange);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sell_product_id' => ['required', 'exists:sell_products,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentType = ReceivedPaymentMethod::from((int) $data['payment_type']);
        $paymentAccountId = $paymentType === ReceivedPaymentMethod::Cash
            ? $this->resolvePaymentAccountId($request, (float) $data['paid_amount'])
            : null;

        try {
            DB::transaction(function () use ($productExchange, $data, $branchId, $paymentAccountId, $paymentType) {
                $this->accounting->reverseFor($productExchange);
                $productExchange->load(['products', 'sell']);

                $this->rollbackProductExchange($productExchange);
                $productExchange->products()->delete();

                $parent = Sell::query()
                    ->ownBranch()
                    ->sale()
                    ->with(['products'])
                    ->lockForUpdate()
                    ->findOrFail($productExchange->sell_id);

                if ($parent->hasAnyDiscount()) {
                    throw new \RuntimeException('Sales with a discount cannot be exchanged.');
                }

                $grossAmount = 0.0;
                $priceDifference = 0.0;
                $lines = [];

                foreach ($data['items'] as $item) {
                    $qty = (int) $item['quantity'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $sellProduct = $parent->products
                        ->firstWhere('id', (int) $item['sell_product_id']);

                    if (! $sellProduct) {
                        throw new \RuntimeException('Invalid sale line.');
                    }

                    if ($qty > (float) $sellProduct->quantity) {
                        throw new \RuntimeException('Exchange quantity exceeds sold quantity.');
                    }

                    $oldBatchMap = $this->scaleBatchMapForQty($sellProduct->batches ?? [], $qty);

                    if ($oldBatchMap !== []) {
                        $this->stock->restoreFromBatchMap(
                            $oldBatchMap,
                            fn (Batch $batch, float $batchQty) => $batch->saleReturnStock($batchQty)
                        );
                    } elseif ($sellProduct->variation_id) {
                        $this->stock->restoreVariation((int) $sellProduct->variation_id, $qty);
                    }

                    $newProductId = (int) $item['product_id'];
                    $newVariationId = $item['variation_id'] ? (int) $item['variation_id'] : null;
                    $newUnitPrice = (float) $item['unit_price'];
                    $newBatchMap = [];

                    if ($newVariationId) {
                        $this->stock->deductVariation($newVariationId, $qty);
                    } else {
                        $newBatchMap = $this->stock->deductFifo(
                            $branchId,
                            $newProductId,
                            $qty,
                            fn (Batch $batch, float $deductQty) => $batch->exchangeStock($deductQty)
                        );
                    }

                    $oldTotal = $qty * (float) $sellProduct->unit_price;
                    $newTotal = $qty * $newUnitPrice;
                    $grossAmount += $newTotal;
                    $priceDifference += $newTotal - $oldTotal;

                    $lines[] = [
                        'branch_id' => $branchId,
                        'sell_product_id' => $sellProduct->id,
                        'old_product_id' => $sellProduct->product_id,
                        'old_variation_id' => $sellProduct->variation_id,
                        'old_quantity' => $qty,
                        'old_unit_price' => $sellProduct->unit_price,
                        'old_batches' => $oldBatchMap,
                        'new_product_id' => $newProductId,
                        'new_variation_id' => $newVariationId,
                        'new_quantity' => $qty,
                        'new_unit_price' => $newUnitPrice,
                        'new_batches' => $newBatchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with exchange quantity greater than zero is required.');
                }

                $paymentType = ReceivedPaymentMethod::from((int) $data['payment_type']);

                $productExchange->update([
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'paid_amount' => (float) $data['paid_amount'],
                    'price_difference' => $priceDifference,
                    'payment_type' => $paymentType,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $productExchange->products()->create($line);
                }

                if ($parent->customer_id && $paymentType === ReceivedPaymentMethod::Customer_Account) {
                    if ($priceDifference > 0) {
                        Customer::whereKey($parent->customer_id)->decrement('balance', $priceDifference);
                    } elseif ($priceDifference < 0) {
                        Customer::whereKey($parent->customer_id)->increment('balance', abs($priceDifference));
                    }
                }

                $this->accounting->postExchange($productExchange->fresh(['customer', 'sell', 'products']), $paymentAccountId);
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update product exchange.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.product-exchange.index')
            ->with('success', 'Product exchange updated successfully.');
    }

    public function destroy(ProductExchange $productExchange): RedirectResponse
    {
        $this->authorize('inventory.product-exchange.delete');
        $this->authorizeBranch($productExchange);

        $productExchange->load(['products', 'sell']);

        try {
            DB::transaction(function () use ($productExchange) {
                $this->accounting->reverseFor($productExchange);
                $this->rollbackProductExchange($productExchange);
                $productExchange->products()->delete();
                $productExchange->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Unable to delete product exchange.');
        }

        return redirect()->route('inventory.product-exchange.index')
            ->with('success', 'Product exchange deleted successfully.');
    }

    private function rollbackProductExchange(ProductExchange $productExchange): void
    {
        $parent = $productExchange->sell;

        if (! $parent) {
            throw new \RuntimeException('Original sale not found.');
        }

        $parent->load('products');

        foreach ($productExchange->products as $line) {
            $qty = (float) $line->new_quantity;

            if ($line->new_variation_id) {
                $this->stock->restoreVariation((int) $line->new_variation_id, $qty);
            } else {
                $this->stock->restoreFromBatchMap(
                    $line->new_batches ?? [],
                    fn (Batch $batch, float $batchQty) => $batch->saleReturnStock($batchQty)
                );
            }
        }

        foreach ($parent->products as $oldLine) {
            $qty = (float) $oldLine->quantity;
            $batchMap = $oldLine->batches ?? [];

            if ($batchMap !== []) {
                $this->stock->deductFromBatchMap(
                    $batchMap,
                    fn (Batch $batch, float $batchQty) => $batch->outStock($batchQty)
                );
            } elseif ($oldLine->variation_id) {
                $this->stock->deductVariation((int) $oldLine->variation_id, $qty);
            }
        }

        $priceDifference = (float) $productExchange->price_difference;

        if ($productExchange->customer_id
            && $productExchange->payment_type === ReceivedPaymentMethod::Customer_Account) {
            if ($priceDifference > 0) {
                Customer::whereKey($productExchange->customer_id)->increment('balance', $priceDifference);
            } elseif ($priceDifference < 0) {
                Customer::whereKey($productExchange->customer_id)->decrement('balance', abs($priceDifference));
            }
        }
    }

    private function authorizeBranch(ProductExchange $productExchange): void
    {
        $branchId = Auth::user()?->branch_id;
        if ($branchId !== null && $productExchange->branch_id !== $branchId) {
            abort(404);
        }
    }

    /**
     * @param  array<int|string, float>  $batches
     * @return array<int|string, float>
     */
    private function scaleBatchMapForQty(array $batches, float $qty): array
    {
        if ($batches === []) {
            return [];
        }

        $remaining = $qty;
        $scaled = [];

        foreach ($batches as $batchId => $soldQty) {
            if ($remaining <= 0) {
                break;
            }

            $portion = min((float) $soldQty, $remaining);
            if ($portion > 0) {
                $scaled[$batchId] = $portion;
                $remaining -= $portion;
            }
        }

        return $scaled;
    }
}
