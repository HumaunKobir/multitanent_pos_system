<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\PurchaseType;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\InventoryAccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(private InventoryAccountingService $accounting) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.purchase.view');

        $purchases = Purchase::query()->ownBranch()
            ->purchase()
            ->with('supplier:id,name,phone')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('serial', 'like', "%{$s}%")
                    ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/purchase/index', [
            'purchases' => $purchases,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('inventory.purchase.create');

        return Inertia::render('admin/inventory/purchase/create', [
            'suppliers' => Supplier::query()->ownBranch()->orderBy('name', 'asc')->get(['id', 'name', 'company_name', 'phone']),
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.purchase.create');

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'discount' => ['required', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.free_quantity' => ['required', 'integer', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.serial' => ['nullable', 'string'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentAccountId = $this->resolvePaymentAccountId($request, (float) $data['paid_amount']);

        DB::transaction(function () use ($data, $branchId, $paymentAccountId) {
            $grossAmount = 0;
            $purchaseProductsData = [];
            $productsById = Product::query()
                ->whereIn('id', collect($data['items'])->pluck('product_id'))
                ->get(['id', 'branch_id'])
                ->keyBy('id');

            foreach ($data['items'] as $item) {
                $qty = (float) $item['quantity'];
                $freeQty = (float) $item['free_quantity'];
                $unitPrice = (float) $item['unit_price'];
                $expiryDate = $item['expiry_date'] ?? null;
                $serial = $item['serial'] ?? null;
                $productId = $item['product_id'];
                $variationId = $item['variation_id'] ?? null;
                $stockBranchId = $productsById[$productId]->resolveStockBranchId($branchId);

                $batchMap = [];

                // Batch for free quantity (price = 0)
                if ($freeQty > 0) {
                    $freeBatch = $this->createOrUpdateBatch(
                        $stockBranchId, $productId, 0, $expiryDate, $serial, $freeQty
                    );
                    $freeBatch->inStock((int) $freeQty);
                    $batchMap[$freeBatch->id] = $freeQty;
                }

                // Batch for paid quantity
                $paidBatch = $this->createOrUpdateBatch(
                    $stockBranchId, $productId, $unitPrice, $expiryDate, $serial, $qty
                );
                $paidBatch->inStock((int) $qty);

                if (isset($batchMap[$paidBatch->id])) {
                    $batchMap[$paidBatch->id] += $qty;
                } else {
                    $batchMap[$paidBatch->id] = $qty;
                }

                // Update variation stock
                if ($variationId) {
                    $this->adjustPurchaseVariationStock((int) $variationId, $qty + $freeQty);
                }

                $grossAmount += $qty * $unitPrice;

                $purchaseProductsData[] = [
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'serial' => $serial,
                    'batches' => $batchMap,
                ];
            }

            $vatAmount = $grossAmount * ((float) $data['vat'] / 100);
            $netAmount = $grossAmount + $vatAmount - (float) $data['discount'];
            $dueAmount = max(0, $netAmount - (float) $data['paid_amount']);

            $purchase = Purchase::create([
                'branch_id' => $branchId,
                'supplier_id' => $data['supplier_id'],
                'date' => $data['date'],
                'gross_amount' => $grossAmount,
                'discount' => $data['discount'],
                'vat' => $vatAmount,
                'paid_amount' => $data['paid_amount'],
                'due_amount' => $dueAmount,
                'purchase_type' => PurchaseType::Purchase,
                'comment' => $data['comment'] ?? null,
                'serial' => 'INVP'.str_pad(Purchase::max('id') + 1, 8, '0', STR_PAD_LEFT),
            ]);

            foreach ($purchaseProductsData as $lineItem) {
                $purchase->purchaseProducts()->create($lineItem);
            }

            // Update supplier due balance
            $dueChange = $netAmount - (float) $data['paid_amount'];
            Supplier::whereKey($data['supplier_id'])->increment('balance', $dueChange);

            $this->accounting->postPurchase($purchase->fresh(['supplier']), $paymentAccountId);
        });

        return redirect()->route('inventory.purchase.index')
            ->with('success', 'Purchase created successfully.');
    }

    public function show(Purchase $purchase): Response
    {
        $this->authorize('inventory.purchase.view');

        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $purchase->branch_id !== $branchId) {
            abort(404);
        }

        $purchase->load([
            'supplier',
            'branch:id,name',
            'purchaseProducts.product',
            'purchaseProducts.variation',
        ]);

        return Inertia::render('admin/inventory/purchase/show', [
            'purchase' => $purchase,
        ]);
    }

    public function edit(Purchase $purchase): Response|RedirectResponse
    {
        $this->authorize('inventory.purchase.update');

        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $purchase->branch_id !== $branchId) {
            abort(404);
        }

        if (PurchaseReturn::where('purchase_id', $purchase->id)->exists()) {
            return redirect()
                ->route('inventory.purchase.show', $purchase)
                ->with('error', 'This purchase cannot be edited because it has returns.');
        }

        $purchase->load([
            'supplier:id,name,phone',
            'purchaseProducts.product:id,name,code,sale_price,purchase_price',
            'purchaseProducts.variation:id,variation_data,price,purchase_price',
        ]);

        $purchaseProducts = $purchase->purchaseProducts;
        $batchIds = $purchaseProducts
            ->flatMap(fn ($pp) => array_keys($pp->batches ?? []))
            ->filter()
            ->unique()
            ->values();

        /** @var array<int, Batch> $batchesById */
        $batchesById = Batch::query()
            ->whereIn('id', $batchIds)
            ->get(['id', 'purchase_price', 'expiry_date'])
            ->keyBy('id')
            ->all();

        $grossAmount = (float) $purchase->gross_amount;
        $vatPercent = $grossAmount > 0 ? ((float) $purchase->vat / $grossAmount) * 100 : 0;

        $items = $purchaseProducts->map(function ($pp) use ($batchesById) {
            $freeQty = 0.0;
            $expiryDate = null;

            foreach (($pp->batches ?? []) as $batchId => $quantity) {
                $batch = $batchesById[(int) $batchId] ?? null;
                if (! $batch) {
                    continue;
                }

                if ((float) $batch->purchase_price === 0.0) {
                    $freeQty += (float) $quantity;
                } elseif ($expiryDate === null && $batch->expiry_date) {
                    $expiryDate = $batch->expiry_date->format('Y-m-d');
                }
            }

            $product = $pp->product;
            $variation = $pp->variation;
            $sellPrice = $variation ? (float) $variation->price : (float) ($product?->sale_price ?? 0);

            return [
                'product_id' => $pp->product_id,
                'product_name' => $product?->name,
                'product_code' => $product?->code,
                'variation_id' => $pp->variation_id,
                'variation_label' => $variation?->variation_data['label'] ?? null,
                'unit_price' => (float) $pp->unit_price,
                'sell_price' => $sellPrice,
                'quantity' => (float) $pp->quantity,
                'free_quantity' => $freeQty,
                'expiry_date' => $expiryDate ?? '',
                'serial' => $pp->serial ?? '',
            ];
        })->values();

        return Inertia::render('admin/inventory/purchase/edit', [
            'purchase' => [
                'id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'date' => optional($purchase->date)->format('Y-m-d'),
                'gross_amount' => (float) $purchase->gross_amount,
                'discount' => (float) $purchase->discount,
                'vat_percent' => round($vatPercent, 6),
                'paid_amount' => (float) $purchase->paid_amount,
                'due_amount' => (float) $purchase->due_amount,
                'comment' => $purchase->comment,
                'invoice_number' => $purchase->invoice_number,
                'supplier' => $purchase->supplier,
                'items' => $items,
            ],
            'suppliers' => Supplier::query()->ownBranch()->orderBy('name', 'asc')->get(['id', 'name', 'company_name', 'phone']),
            'paymentAccounts' => $this->paymentAccounts(),
        ]);
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('inventory.purchase.update');

        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $purchase->branch_id !== $branchId) {
            abort(404);
        }

        if (PurchaseReturn::where('purchase_id', $purchase->id)->exists()) {
            return back()->with('error', 'This purchase cannot be edited because it has returns.');
        }

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'discount' => ['required', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.free_quantity' => ['required', 'integer', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.serial' => ['nullable', 'string'],
        ]);

        $paymentAccountId = $this->resolvePaymentAccountId($request, (float) $data['paid_amount']);

        try {
            DB::transaction(function () use ($purchase, $data, $branchId, $paymentAccountId) {
                $this->accounting->reverseFor($purchase);
                $purchase->load(['purchaseProducts']);

                // Rollback previous stock changes
                foreach ($purchase->purchaseProducts as $purchaseProduct) {
                    $batches = $purchaseProduct->batches ?? [];
                    $totalLineQuantity = 0;

                    foreach ($batches as $batchId => $quantity) {
                        $qty = (float) $quantity;
                        $totalLineQuantity += $qty;

                        $batch = Batch::whereKey($batchId)->lockForUpdate()->first();

                        if (! $batch) {
                            throw new \RuntimeException('Batch not found.');
                        }

                        if ((float) $batch->available < $qty) {
                            throw new \RuntimeException('This purchase cannot be edited because some stock has already been used.');
                        }

                        $batch->decrement('available', $qty);
                        $batch->refresh();
                        $batch->inStock(-$qty);
                    }

                    if ($purchaseProduct->variation_id) {
                        $this->adjustPurchaseVariationStock(
                            (int) $purchaseProduct->variation_id,
                            -$totalLineQuantity
                        );
                    }
                }

                // Rollback previous supplier due balance
                $oldSupplierId = $purchase->supplier_id;
                $oldDueChange = (float) $purchase->net_amount - (float) $purchase->paid_amount;
                if ($oldSupplierId !== null) {
                    Supplier::whereKey($oldSupplierId)->increment('balance', -$oldDueChange);
                }

                // Replace line items
                $purchase->purchaseProducts()->delete();

                $grossAmount = 0;
                $purchaseProductsData = [];
                $productsById = Product::query()
                    ->whereIn('id', collect($data['items'])->pluck('product_id'))
                    ->get(['id', 'branch_id'])
                    ->keyBy('id');

                foreach ($data['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $freeQty = (float) $item['free_quantity'];
                    $unitPrice = (float) $item['unit_price'];
                    $expiryDate = $item['expiry_date'] ?? null;
                    $serial = $item['serial'] ?? null;
                    $productId = (int) $item['product_id'];
                    $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;
                    $stockBranchId = $productsById[$productId]->resolveStockBranchId($branchId);

                    $batchMap = [];

                    if ($freeQty > 0) {
                        $freeBatch = $this->createOrUpdateBatch(
                            $stockBranchId,
                            $productId,
                            0,
                            $expiryDate,
                            $serial,
                            $freeQty
                        );
                        $freeBatch->inStock((int) $freeQty);
                        $batchMap[$freeBatch->id] = $freeQty;
                    }

                    $paidBatch = $this->createOrUpdateBatch(
                        $stockBranchId,
                        $productId,
                        $unitPrice,
                        $expiryDate,
                        $serial,
                        $qty
                    );
                    $paidBatch->inStock((int) $qty);

                    if (isset($batchMap[$paidBatch->id])) {
                        $batchMap[$paidBatch->id] += $qty;
                    } else {
                        $batchMap[$paidBatch->id] = $qty;
                    }

                    if ($variationId) {
                        $this->adjustPurchaseVariationStock($variationId, $qty + $freeQty);
                    }

                    $grossAmount += $qty * $unitPrice;

                    $purchaseProductsData[] = [
                        'branch_id' => $branchId,
                        'product_id' => $productId,
                        'variation_id' => $variationId,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'serial' => $serial,
                        'batches' => $batchMap,
                    ];
                }

                $vatAmount = $grossAmount * ((float) $data['vat'] / 100);
                $netAmount = $grossAmount + $vatAmount - (float) $data['discount'];
                $dueAmount = max(0, $netAmount - (float) $data['paid_amount']);

                $purchase->update([
                    'supplier_id' => $data['supplier_id'],
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $data['discount'],
                    'vat' => $vatAmount,
                    'paid_amount' => $data['paid_amount'],
                    'due_amount' => $dueAmount,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($purchaseProductsData as $lineItem) {
                    $purchase->purchaseProducts()->create($lineItem);
                }

                $newDueChange = $netAmount - (float) $data['paid_amount'];
                Supplier::whereKey($data['supplier_id'])->increment('balance', $newDueChange);

                $this->accounting->postPurchase($purchase->fresh(['supplier']), $paymentAccountId);
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update purchase.',
                ])
                ->withInput();
        }

        return redirect()
            ->route('inventory.purchase.index')
            ->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorize('inventory.purchase.delete');

        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $purchase->branch_id !== $branchId) {
            abort(404);
        }

        $purchase->load(['purchaseProducts']);

        try {
            DB::transaction(function () use ($purchase) {
                $this->accounting->reverseFor($purchase);

                foreach ($purchase->purchaseProducts as $purchaseProduct) {
                    $batches = $purchaseProduct->batches ?? [];

                    $totalLineQuantity = 0;

                    foreach ($batches as $batchId => $quantity) {
                        $qty = (float) $quantity;
                        $totalLineQuantity += $qty;

                        $batch = Batch::whereKey($batchId)->lockForUpdate()->first();

                        if (! $batch) {
                            throw new \RuntimeException('Batch not found.');
                        }

                        if ((float) $batch->available < $qty) {
                            throw new \RuntimeException('This purchase cannot be deleted because some stock has already been used.');
                        }

                        $batch->decrement('available', $qty);
                        $batch->refresh();
                        $batch->inStock(-$qty);
                    }

                    if ($purchaseProduct->variation_id) {
                        $this->adjustPurchaseVariationStock(
                            (int) $purchaseProduct->variation_id,
                            -$totalLineQuantity
                        );
                    }
                }

                $netAmount = (float) $purchase->gross_amount + (float) $purchase->vat - (float) $purchase->discount;
                $dueChange = $netAmount - (float) $purchase->paid_amount;

                if ($purchase->supplier_id !== null) {
                    Supplier::whereKey($purchase->supplier_id)->increment('balance', -$dueChange);
                }

                Purchase::query()->whereKey($purchase->id)->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Unable to delete purchase.');
        }

        return redirect()->route('inventory.purchase.index')
            ->with('success', 'Purchase deleted successfully.');
    }

    private function adjustPurchaseVariationStock(int $variationId, float $quantity): void
    {
        $mainVariation = ProductVariation::mainWarehouseFor($variationId);

        if (! $mainVariation) {
            return;
        }

        if ($quantity >= 0) {
            $mainVariation->increment('stock', (int) $quantity);
        } else {
            $mainVariation->decrement('stock', (int) abs($quantity));
        }
    }

    private function createOrUpdateBatch(
        ?int $branchId,
        int $productId,
        float $purchasePrice,
        ?string $expiryDate,
        ?string $serial,
        float $available
    ): Batch {
        $batch = Batch::firstOrCreate(
            [
                'branch_id' => $branchId,
                'product_id' => $productId,
                'purchase_price' => $purchasePrice,
                'expiry_date' => $expiryDate,
                'serial' => $serial,
            ],
            ['available' => 0]
        );

        $batch->increment('available', $available);
        $batch->refresh();

        return $batch;
    }
}
