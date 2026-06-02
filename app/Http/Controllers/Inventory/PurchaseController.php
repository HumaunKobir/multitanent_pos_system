<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\PurchaseType;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $purchases = Purchase::ownBranch()
            ->purchase()
            ->with('supplier:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('serial', 'like', "%{$s}%")
                    ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$s}%"));
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
        return Inertia::render('admin/inventory/purchase/create', [
            'suppliers' => Supplier::ownBranch()->orderBy('name')->get(['id', 'name', 'phone']),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'discount' => ['required', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.free_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.serial' => ['nullable', 'string'],
        ]);

        $branchId = auth()->user()?->branch_id;

        DB::transaction(function () use ($data, $branchId) {
            $grossAmount = 0;
            $purchaseProductsData = [];

            foreach ($data['items'] as $item) {
                $qty = (float) $item['quantity'];
                $freeQty = (float) $item['free_quantity'];
                $unitPrice = (float) $item['unit_price'];
                $expiryDate = $item['expiry_date'] ?? null;
                $serial = $item['serial'] ?? null;
                $productId = $item['product_id'];
                $variationId = $item['variation_id'] ?? null;

                $batchMap = [];

                // Batch for free quantity (price = 0)
                if ($freeQty > 0) {
                    $freeBatch = $this->createOrUpdateBatch(
                        $branchId, $productId, 0, $expiryDate, $serial, $freeQty
                    );
                    $freeBatch->inStock((int) $freeQty);
                    $batchMap[$freeBatch->id] = $freeQty;
                }

                // Batch for paid quantity
                $paidBatch = $this->createOrUpdateBatch(
                    $branchId, $productId, $unitPrice, $expiryDate, $serial, $qty
                );
                $paidBatch->inStock((int) $qty);

                if (isset($batchMap[$paidBatch->id])) {
                    $batchMap[$paidBatch->id] += $qty;
                } else {
                    $batchMap[$paidBatch->id] = $qty;
                }

                // Update variation stock
                if ($variationId) {
                    ProductVariation::where('id', $variationId)
                        ->increment('stock', $qty + $freeQty);
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

            // Update supplier balance: negative means we owe them
            $supplier = Supplier::find($data['supplier_id']);
            $supplier->increment('balance', $netAmount);
            $supplier->increment('balance_in', $netAmount);

            if ((float) $data['paid_amount'] > 0) {
                $supplier->decrement('balance', (float) $data['paid_amount']);
                $supplier->increment('balance_out', (float) $data['paid_amount']);
            }
        });

        return redirect()->route('inventory.purchase.index')
            ->with('success', 'Purchase created successfully.');
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load([
            'supplier',
            'purchaseProducts.product',
            'purchaseProducts.variation',
        ]);

        return Inertia::render('admin/inventory/purchase/show', [
            'purchase' => $purchase,
        ]);
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
