<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\DiscountType;
use App\Enums\SaleType;
use App\Enums\SystemAccountKey;
use App\Http\Controllers\Concerns\AuthorizesBranchUserRecords;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\ProductExchange;
use App\Models\ProductVariation;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Services\CoinService;
use App\Services\CustomerDueAlertService;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\PartyPaymentAllocationService;
use App\Services\PromotionService;
use App\Services\SpecialDiscountService;
use App\Services\SystemAccountService;
use App\Support\StorageUrl;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellController extends Controller
{
    use AuthorizesBranchUserRecords;
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(
        private InventoryAccountingService $accounting,
        private InventoryCostService $costService,
        private SpecialDiscountService $specialDiscountService,
        private PromotionService $promotionService,
        private CustomerDueAlertService $dueAlertService,
        private CoinService $coinService,
        private PartyPaymentAllocationService $allocations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.sell.view');

        $sells = $this->forCurrentBranchUser(Sell::query())
            ->sale()
            ->withSum('products as line_discount_total', 'discount')
            ->with('customer:id,name,phone')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
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
            $pausedSell = $this->forCurrentBranchUser(Sell::query())
                ->paused()
                ->with([
                    'customer:id,name,phone',
                    'specialDiscount:id,name,discount_type,discount_value',
                    'products.product:id,name,code,sale_price,discount_price,category_id,brand_id',
                    'products.promotion:id,name',
                    'products.variation:id,variation_data,price,stock',
                ])
                ->findOrFail((int) $request->query('paused'));

            $this->authorizeBranchUserRecord($pausedSell);
            $resumedSell = $this->buildPosSellPayload($pausedSell, $branchId);
        }

        return Inertia::render('admin/inventory/sell/create', [
            'today' => now()->format('Y-m-d'),
            'defaultCustomer' => $defaultCustomer,
            'paymentAccounts' => $this->paymentAccounts(),
            'specialDiscounts' => $this->activeSpecialDiscounts($branchId),
            'promotions' => $this->promotionService->activeForBranch($branchId),
            'discountTypes' => collect(DiscountType::cases())->map(fn (DiscountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
            'categories' => Category::forCatalogPanel()->active()
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
            'posTerms' => $this->currentBranchPosTerms(),
            'coinSettings' => $this->coinService->settingsPayloadForBranch($branchId),
            'cashInHandAccountId' => SystemAccountService::id(SystemAccountKey::CashInHand, $branchId),
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
                [
                    'grossAmount' => $grossAmount,
                    'lineDiscountTotal' => $lineDiscountTotal,
                    'vatAmount' => $vatAmount,
                    'sellProductsData' => $sellProductsData,
                    'promotionDiscountTotal' => $promotionDiscountTotal,
                    'promotionStacking' => $promotionStacking,
                ] = $this->processSellItems(
                    $data['items'],
                    $branchId,
                    (float) $data['vat'],
                    deductStock: false,
                    saleDate: $data['date'] ?? null,
                );

                $discountFields = $this->resolveSaleDiscounts($data, $grossAmount, $lineDiscountTotal, $branchId, $promotionStacking);

                if ($pausedSellId !== null) {
                    $sell = $this->forCurrentBranchUser(Sell::query())->paused()->findOrFail($pausedSellId);
                    $this->authorizeBranchUserRecord($sell);
                    $sell->products()->delete();
                } else {
                    $sell = new Sell;
                    $sell->branch_id = $branchId;
                    $sell->user_id = $this->currentUserId();
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
                    'promotion_discount_total' => $promotionDiscountTotal,
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
        $pausedSellId = $request->integer('paused_sell_id') ?: null;

        [
            'grossAmount' => $grossAmount,
            'lineDiscountTotal' => $lineDiscountTotal,
            'vatAmount' => $vatAmount,
            'promotionStacking' => $promotionStacking,
        ] = $this->processSellItems(
            $data['items'],
            $branchId,
            (float) $data['vat'],
            deductStock: false,
            saleDate: $data['date'] ?? null,
        );

        $saleTotals = $this->resolveSaleTotals($data, $grossAmount, $lineDiscountTotal, $vatAmount, $branchId, $promotionStacking);
        $payment = $this->resolveSalePayments($data, $saleTotals['net_amount']);
        $this->assertCustomerForDueSale($data['customer_id'] ? (int) $data['customer_id'] : null, $payment['due_amount']);
        $this->assertDueAlertFields($data, $branchId, $payment['due_amount']);

        try {
            $sell = DB::transaction(function () use ($data, $branchId, $pausedSellId, $payment, $saleTotals) {
                [
                    'grossAmount' => $grossAmount,
                    'lineDiscountTotal' => $lineDiscountTotal,
                    'vatAmount' => $vatAmount,
                    'sellProductsData' => $sellProductsData,
                    'promotionDiscountTotal' => $promotionDiscountTotal,
                    'promotionStacking' => $promotionStacking,
                ] = $this->processSellItems(
                    $data['items'],
                    $branchId,
                    (float) $data['vat'],
                    saleDate: $data['date'] ?? null,
                );

                $discountFields = $this->resolveSaleDiscounts($data, $grossAmount, $lineDiscountTotal, $branchId, $promotionStacking);

                if ($pausedSellId !== null) {
                    $sell = $this->forCurrentBranchUser(Sell::query())->paused()->findOrFail($pausedSellId);
                    $this->authorizeBranchUserRecord($sell);
                    $sell->products()->delete();
                } else {
                    $sell = new Sell(['branch_id' => $branchId, 'user_id' => $this->currentUserId()]);
                }

                $customer = Customer::query()->lockForUpdate()->findOrFail((int) $data['customer_id']);
                $settings = $this->coinService->settingsForBranch($branchId);
                $coinResult = $this->resolveCoinResult(
                    $customer,
                    $settings,
                    $saleTotals['net_before_coin'],
                    $saleTotals['coins_redeemed'],
                    $saleTotals['net_before_coin'],
                );

                $sell->fill([
                    'customer_id' => $data['customer_id'] ?? null,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $discountFields['discount'],
                    'discount_type' => $discountFields['discount_type'],
                    'discount_value' => $discountFields['discount_value'],
                    'special_discount_id' => $discountFields['special_discount_id'],
                    'special_discount_amount' => $discountFields['special_discount_amount'],
                    'promotion_discount_total' => $promotionDiscountTotal,
                    'round_off_amount' => $saleTotals['round_off_amount'],
                    'coins_redeemed' => $coinResult['coins_redeemed'],
                    'coin_discount_amount' => $coinResult['coin_discount_amount'],
                    'coins_earned' => $coinResult['coins_earned'],
                    'vat' => $vatAmount,
                    'paid_amount' => $payment['effective_paid'],
                    'type' => SaleType::Sale,
                    'comment' => $data['comment'] ?? null,
                ]);
                $sell->save();

                foreach ($sellProductsData as $lineItem) {
                    $sell->products()->create($lineItem);
                }

                $sell->load('products');

                $coinResult['effective_paid'] = $payment['effective_paid'];
                $this->coinService->applyToSale($sell, $customer, $coinResult);

                if ($sell->customer_id && $payment['due_amount'] > 0) {
                    Customer::whereKey($sell->customer_id)->increment('balance', $payment['due_amount']);

                    $this->dueAlertService->syncFromDueSale(
                        (int) $sell->customer_id,
                        $branchId,
                        $payment['due_amount'],
                        $data['due_given_date'] ?? null,
                        $data['due_alert_action'] ?? null,
                    );
                }

                $this->syncSellPayments($sell, $payment['payment_lines']);

                $this->accounting->postSale(
                    $sell->fresh(['customer']),
                    $payment['payment_lines'],
                    $this->costService->costForSell($sell),
                    $payment['change_amount'],
                );

                return $sell;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors([
                    'items' => $this->saleStoreErrorMessage($e),
                ])
                ->withInput();
        }

        return redirect()->to(route('inventory.sell.show', $sell).'?pos_print=1')
            ->with('success', 'Sale created successfully.')
            ->with('pos_change', $payment['change_amount'] > 0 ? $payment['change_amount'] : null);
    }

    public function show(Sell $sell): Response
    {
        $this->authorize('inventory.sell.view');
        $this->authorizeBranchUserRecord($sell);

        $sell->load([
            'customer',
            'branch:id,name,phone,address,logo,pos_terms_and_conditions',
            'specialDiscount:id,name,discount_type,discount_value',
            'products.product',
            'products.promotion:id,name',
            'products.variation',
            'payments.paymentAccount:id,code,name',
        ]);

        if ($sell->branch) {
            $sell->branch->logo_url = StorageUrl::public($sell->branch->logo);
        }

        $paymentAccountLabels = collect($this->paymentAccounts())->keyBy('id');

        return Inertia::render('admin/inventory/sell/show', [
            'sell' => [
                ...$sell->toArray(),
                'customer' => $sell->customer,
                'branch' => $sell->branch,
                'special_discount' => $sell->specialDiscount,
                'products' => $sell->products,
                'payments' => $sell->payments,
                'collection_payment_details' => $this->allocations->customerCollectionDetailsForSell($sell, $paymentAccountLabels),
            ],
        ]);
    }

    public function edit(Sell $sell): Response|RedirectResponse
    {
        $this->authorize('inventory.sell.update');
        $this->authorizeBranchUserRecord($sell);

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

        if ($this->sellIsFullyPaid($sell)) {
            return redirect()
                ->route('inventory.sell.show', $sell)
                ->with('error', 'Fully paid sales cannot be edited.');
        }

        $paymentOnlyEdit = $this->sellIsPartiallyPaid($sell);

        $sell->load([
            'customer:id,name,phone,point,is_default',
            'specialDiscount:id,name,discount_type,discount_value',
            'products.product:id,name,code,sale_price,discount_price,category_id,brand_id',
            'products.variation:id,variation_data,price,stock',
            'products.promotion:id,name',
            'payments.paymentAccount:id,code,name',
        ]);

        $branchId = Auth::user()?->branch_id;

        $items = $sell->products->map(function ($sp) use ($branchId) {
            $product = $sp->product;
            $lineQty = (float) $sp->quantity;

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
                'category_id' => $sp->product?->category_id,
                'brand_id' => $sp->product?->brand_id,
                'variation_id' => $sp->variation_id,
                'variation_label' => $sp->variation?->variation_data['label'] ?? null,
                'unit_price' => (float) $sp->unit_price,
                'original_unit_price' => (float) ($sp->original_unit_price ?? $sp->unit_price),
                'discount' => (float) $sp->discount,
                'promotion_id' => $sp->promotion_id,
                'promotion_discount' => (float) $sp->promotion_discount,
                'promotion_label' => $sp->promotion?->name,
                'promotion_meta' => $sp->promotion_meta,
                'free_quantity' => (float) ($sp->free_quantity ?? 0),
                'sell_price' => $sp->variation_id
                    ? (float) ($sp->variation?->price ?? $sp->unit_price)
                    : (float) ($sp->product?->sale_price ?? $sp->unit_price),
                'quantity' => $lineQty,
                'available_stock' => $availableStock + $lineQty,
            ];
        })->values();

        $grossAmount = (float) $sell->gross_amount;
        $taxableBase = max(0, $grossAmount - $sell->lineDiscountTotal());
        $vatPercent = $taxableBase > 0 ? ((float) $sell->vat / $taxableBase) * 100 : 0;

        $walkInCustomerId = Customer::query()
            ->where('is_default', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->value('id');

        return Inertia::render('admin/inventory/sell/edit', [
            'walkInCustomerId' => $walkInCustomerId,
            'paymentOnlyEdit' => $paymentOnlyEdit,
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
                'promotion_discount_total' => (float) $sell->promotion_discount_total,
                'round_off_amount' => (float) $sell->round_off_amount,
                'coins_redeemed' => (float) $sell->coins_redeemed,
                'coin_discount_amount' => (float) $sell->coin_discount_amount,
                'coins_earned' => (float) $sell->coins_earned,
                'special_discount' => $sell->specialDiscount ? [
                    'id' => $sell->specialDiscount->id,
                    'name' => $sell->specialDiscount->name,
                    'discount_type' => $sell->specialDiscount->discount_type->value,
                    'discount_value' => (float) $sell->specialDiscount->discount_value,
                ] : null,
                'vat_percent' => round($vatPercent, 6),
                'paid_amount' => (float) $sell->paid_amount,
                'payments' => $this->allocations->sellPaymentLinesForEdit($sell),
                'collection_payments' => $this->allocations->collectionPaymentLinesForSell($sell),
                'comment' => $sell->comment,
                'invoice_number' => 'INVS'.str_pad($sell->id, 8, '0', STR_PAD_LEFT),
                'items' => $items,
            ],
            'paymentAccounts' => $this->paymentAccounts(),
            'specialDiscounts' => $this->activeSpecialDiscounts($branchId),
            'promotions' => $this->promotionService->activeForBranch($branchId),
            'discountTypes' => collect(DiscountType::cases())->map(fn (DiscountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
            'cashInHandAccountId' => SystemAccountService::id(SystemAccountKey::CashInHand, $branchId),
            'coinSettings' => $this->coinService->settingsPayloadForBranch($branchId),
        ]);
    }

    public function update(Request $request, Sell $sell): RedirectResponse
    {
        $this->authorize('inventory.sell.update');
        $this->authorizeBranchUserRecord($sell);

        if (SaleReturn::where('sell_id', $sell->id)->exists()) {
            return back()->with('error', 'This sale cannot be edited because it has returns.');
        }

        if (ProductExchange::where('sell_id', $sell->id)->exists()) {
            return back()->with('error', 'This sale cannot be edited because it has been exchanged.');
        }

        if ($this->sellIsFullyPaid($sell)) {
            return back()->with('error', 'Fully paid sales cannot be edited.');
        }

        if ($this->sellIsPartiallyPaid($sell)) {
            return $this->updateSellPaymentsOnly($request, $sell);
        }

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'special_discount_id' => ['nullable', 'integer', 'exists:special_discounts,id'],
            'round_off_amount' => ['nullable', 'numeric', 'min:0'],
            'coins_redeemed' => ['nullable', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_account_id' => ['required_with:payments', 'integer', 'exists:chart_of_accounts,id'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'due_given_date' => ['nullable', 'date'],
            'due_alert_action' => ['nullable', 'in:merge,separate'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.free_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branchId = Auth::user()?->branch_id;

        [
            'grossAmount' => $grossAmount,
            'lineDiscountTotal' => $lineDiscountTotal,
            'vatAmount' => $vatAmount,
            'promotionStacking' => $promotionStacking,
        ] = $this->processSellItems(
            $data['items'],
            $branchId,
            (float) $data['vat'],
            deductStock: false,
            saleDate: $data['date'] ?? null,
        );

        $saleTotals = $this->resolveSaleTotals(
            $data,
            $grossAmount,
            $lineDiscountTotal,
            $vatAmount,
            $branchId,
            $promotionStacking,
            $this->coinBalanceOffsetForSaleEdit($sell, $data),
        );
        $collectionTotal = $this->allocations->totalCollectionAmountForSell($sell);
        $payment = $this->resolveSalePaymentsWithCollectionAmount($data, $saleTotals['net_amount'], $collectionTotal);
        $this->assertCustomerForDueSale($data['customer_id'] ? (int) $data['customer_id'] : null, $payment['due_amount']);
        $this->assertDueAlertFields($data, $branchId, $payment['due_amount']);

        try {
            DB::transaction(function () use ($sell, $data, $branchId, $payment, $saleTotals) {
                $this->accounting->reverseFor($sell);
                $this->coinService->reverseForSell($sell);
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

                [
                    'grossAmount' => $grossAmount,
                    'lineDiscountTotal' => $lineDiscountTotal,
                    'vatAmount' => $vatAmount,
                    'sellProductsData' => $sellProductsData,
                    'promotionDiscountTotal' => $promotionDiscountTotal,
                    'promotionStacking' => $promotionStacking,
                ] = $this->processSellItems(
                    $data['items'],
                    $branchId,
                    (float) $data['vat'],
                    saleDate: $data['date'] ?? null,
                );

                $discountFields = $this->resolveSaleDiscounts($data, $grossAmount, $lineDiscountTotal, $branchId, $promotionStacking);

                $customer = Customer::query()->lockForUpdate()->findOrFail((int) $data['customer_id']);
                $settings = $this->coinService->settingsForBranch($branchId);
                $coinResult = $this->resolveCoinResult(
                    $customer,
                    $settings,
                    $saleTotals['net_before_coin'],
                    $saleTotals['coins_redeemed'],
                    $saleTotals['net_before_coin'],
                );

                $sell->update([
                    'customer_id' => $data['customer_id'] ?? null,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount' => $discountFields['discount'],
                    'discount_type' => $discountFields['discount_type'],
                    'discount_value' => $discountFields['discount_value'],
                    'special_discount_id' => $discountFields['special_discount_id'],
                    'special_discount_amount' => $discountFields['special_discount_amount'],
                    'promotion_discount_total' => $promotionDiscountTotal,
                    'round_off_amount' => $saleTotals['round_off_amount'],
                    'coins_redeemed' => $coinResult['coins_redeemed'],
                    'coin_discount_amount' => $coinResult['coin_discount_amount'],
                    'coins_earned' => $coinResult['coins_earned'],
                    'vat' => $vatAmount,
                    'paid_amount' => $payment['effective_paid'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($sellProductsData as $lineItem) {
                    $sell->products()->create($lineItem);
                }

                $sell->load('products');

                $coinResult['effective_paid'] = $payment['effective_paid'];
                $this->coinService->applyToSale($sell, $customer, $coinResult);

                if ($sell->customer_id && $payment['due_amount'] > 0) {
                    Customer::whereKey($sell->customer_id)->increment('balance', $payment['due_amount']);

                    $this->dueAlertService->syncFromDueSale(
                        (int) $sell->customer_id,
                        $branchId,
                        $payment['due_amount'],
                        $data['due_given_date'] ?? null,
                        $data['due_alert_action'] ?? null,
                    );
                }

                $this->syncSellPayments($sell, $payment['payment_lines']);

                $this->accounting->postSale(
                    $sell->fresh(['customer']),
                    $payment['payment_lines'],
                    $this->costService->costForSell($sell),
                    $payment['change_amount'],
                );
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors([
                    'items' => $this->saleStoreErrorMessage($e, 'Unable to update sale.'),
                ])
                ->withInput();
        }

        return redirect()->route('inventory.sell.index')
            ->with('success', 'Sale updated successfully.');
    }

    public function destroy(Sell $sell): RedirectResponse
    {
        $this->authorize('inventory.sell.delete');
        $this->authorizeBranchUserRecord($sell);

        $sell->load(['products']);

        try {
            DB::transaction(function () use ($sell) {
                if ($sell->type !== SaleType::Paused) {
                    $sell->refresh();
                    $this->accounting->reverseFor($sell);
                    $this->coinService->reverseForSell($sell);

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
     * @param  array<string, bool>|null  $promotionStacking
     * @return array{discount: float, discount_type: DiscountType, discount_value: float, special_discount_id: ?int, special_discount_amount: float}
     */
    private function resolveSaleDiscounts(array $data, float $grossAmount, float $lineDiscountTotal, ?int $branchId, ?array $promotionStacking = null): array
    {
        $taxableBase = max(0, $grossAmount - $lineDiscountTotal);
        $discountType = $data['discount_type'] instanceof DiscountType
            ? $data['discount_type']
            : DiscountType::from($data['discount_type']);
        $discountValue = (float) $data['discount_value'];

        if ($promotionStacking !== null) {
            if (! $promotionStacking['invoice_discount'] && $discountValue > 0) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Invoice discount cannot be applied with the active promotion stacking rules.',
                ]);
            }

            if (! $promotionStacking['special_discount'] && $this->normalizedSpecialDiscountId($data) !== null) {
                throw ValidationException::withMessages([
                    'special_discount_id' => 'Special discount cannot be applied with the active promotion stacking rules.',
                ]);
            }
        }

        if ($discountType === DiscountType::Percent && $discountValue > 100) {
            throw new \RuntimeException('Invoice discount percent cannot exceed 100.');
        }

        $invoiceDiscount = $this->specialDiscountService->computeAmount($discountType, $discountValue, $taxableBase);
        $specialResolved = $promotionStacking === null || $promotionStacking['special_discount']
            ? $this->specialDiscountService->resolveForSale(
                $this->normalizedSpecialDiscountId($data),
                $taxableBase,
                $branchId,
            )
            : null;

        return [
            'discount' => $invoiceDiscount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'special_discount_id' => $specialResolved['id'] ?? null,
            'special_discount_amount' => $specialResolved['amount'] ?? 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     discount: float,
     *     discount_type: DiscountType,
     *     discount_value: float,
     *     special_discount_id: int|null,
     *     special_discount_amount: float,
     *     round_off_amount: float,
     *     net_amount: float
     * }
     */
    private function resolveSaleTotals(
        array $data,
        float $grossAmount,
        float $lineDiscountTotal,
        float $vatAmount,
        ?int $branchId,
        ?array $promotionStacking = null,
        float $coinBalanceOffset = 0,
    ): array {
        $discountFields = $this->resolveSaleDiscounts($data, $grossAmount, $lineDiscountTotal, $branchId, $promotionStacking);
        $netBeforeCoin = round(
            $grossAmount + $vatAmount - $discountFields['discount'] - $discountFields['special_discount_amount'] - $lineDiscountTotal,
            2,
        );
        $coinFields = $this->resolveCoinFields($data, $netBeforeCoin, $branchId, $coinBalanceOffset);
        $netBeforeRoundOff = round($netBeforeCoin - $coinFields['coin_discount_amount'], 2);
        $roundOffAmount = $this->resolveRoundOffAmount($data, $netBeforeRoundOff);

        return [
            ...$discountFields,
            ...$coinFields,
            'net_before_coin' => $netBeforeCoin,
            'round_off_amount' => $roundOffAmount,
            'net_amount' => round($netBeforeRoundOff - $roundOffAmount, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{coins_redeemed: float, coin_discount_amount: float}
     */
    private function resolveCoinFields(
        array $data,
        float $netBeforeCoin,
        ?int $branchId,
        float $coinBalanceOffset = 0,
    ): array {
        $coinsRedeemed = round(max(0, (float) ($data['coins_redeemed'] ?? 0)), 2);

        if ($coinsRedeemed <= 0) {
            return [
                'coins_redeemed' => 0.0,
                'coin_discount_amount' => 0.0,
            ];
        }

        $settings = $this->coinService->settingsForBranch($branchId);

        if ($settings === null || ! $settings->isActive()) {
            throw ValidationException::withMessages([
                'coins_redeemed' => 'Coin redemption is not enabled for this branch.',
            ]);
        }

        $customer = Customer::query()->findOrFail((int) $data['customer_id']);
        $result = $this->coinService->resolveForSale(
            $customer,
            $settings,
            $netBeforeCoin,
            $coinsRedeemed,
            0,
            $coinBalanceOffset,
        );

        return [
            'coins_redeemed' => $result['coins_redeemed'],
            'coin_discount_amount' => $result['coin_discount_amount'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function coinBalanceOffsetForSaleEdit(?Sell $sell, array $data): float
    {
        if ($sell === null) {
            return 0.0;
        }

        if ((int) $sell->customer_id !== (int) ($data['customer_id'] ?? 0)) {
            return 0.0;
        }

        return round((float) $sell->coins_redeemed - (float) $sell->coins_earned, 2);
    }

    /**
     * @return array{coins_redeemed: float, coin_discount_amount: float, coins_earned: float}
     */
    private function resolveCoinResult(
        Customer $customer,
        ?CoinSettings $settings,
        float $netBeforeCoin,
        float $coinsRedeemed,
        float $earnBase,
    ): array {
        if ($settings === null || ! $settings->isActive()) {
            return [
                'coins_redeemed' => 0.0,
                'coin_discount_amount' => 0.0,
                'coins_earned' => 0.0,
            ];
        }

        return $this->coinService->resolveForSale(
            $customer,
            $settings,
            $netBeforeCoin,
            $coinsRedeemed,
            $earnBase,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveRoundOffAmount(array $data, float $netBeforeRoundOff): float
    {
        $roundOffAmount = round(max(0, (float) ($data['round_off_amount'] ?? 0)), 2);

        if ($roundOffAmount <= 0) {
            return 0.0;
        }

        $netBeforeRoundOff = round(max(0, $netBeforeRoundOff), 2);

        if ($roundOffAmount > $netBeforeRoundOff) {
            throw ValidationException::withMessages([
                'round_off_amount' => 'Round off cannot exceed the net payable.',
            ]);
        }

        return $roundOffAmount;
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
            'customer_id' => ['required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'special_discount_id' => ['nullable', 'integer', 'exists:special_discounts,id'],
            'round_off_amount' => ['nullable', 'numeric', 'min:0'],
            'coins_redeemed' => ['nullable', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0'],
            'paid_amount' => [$requirePayment ? 'required' : 'nullable', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_account_id' => ['required_with:payments', 'integer', 'exists:chart_of_accounts,id'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'paused_sell_id' => ['nullable', 'integer', 'exists:sells,id'],
            'due_given_date' => ['nullable', 'date'],
            'due_alert_action' => ['nullable', 'in:merge,separate'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.free_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function pausedSalesList(?int $branchId): array
    {
        return $this->forCurrentBranchUser(Sell::query())
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
                'category_id' => $sp->product?->category_id,
                'brand_id' => $sp->product?->brand_id,
                'variation_id' => $sp->variation_id,
                'variation_label' => $sp->variation?->variation_data['label'] ?? null,
                'unit_price' => (float) $sp->unit_price,
                'original_unit_price' => (float) ($sp->original_unit_price ?? $sp->unit_price),
                'discount' => (string) (float) $sp->discount,
                'promotion_id' => $sp->promotion_id,
                'promotion_discount' => (float) $sp->promotion_discount,
                'promotion_label' => $sp->promotion?->name,
                'promotion_meta' => $sp->promotion_meta,
                'quantity' => (float) $sp->quantity,
                'free_quantity' => (float) ($sp->free_quantity ?? 0),
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
            'coins_redeemed' => (string) (float) $sell->coins_redeemed,
            'vat_percent' => (string) round($vatPercent, 6),
            'paid_amount' => (string) (float) $sell->paid_amount,
            'comment' => $sell->comment,
            'items' => $items,
        ];
    }

    /** @return array{
     *     grossAmount: float,
     *     lineDiscountTotal: float,
     *     vatAmount: float,
     *     promotionDiscountTotal: float,
     *     promotionStacking: array<string, bool>,
     *     sellProductsData: array<int, array<string, mixed>>
     * } */
    private function processSellItems(
        array $items,
        ?int $branchId,
        float $vatPercent,
        bool $deductStock = true,
        ?string $saleDate = null,
    ): array {
        $promotionResult = $this->promotionService->validateAndResolve($items, $branchId, $saleDate);
        $items = $promotionResult['items'];
        $promotionDiscountTotal = (float) $promotionResult['promotion_discount_total'];
        $promotionStacking = $promotionResult['stacking'];

        $grossAmount = 0.0;
        $lineDiscountTotal = 0.0;
        $sellProductsData = [];

        foreach ($items as $item) {
            $paidQty = (float) $item['quantity'];
            $freeQty = (float) ($item['free_quantity'] ?? 0);
            $totalPhysical = $paidQty + $freeQty;
            $unitPrice = (float) $item['unit_price'];
            $lineGross = $paidQty * $unitPrice;
            $lineDiscount = min(max(0, (float) ($item['discount'] ?? 0)), $lineGross);
            $productId = (int) $item['product_id'];
            $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;

            if (! empty($item['promotion_id']) && ! $promotionStacking['manual_line_discount'] && $lineDiscount > 0) {
                throw ValidationException::withMessages([
                    'items' => 'Manual line discount cannot be applied to promotion items.',
                ]);
            }

            $batchMap = [];

            if ($deductStock) {
                if ($variationId) {
                    $variation = ProductVariation::whereKey($variationId)->lockForUpdate()->firstOrFail();
                    if ((float) $variation->stock < $totalPhysical) {
                        throw new \RuntimeException('Insufficient stock for variation.');
                    }
                    $variation->decrement('stock', $totalPhysical);
                } else {
                    $batchMap = $this->deductBatchStock($branchId, $productId, $totalPhysical);
                }
            }

            $grossAmount += $lineGross;
            $lineDiscountTotal += $lineDiscount;

            $sellProductsData[] = [
                'branch_id' => $branchId,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $paidQty,
                'free_quantity' => $freeQty,
                'unit_price' => $unitPrice,
                'original_unit_price' => (float) ($item['original_unit_price'] ?? $unitPrice),
                'discount' => $lineDiscount,
                'promotion_id' => $item['promotion_id'] ?? null,
                'promotion_discount' => (float) ($item['promotion_discount'] ?? 0),
                'promotion_meta' => $item['promotion_meta'] ?? null,
                'batches' => $batchMap,
            ];
        }

        $taxableBase = max(0, $grossAmount - $lineDiscountTotal);
        $vatAmount = $taxableBase * ($vatPercent / 100);

        return [
            'grossAmount' => $grossAmount,
            'lineDiscountTotal' => $lineDiscountTotal,
            'vatAmount' => $vatAmount,
            'promotionDiscountTotal' => $promotionDiscountTotal,
            'promotionStacking' => $promotionStacking,
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

    private function assertCustomerForDueSale(?int $customerId, float $dueAmount): void
    {
        if ($dueAmount <= 0) {
            return;
        }

        if ($customerId === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Select a customer to record due amount.',
            ]);
        }

        if (Customer::whereKey($customerId)->where('is_default', true)->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Due sales require a registered customer. Walk-in cannot have due.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertDueAlertFields(array $data, ?int $branchId, float $dueAmount): void
    {
        if ($dueAmount <= 0 || blank($data['due_given_date'] ?? null)) {
            return;
        }

        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;

        if ($customerId === null) {
            return;
        }

        $existingAlert = $this->dueAlertService->findActiveAlert($customerId, $branchId);

        if ($existingAlert === null) {
            return;
        }

        if (blank($data['due_alert_action'] ?? null)) {
            throw ValidationException::withMessages([
                'due_alert_action' => 'This customer already has an active due alert. Choose to merge or create a separate alert.',
            ]);
        }
    }

    private function saleStoreErrorMessage(\Throwable $e, string $fallback = 'Unable to create sale.'): string
    {
        if ($e instanceof \RuntimeException) {
            return $e->getMessage();
        }

        if ($e instanceof \Exception && ! $e instanceof QueryException) {
            return $e->getMessage();
        }

        return $fallback;
    }

    private function currentBranchPosTerms(): ?string
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return null;
        }

        $content = Branch::query()
            ->whereKey($branchId)
            ->value('pos_terms_and_conditions');

        return Branch::hasPosTerms($content) ? $content : null;
    }

    private function sellDueAmount(Sell $sell): float
    {
        return round(max(0, (float) $sell->net_amount - (float) $sell->paid_amount), 2);
    }

    private function sellIsFullyPaid(Sell $sell): bool
    {
        return $this->sellDueAmount($sell) <= 0;
    }

    private function sellIsPartiallyPaid(Sell $sell): bool
    {
        return (float) $sell->paid_amount > 0 && $this->sellDueAmount($sell) > 0;
    }

    private function updateSellPaymentsOnly(Request $request, Sell $sell): RedirectResponse
    {
        $data = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_account_id' => ['required_with:payments', 'integer', 'exists:chart_of_accounts,id'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'due_given_date' => ['nullable', 'date'],
            'due_alert_action' => ['nullable', 'in:merge,separate'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $netAmount = round((float) $sell->net_amount, 2);
        $collectionTotal = $this->allocations->totalCollectionAmountForSell($sell);
        $payment = $this->resolveSalePaymentsWithCollectionAmount($data, $netAmount, $collectionTotal);
        $this->assertCustomerForDueSale($sell->customer_id ? (int) $sell->customer_id : null, $payment['due_amount']);
        $this->assertDueAlertFields($data, $branchId, $payment['due_amount']);

        try {
            DB::transaction(function () use ($sell, $data, $branchId, $payment) {
                $sell->load(['products', 'payments']);

                $oldDue = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);

                $this->accounting->reverseFor($sell);

                if ($sell->customer_id && $oldDue > 0) {
                    Customer::whereKey($sell->customer_id)->decrement('balance', $oldDue);
                }

                $sell->update([
                    'paid_amount' => $payment['effective_paid'],
                ]);

                if ($sell->customer_id && $payment['due_amount'] > 0) {
                    Customer::whereKey($sell->customer_id)->increment('balance', $payment['due_amount']);

                    $this->dueAlertService->syncFromDueSale(
                        (int) $sell->customer_id,
                        $branchId,
                        $payment['due_amount'],
                        $data['due_given_date'] ?? null,
                        $data['due_alert_action'] ?? null,
                    );
                }

                $this->syncSellPayments($sell, $payment['payment_lines']);

                $this->accounting->postSale(
                    $sell->fresh(['customer']),
                    $payment['payment_lines'],
                    $this->costService->costForSell($sell),
                    $payment['change_amount'],
                );
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors([
                    'payments' => $this->saleStoreErrorMessage($e, 'Unable to update sale payment.'),
                ])
                ->withInput();
        }

        return redirect()->route('inventory.sell.show', $sell)
            ->with('success', 'Sale payment updated successfully.');
    }
}
