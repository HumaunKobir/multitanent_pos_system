<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\DiscountType;
use App\Enums\SaleType;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ProductExchange;
use App\Models\ProductVariation;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\SpecialDiscountService;
use App\Support\StorageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SellController extends Controller
{
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(
        private InventoryAccountingService $accounting,
        private InventoryCostService $costService,
        private SpecialDiscountService $specialDiscountService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.sell.view');

        $sells = $this->forCurrentBranch(Sell::query())
            ->sale()
            ->withSum('products as line_discount_total', 'discount')
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

    public function create(Request $request): Response
    {
        $this->authorize('inventory.sell.create');

        $branchId = Auth::user()?->branch_id;

        $defaultCustomer = Customer::where('is_default', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->first(['id', 'name', 'phone']);

        $resumedSell = null;
        if ($request->filled('paused')) {
            $pausedSell = $this->forCurrentBranch(Sell::query())
                ->paused()
                ->with([
                    'customer:id,name,phone',
                    'specialDiscount:id,name,discount_type,discount_value',
                    'products.product:id,name,code,sale_price,discount_price',
                    'products.variation:id,variation_data,price,stock',
                ])
                ->findOrFail((int) $request->query('paused'));

            $this->authorizeBranch($pausedSell);
            $resumedSell = $this->buildPosSellPayload($pausedSell, $branchId);
        }

        return Inertia::render('admin/inventory/sell/create', [
            'today' => now()->format('Y-m-d'),
            'defaultCustomer' => $defaultCustomer,
            'paymentAccounts' => $this->paymentAccounts(),
            'specialDiscounts' => $this->activeSpecialDiscounts($branchId),
            'discountTypes' => collect(DiscountType::cases())->map(fn (DiscountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
            'categories' => Category::active()
                ->orderBy('name')
                ->get(['id', 'name', 'image'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'image' => StorageUrl::public($category->image),
                ])
                ->values(),
            'pausedSales' => $this->pausedSalesList($branchId),
            'resumedSell' => $resumedSell,
        ]);
    }

    public function pause(Request $request): RedirectResponse
    {
        $this->authorize('inventory.sell.create');

        $data = $this->validateSellCart($request);
        $branchId = Auth::user()?->branch_id;
        $pausedSellId = $request->integer('paused_sell_id') ?: null;

        try {
            DB::transaction(function () use ($data, $branchId, $pausedSellId) {
                ['grossAmount' => $grossAmount, 'lineDiscountTotal' => $lineDiscountTotal, 'vatAmount' => $vatAmount, 'sellProductsData' => $sellProductsData] = $this->processSellItems(
                    $data['items'],
                    $branchId,
                    (float) $data['vat'],
                    deductStock: false,
                );

                $discountFields = $this->resolveSaleDiscounts($data, $grossAmount, $lineDiscountTotal, $branchId);

                if ($pausedSellId !== null) {
                    $sell = $this->forCurrentBranch(Sell::query())->paused()->findOrFail($pausedSellId);
                    $this->authorizeBranch($sell);
                    $sell->products()->delete();
                } else {
                    $sell = new Sell;
                    $sell->branch_id = $branchId;
                    $sell->type = SaleType::Paused;
                }

                $sell->fill([
                    'customer_id' => $data['customer_id'] ?? null,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $discountFields['discount'],
                    'discount_type' => $discountFields['discount_type'],
                    'discount_value' => $discountFields['discount_value'],
                    'special_discount_id' => $discountFields['special_discount_id'],
                    'special_discount_amount' => $discountFields['special_discount_amount'],
                    'vat' => $vatAmount,
                    'paid_amount' => 0,
                    'comment' => $data['comment'] ?? null,
                ]);
                $sell->type = SaleType::Paused;
                $sell->save();

                foreach ($sellProductsData as $lineItem) {
                    $sell->products()->create($lineItem);
                }
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to pause sale.',
                ])
                ->withInput();
        }

        return redirect()
            ->route('inventory.sell.create')
            ->with('success', 'Sale paused. You can resume it later from the POS screen.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.sell.create');

        $data = $this->validateSellCart($request, requirePayment: true);
        $branchId = Auth::user()?->branch_id;
        $paymentAccountId = $this->resolvePaymentAccountId($request, (float) $data['paid_amount']);
        $pausedSellId = $request->integer('paused_sell_id') ?: null;

        try {
            $sell = DB::transaction(function () use ($data, $branchId, $paymentAccountId, $pausedSellId) {
                ['grossAmount' => $grossAmount, 'lineDiscountTotal' => $lineDiscountTotal, 'vatAmount' => $vatAmount, 'sellProductsData' => $sellProductsData] = $this->processSellItems(
                    $data['items'],
                    $branchId,
                    (float) $data['vat'],
                );

                $discountFields = $this->resolveSaleDiscounts($data, $grossAmount, $lineDiscountTotal, $branchId);

                if ($pausedSellId !== null) {
                    $sell = $this->forCurrentBranch(Sell::query())->paused()->findOrFail($pausedSellId);
                    $this->authorizeBranch($sell);
                    $sell->products()->delete();
                } else {
                    $sell = new Sell(['branch_id' => $branchId]);
                }

                $sell->fill([
                    'customer_id' => $data['customer_id'] ?? null,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $discountFields['discount'],
                    'discount_type' => $discountFields['discount_type'],
                    'discount_value' => $discountFields['discount_value'],
                    'special_discount_id' => $discountFields['special_discount_id'],
                    'special_discount_amount' => $discountFields['special_discount_amount'],
                    'vat' => $vatAmount,
                    'paid_amount' => $data['paid_amount'],
                    'type' => SaleType::Sale,
                    'comment' => $data['comment'] ?? null,
                ]);
                $sell->save();

                foreach ($sellProductsData as $lineItem) {
                    $sell->products()->create($lineItem);
                }

                $sell->load('products');
                $dueAmount = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);

                if ($sell->customer_id && $dueAmount > 0) {
                    Customer::whereKey($sell->customer_id)->increment('balance', $dueAmount);
                }

                $this->accounting->postSale(
                    $sell->fresh(['customer']),
                    $paymentAccountId,
                    $this->costService->costForSell($sell),
                );

                return $sell;
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to create sale.',
                ])
                ->withInput();
        }

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
            'specialDiscount:id,name,discount_type,discount_value',
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

        if ($sell->type === SaleType::Paused) {
            return redirect()
                ->route('inventory.sell.create', ['paused' => $sell->id])
                ->with('success', 'Paused sale loaded. Add more items or complete the sale.');
        }

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
            'specialDiscount:id,name,discount_type,discount_value',
            'products.product:id,name,code,sale_price,discount_price',
            'products.variation:id,variation_data,price,stock',
        ]);

        $branchId = Auth::user()?->branch_id;

        $items = $sell->products->map(function ($sp) use ($branchId) {
            $product = $sp->product;
            if ($sp->variation_id) {
                $availableStock = (float) ProductVariation::query()
                    ->whereKey($sp->variation_id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->value('stock') ?? 0;
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
                'discount' => (float) $sp->discount,
                'sell_price' => $sp->variation_id
                    ? (float) ($sp->variation?->price ?? $sp->unit_price)
                    : (float) ($sp->product?->sale_price ?? $sp->unit_price),
                'quantity' => (float) $sp->quantity,
                'available_stock' => $availableStock,
            ];
        })->values();

        $grossAmount = (float) $sell->gross_amount;
        $taxableBase = max(0, $grossAmount - $sell->lineDiscountTotal());
        $vatPercent = $taxableBase > 0 ? ((float) $sell->vat / $taxableBase) * 100 : 0;

        return Inertia::render('admin/inventory/sell/edit', [
            'sell' => [
                'id' => $sell->id,
                'customer_id' => $sell->customer_id,
                'customer' => $sell->customer,
                'date' => optional($sell->date)->format('Y-m-d'),
                'gross_amount' => $grossAmount,
                'discount' => (float) $sell->discount,
                'discount_type' => $sell->discount_type?->value ?? DiscountType::Flat->value,
                'discount_value' => (float) ($sell->discount_value ?? $sell->discount),
                'special_discount_id' => $sell->special_discount_id,
                'special_discount_amount' => (float) $sell->special_discount_amount,
                'special_discount' => $sell->specialDiscount ? [
                    'id' => $sell->specialDiscount->id,
                    'name' => $sell->specialDiscount->name,
                    'discount_type' => $sell->specialDiscount->discount_type->value,
                    'discount_value' => (float) $sell->specialDiscount->discount_value,
                ] : null,
                'vat_percent' => round($vatPercent, 6),
                'paid_amount' => (float) $sell->paid_amount,
                'comment' => $sell->comment,
                'invoice_number' => 'INVS'.str_pad($sell->id, 8, '0', STR_PAD_LEFT),
                'items' => $items,
            ],
            'paymentAccounts' => $this->paymentAccounts(),
            'specialDiscounts' => $this->activeSpecialDiscounts($branchId),
            'discountTypes' => collect(DiscountType::cases())->map(fn (DiscountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
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
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'special_discount_id' => ['nullable', 'integer', 'exists:special_discounts,id'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentAccountId = $this->resolvePaymentAccountId($request, (float) $data['paid_amount']);

        try {
            DB::transaction(function () use ($sell, $data, $branchId, $paymentAccountId) {
                $this->accounting->reverseFor($sell);
                $sell->load(['products']);

                $oldDue = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);
                if ($sell->customer_id && $oldDue > 0) {
                    Customer::whereKey($sell->customer_id)->decrement('balance', $oldDue);
                }

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

                ['grossAmount' => $grossAmount, 'lineDiscountTotal' => $lineDiscountTotal, 'vatAmount' => $vatAmount, 'sellProductsData' => $sellProductsData] = $this->processSellItems(
                    $data['items'],
                    $branchId,
                    (float) $data['vat'],
                );

                $discountFields = $this->resolveSaleDiscounts($data, $grossAmount, $lineDiscountTotal, $branchId);

                $sell->update([
                    'customer_id' => $data['customer_id'] ?? null,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $discountFields['discount'],
                    'discount_type' => $discountFields['discount_type'],
                    'discount_value' => $discountFields['discount_value'],
                    'special_discount_id' => $discountFields['special_discount_id'],
                    'special_discount_amount' => $discountFields['special_discount_amount'],
                    'vat' => $vatAmount,
                    'paid_amount' => $data['paid_amount'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($sellProductsData as $lineItem) {
                    $sell->products()->create($lineItem);
                }

                $sell->load('products');
                $newDue = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);

                if ($sell->customer_id && $newDue > 0) {
                    Customer::whereKey($sell->customer_id)->increment('balance', $newDue);
                }

                $this->accounting->postSale(
                    $sell->fresh(['customer']),
                    $paymentAccountId,
                    $this->costService->costForSell($sell),
                );
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
                if ($sell->type !== SaleType::Paused) {
                    $this->accounting->reverseFor($sell);

                    $dueAmount = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);
                    if ($sell->customer_id && $dueAmount > 0) {
                        Customer::whereKey($sell->customer_id)->decrement('balance', $dueAmount);
                    }

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
                }

                $sell->products()->delete();
                Sell::whereKey($sell->id)->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to delete sale.');
        }

        $wasPaused = $sell->type === SaleType::Paused;

        return redirect()
            ->route($wasPaused ? 'inventory.sell.create' : 'inventory.sell.index')
            ->with('success', $wasPaused ? 'Paused sale removed.' : 'Sale deleted successfully.');
    }

    /** @return array<int, array<string, mixed>> */
    private function activeSpecialDiscounts(?int $branchId): array
    {
        return SpecialDiscount::query()
            ->active()
            ->when($branchId, fn ($query) => $query->accessibleAtBranch($branchId))
            ->orderBy('min_amount')
            ->get(['id', 'name', 'min_amount', 'max_amount', 'discount_type', 'discount_value'])
            ->map(fn (SpecialDiscount $discount) => [
                'id' => $discount->id,
                'name' => $discount->name,
                'min_amount' => (float) $discount->min_amount,
                'max_amount' => $discount->max_amount !== null ? (float) $discount->max_amount : null,
                'discount_type' => $discount->discount_type->value,
                'discount_value' => (float) $discount->discount_value,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{discount: float, discount_type: DiscountType, discount_value: float, special_discount_id: ?int, special_discount_amount: float}
     */
    private function resolveSaleDiscounts(array $data, float $grossAmount, float $lineDiscountTotal, ?int $branchId): array
    {
        $taxableBase = max(0, $grossAmount - $lineDiscountTotal);
        $discountType = $data['discount_type'] instanceof DiscountType
            ? $data['discount_type']
            : DiscountType::from($data['discount_type']);
        $discountValue = (float) $data['discount_value'];

        if ($discountType === DiscountType::Percent && $discountValue > 100) {
            throw new \RuntimeException('Invoice discount percent cannot exceed 100.');
        }

        $invoiceDiscount = $this->specialDiscountService->computeAmount($discountType, $discountValue, $taxableBase);
        $specialResolved = $this->specialDiscountService->resolveForSale(
            $this->normalizedSpecialDiscountId($data),
            $taxableBase,
            $branchId,
        );

        return [
            'discount' => $invoiceDiscount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'special_discount_id' => $specialResolved['id'] ?? null,
            'special_discount_amount' => $specialResolved['amount'] ?? 0.0,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function normalizedSpecialDiscountId(array $data): ?int
    {
        if (empty($data['special_discount_id'])) {
            return null;
        }

        return (int) $data['special_discount_id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSellCart(Request $request, bool $requirePayment = false): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'special_discount_id' => ['nullable', 'integer', 'exists:special_discounts,id'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => [$requirePayment ? 'required' : 'nullable', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'paused_sell_id' => ['nullable', 'integer', 'exists:sells,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function pausedSalesList(?int $branchId): array
    {
        return $this->forCurrentBranch(Sell::query())
            ->paused()
            ->with('customer:id,name,phone')
            ->withSum('products as item_count', 'quantity')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Sell $sell) => [
                'id' => $sell->id,
                'customer_name' => $sell->customer?->name ?? 'Walk-in',
                'item_count' => (int) ($sell->item_count ?? 0),
                'net_amount' => round((float) $sell->net_amount, 2),
                'paused_at' => $sell->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function buildPosSellPayload(Sell $sell, ?int $branchId): array
    {
        $items = $sell->products->map(function ($sp) use ($branchId) {
            $product = $sp->product;
            if ($sp->variation_id) {
                $availableStock = (float) ProductVariation::query()
                    ->whereKey($sp->variation_id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->value('stock') ?? 0;
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
                'discount' => (string) (float) $sp->discount,
                'quantity' => (float) $sp->quantity,
                'available_stock' => $availableStock,
            ];
        })->values();

        $grossAmount = (float) $sell->gross_amount;
        $taxableBase = max(0, $grossAmount - $sell->lineDiscountTotal());
        $vatPercent = $taxableBase > 0 ? ((float) $sell->vat / $taxableBase) * 100 : 0;

        return [
            'id' => $sell->id,
            'customer_id' => $sell->customer_id,
            'customer' => $sell->customer,
            'date' => optional($sell->date)->format('Y-m-d'),
            'discount_type' => $sell->discount_type?->value ?? DiscountType::Flat->value,
            'discount_value' => (string) (float) ($sell->discount_value ?? $sell->discount),
            'special_discount_id' => $sell->special_discount_id,
            'vat_percent' => (string) round($vatPercent, 6),
            'paid_amount' => (string) (float) $sell->paid_amount,
            'comment' => $sell->comment,
            'items' => $items,
        ];
    }

    /** @return array{grossAmount: float, lineDiscountTotal: float, vatAmount: float, sellProductsData: array<int, array<string, mixed>>} */
    private function processSellItems(array $items, ?int $branchId, float $vatPercent, bool $deductStock = true): array
    {
        $grossAmount = 0.0;
        $lineDiscountTotal = 0.0;
        $sellProductsData = [];

        foreach ($items as $item) {
            $qty = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineGross = $qty * $unitPrice;
            $lineDiscount = min(max(0, (float) ($item['discount'] ?? 0)), $lineGross);
            $productId = (int) $item['product_id'];
            $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;

            $batchMap = [];

            if ($deductStock) {
                if ($variationId) {
                    $variation = ProductVariation::whereKey($variationId)->lockForUpdate()->firstOrFail();
                    if ((float) $variation->stock < $qty) {
                        throw new \RuntimeException('Insufficient stock for variation.');
                    }
                    $variation->decrement('stock', $qty);
                } else {
                    $batchMap = $this->deductBatchStock($branchId, $productId, $qty);
                }
            }

            $grossAmount += $lineGross;
            $lineDiscountTotal += $lineDiscount;

            $sellProductsData[] = [
                'branch_id' => $branchId,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $lineDiscount,
                'batches' => $batchMap,
            ];
        }

        $taxableBase = max(0, $grossAmount - $lineDiscountTotal);
        $vatAmount = $taxableBase * ($vatPercent / 100);

        return [
            'grossAmount' => $grossAmount,
            'lineDiscountTotal' => $lineDiscountTotal,
            'vatAmount' => $vatAmount,
            'sellProductsData' => $sellProductsData,
        ];
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

    private function forCurrentBranch(Builder $query): Builder
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    private function authorizeBranch(Sell $sell): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return;
        }

        if ($sell->branch_id !== $branchId) {
            abort(404);
        }
    }
}
