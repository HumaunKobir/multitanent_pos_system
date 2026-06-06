<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\SaleType;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\ProductExchange;
use App\Models\ProductVariation;
use App\Models\SaleReturn;
use App\Models\Sell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SellController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('inventory.sell.view');

        $sells = Sell::query()->ownBranch()
            ->sale()
            ->with('customer:id,name,phone')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/sell/index', [
            'sells' => $sells,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('inventory.sell.create');

        $branchId = Auth::user()?->branch_id;

        $defaultCustomer = Customer::where('is_default', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->first(['id', 'name', 'phone']);

        return Inertia::render('admin/inventory/sell/create', [
            'today' => now()->format('Y-m-d'),
            'defaultCustomer' => $defaultCustomer,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.sell.create');

        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'discount' => ['required', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;

        $sell = DB::transaction(function () use ($data, $branchId) {
            $grossAmount = 0;
            $sellProductsData = [];

            foreach ($data['items'] as $item) {
                $qty = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $productId = (int) $item['product_id'];
                $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;

                $batchMap = [];

                if ($variationId) {
                    $variation = ProductVariation::whereKey($variationId)->lockForUpdate()->firstOrFail();
                    if ((float) $variation->stock < $qty) {
                        throw new \RuntimeException('Insufficient stock for variation.');
                    }
                    $variation->decrement('stock', $qty);
                } else {
                    $batchMap = $this->deductBatchStock($branchId, $productId, $qty);
                }

                $grossAmount += $qty * $unitPrice;

                $sellProductsData[] = [
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'batches' => $batchMap,
                ];
            }

            $vatAmount = $grossAmount * ((float) $data['vat'] / 100);

            $sell = Sell::create([
                'branch_id' => $branchId,
                'customer_id' => $data['customer_id'] ?? null,
                'date' => $data['date'],
                'gross_amount' => $grossAmount,
                'discount' => $data['discount'],
                'vat' => $vatAmount,
                'paid_amount' => $data['paid_amount'],
                'type' => SaleType::Sale,
                'comment' => $data['comment'] ?? null,
            ]);

            foreach ($sellProductsData as $lineItem) {
                $sell->products()->create($lineItem);
            }

            return $sell;
        });

        return redirect()->to(route('inventory.sell.show', $sell).'?pos_print=1')
            ->with('success', 'Sale created successfully.');
    }

    public function show(Sell $sell): Response
    {
        $this->authorize('inventory.sell.view');
        $this->authorizeBranch($sell);

        $sell->load([
            'customer',
            'branch:id,name',
            'products.product',
            'products.variation',
        ]);

        return Inertia::render('admin/inventory/sell/show', [
            'sell' => $sell,
        ]);
    }

    public function edit(Sell $sell): Response|RedirectResponse
    {
        $this->authorize('inventory.sell.update');
        $this->authorizeBranch($sell);

        if (SaleReturn::where('sell_id', $sell->id)->exists()) {
            return redirect()
                ->route('inventory.sell.show', $sell)
                ->with('error', 'This sale cannot be edited because it has returns.');
        }

        if (ProductExchange::where('sell_id', $sell->id)->exists()) {
            return redirect()
                ->route('inventory.sell.show', $sell)
                ->with('error', 'This sale cannot be edited because it has been exchanged.');
        }

        $sell->load([
            'customer:id,name,phone',
            'products.product:id,name,code,sale_price,discount_price',
            'products.variation:id,variation_data,price,stock',
        ]);

        $branchId = Auth::user()?->branch_id;

        $items = $sell->products->map(function ($sp) use ($branchId) {
            if ($sp->variation_id) {
                $availableStock = (float) ($sp->variation?->stock ?? 0);
            } else {
                $availableStock = (float) Batch::where('product_id', $sp->product_id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->sum('available');
            }

            return [
                'product_id' => $sp->product_id,
                'product_name' => $sp->product?->name,
                'product_code' => $sp->product?->code,
                'variation_id' => $sp->variation_id,
                'variation_label' => $sp->variation?->variation_data['label'] ?? null,
                'unit_price' => (float) $sp->unit_price,
                'sell_price' => $sp->variation_id
                    ? (float) ($sp->variation?->price ?? $sp->unit_price)
                    : (float) ($sp->product?->sale_price ?? $sp->unit_price),
                'quantity' => (float) $sp->quantity,
                'available_stock' => $availableStock,
            ];
        })->values();

        $grossAmount = (float) $sell->gross_amount;
        $vatPercent = $grossAmount > 0 ? ((float) $sell->vat / $grossAmount) * 100 : 0;

        return Inertia::render('admin/inventory/sell/edit', [
            'sell' => [
                'id' => $sell->id,
                'customer_id' => $sell->customer_id,
                'customer' => $sell->customer,
                'date' => optional($sell->date)->format('Y-m-d'),
                'gross_amount' => $grossAmount,
                'discount' => (float) $sell->discount,
                'vat_percent' => round($vatPercent, 6),
                'paid_amount' => (float) $sell->paid_amount,
                'comment' => $sell->comment,
                'invoice_number' => 'INVS'.str_pad($sell->id, 8, '0', STR_PAD_LEFT),
                'items' => $items,
            ],
        ]);
    }

    public function update(Request $request, Sell $sell): RedirectResponse
    {
        $this->authorize('inventory.sell.update');
        $this->authorizeBranch($sell);

        if (SaleReturn::where('sell_id', $sell->id)->exists()) {
            return back()->with('error', 'This sale cannot be edited because it has returns.');
        }

        if (ProductExchange::where('sell_id', $sell->id)->exists()) {
            return back()->with('error', 'This sale cannot be edited because it has been exchanged.');
        }

        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'discount' => ['required', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($sell, $data, $branchId) {
                $sell->load(['products']);

                // Rollback previous stock deductions
                foreach ($sell->products as $sp) {
                    $totalLineQty = (float) $sp->quantity;

                    foreach ($sp->batches ?? [] as $batchId => $quantity) {
                        $batch = Batch::whereKey($batchId)->lockForUpdate()->first();
                        if ($batch) {
                            $batch->increment('available', (float) $quantity);
                            $batch->refresh();
                            $batch->saleReturnStock((float) $quantity);
                        }
                    }

                    if ($sp->variation_id) {
                        ProductVariation::whereKey($sp->variation_id)
                            ->increment('stock', $totalLineQty);
                    }
                }

                $sell->products()->delete();

                $grossAmount = 0;
                $sellProductsData = [];

                foreach ($data['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $unitPrice = (float) $item['unit_price'];
                    $productId = (int) $item['product_id'];
                    $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;

                    $batchMap = [];

                    if ($variationId) {
                        $variation = ProductVariation::whereKey($variationId)->lockForUpdate()->firstOrFail();
                        if ((float) $variation->stock < $qty) {
                            throw new \RuntimeException('Insufficient stock for variation.');
                        }
                        $variation->decrement('stock', $qty);
                    } else {
                        $batchMap = $this->deductBatchStock($branchId, $productId, $qty);
                    }

                    $grossAmount += $qty * $unitPrice;

                    $sellProductsData[] = [
                        'branch_id' => $branchId,
                        'product_id' => $productId,
                        'variation_id' => $variationId,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'batches' => $batchMap,
                    ];
                }

                $vatAmount = $grossAmount * ((float) $data['vat'] / 100);

                $sell->update([
                    'customer_id' => $data['customer_id'] ?? null,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $data['discount'],
                    'vat' => $vatAmount,
                    'paid_amount' => $data['paid_amount'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($sellProductsData as $lineItem) {
                    $sell->products()->create($lineItem);
                }
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update sale.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.sell.index')
            ->with('success', 'Sale updated successfully.');
    }

    public function destroy(Sell $sell): RedirectResponse
    {
        $this->authorize('inventory.sell.delete');
        $this->authorizeBranch($sell);

        $sell->load(['products']);

        try {
            DB::transaction(function () use ($sell) {
                foreach ($sell->products as $sp) {
                    $totalLineQty = (float) $sp->quantity;

                    foreach ($sp->batches ?? [] as $batchId => $quantity) {
                        $batch = Batch::whereKey($batchId)->lockForUpdate()->first();
                        if ($batch) {
                            $batch->increment('available', (float) $quantity);
                            $batch->refresh();
                            $batch->saleReturnStock((float) $quantity);
                        }
                    }

                    if ($sp->variation_id) {
                        ProductVariation::whereKey($sp->variation_id)
                            ->increment('stock', $totalLineQty);
                    }
                }

                Sell::whereKey($sell->id)->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to delete sale.');
        }

        return redirect()->route('inventory.sell.index')
            ->with('success', 'Sale deleted successfully.');
    }

    /** @return array<int|string, float> */
    private function deductBatchStock(?int $branchId, int $productId, float $qty): array
    {
        $batches = Batch::where('product_id', $productId)
            ->where('available', '>', 0)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->oldest()
            ->lockForUpdate()
            ->get();

        $remaining = $qty;
        $batchMap = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $deduct = min((float) $batch->available, $remaining);
            $batchMap[$batch->id] = $deduct;
            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            throw new \RuntimeException('Insufficient stock for one or more products.');
        }

        foreach ($batchMap as $batchId => $deductQty) {
            $batch = $batches->firstWhere('id', $batchId);
            $batch->decrement('available', $deductQty);
            $batch->refresh();
            $batch->outStock($deductQty);
        }

        return $batchMap;
    }

    private function authorizeBranch(Sell $sell): void
    {
        $branchId = Auth::user()?->branch_id;
        if ($branchId !== null && $sell->branch_id !== $branchId) {
            abort(404);
        }
    }
}
