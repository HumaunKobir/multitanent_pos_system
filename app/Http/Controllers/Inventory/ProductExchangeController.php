<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ReceivedPaymentMethod;
use App\Http\Controllers\Concerns\AuthorizesBranchUserRecords;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\ProductExchange;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Services\CoinService;
use App\Services\InventoryAccountingService;
use App\Services\InventoryStockService;
use App\Services\ProductExchangeDiscountService;
use App\Services\PromotionService;
use App\Services\SpecialDiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductExchangeController extends Controller
{
    use AuthorizesBranchUserRecords;
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(
        private InventoryStockService $stock,
        private InventoryAccountingService $accounting,
        private SpecialDiscountService $specialDiscountService,
        private PromotionService $promotionService,
        private CoinService $coinService,
        private ProductExchangeDiscountService $exchangeDiscounts,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.product-exchange.view');

        $exchanges = ProductExchange::query()->ownBranchUser()
            ->with(['customer:id,name', 'sell:id'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('invoice_sequence', 'like', "%{$s}%")
                    ->orWhere('id', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProductExchange $exchange) => [
                ...$exchange->toArray(),
                'is_editable' => $exchange->isEditable(),
                'can_access_edit' => $exchange->canAccessEdit(),
                'payment_only_edit' => $exchange->isPaymentOnlyEditable(),
                'payment_status' => $exchange->paymentStatusLabel(),
                'settlement_amount' => $exchange->settlementAmount(),
            ]);

        return Inertia::render('admin/inventory/product-exchange/index', [
            'exchanges' => $exchanges,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('inventory.product-exchange.create');

        $branchId = Auth::user()?->branch_id;

        return Inertia::render('admin/inventory/product-exchange/create', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccounts(),
            'promotions' => $this->promotionService->activeForBranch($branchId),
            'specialDiscounts' => $this->activeSpecialDiscounts($branchId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.product-exchange.create');

        $data = $request->validate([
            'sell_id' => ['required', 'exists:sells,id'],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:flat,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'special_discount_id' => ['nullable', 'integer', 'exists:special_discounts,id'],
            'round_off_amount' => ['nullable', 'numeric', 'min:0'],
            'coins_redeemed' => ['nullable', 'numeric', 'min:0'],
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
            ? $this->requirePaymentAccountId($request)
            : null;

        try {
            DB::transaction(function () use ($data, $branchId, $paymentAccountId, $paymentType) {
                $parent = Sell::query()
                    ->ownBranchUser()
                    ->sale()
                    ->with(['products'])
                    ->lockForUpdate()
                    ->findOrFail($data['sell_id']);

                if (ProductExchange::where('sell_id', $parent->id)->exists()) {
                    throw new \RuntimeException('This sale has already been exchanged.');
                }

                $processed = $this->processExchangeLines($data, $parent, $branchId);
                $totals = $processed['totals'];
                $lines = $processed['lines'];
                $oldTotal = $processed['old_total'];
                $grossAmount = $processed['gross_amount'];
                $signedSettlement = $this->resolveExchangeSignedSettlement($totals, $oldTotal, $grossAmount);
                $priceDifference = $signedSettlement;
                $customerAccountEffect = $signedSettlement;
                $payment = $this->exchangeDiscounts->resolvePayment($data, $signedSettlement, (int) $data['payment_type']);

                $exchange = ProductExchange::create([
                    'branch_id' => $branchId,
                    'user_id' => $this->currentUserId(),
                    'sell_id' => $parent->id,
                    'customer_id' => $parent->customer_id,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'special_discount_id' => $totals['special_discount_id'],
                    'special_discount_amount' => $totals['special_discount_amount'],
                    'promotion_discount_total' => $totals['promotion_discount_total'],
                    'discount' => $totals['discount'],
                    'discount_type' => $totals['discount_type'],
                    'discount_value' => $totals['discount_value'],
                    'vat' => $totals['vat'],
                    'round_off_amount' => $totals['round_off_amount'],
                    'coins_redeemed' => $totals['coins_redeemed'],
                    'coin_discount_amount' => $totals['coin_discount_amount'],
                    'coins_earned' => $totals['coins_earned'],
                    'net_amount' => $totals['net_amount'],
                    'paid_amount' => $payment['paid_amount'],
                    'due_amount' => $payment['due_amount'],
                    'price_difference' => $priceDifference,
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $exchange->products()->create($line);
                }

                $this->applyExchangeCustomerEffects($parent, $exchange, $customerAccountEffect, $paymentType, $totals);
                $this->accounting->postExchange($exchange->fresh(['customer', 'sell', 'products']), $paymentAccountId);
            });
        } catch (\Throwable $e) {
            \Log::error('Product exchange create failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

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
        $this->authorizeBranchUserRecord($productExchange);

        $productExchange->load([
            'customer',
            'sell.products.product',
            'specialDiscount:id,name,discount_type,discount_value',
            'products.oldProduct',
            'products.newProduct',
            'products.oldVariation',
            'products.newVariation',
            'products.newPromotion:id,name,type,discount_value,fixed_price',
        ]);

        return Inertia::render('admin/inventory/product-exchange/show', [
            'exchange' => [
                ...$productExchange->toArray(),
                'is_editable' => $productExchange->isEditable(),
                'can_access_edit' => $productExchange->canAccessEdit(),
                'payment_only_edit' => $productExchange->isPaymentOnlyEditable(),
                'payment_status' => $productExchange->paymentStatusLabel(),
                'settlement_amount' => $productExchange->settlementAmount(),
            ],
            'totals' => $this->exchangeShowTotals($productExchange),
            'sell_discounts' => $this->saleDiscountsPayload($productExchange->sell),
        ]);
    }

    public function edit(ProductExchange $productExchange): Response
    {
        $this->authorize('inventory.product-exchange.update');
        $this->authorizeBranchUserRecord($productExchange);

        $productExchange->load([
            'customer',
            'sell.products',
            'products.oldProduct',
            'products.newProduct',
            'products.newVariation',
            'products.newPromotion',
        ]);

        $parent = $productExchange->sell;

        if (! $parent) {
            abort(403, 'This exchange cannot be edited.');
        }

        if (! $productExchange->canAccessEdit()) {
            abort(403, 'This exchange cannot be edited because payment has been recorded.');
        }

        $paymentOnlyEdit = $productExchange->isPaymentOnlyEditable();

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
            'new_original_unit_price' => $line->new_original_unit_price ? (string) $line->new_original_unit_price : (string) $line->new_unit_price,
            'new_free_quantity' => (string) (float) $line->new_free_quantity,
            'new_promotion_id' => $line->new_promotion_id,
            'new_promotion_name' => $line->newPromotion?->name,
            'new_promotion_discount' => (string) $line->new_promotion_discount,
            'new_line_discount' => (string) (float) $line->new_line_discount,
            'old_product_id' => $line->old_product_id,
            'line_discount' => (float) $parent->products->firstWhere('id', $line->sell_product_id)?->discount,
            'promotion_id' => $parent->products->firstWhere('id', $line->sell_product_id)?->promotion_id,
        ])->values();

        $branchId = Auth::user()?->branch_id;

        return Inertia::render('admin/inventory/product-exchange/edit', [
            'today' => now()->format('Y-m-d'),
            'paymentOnlyEdit' => $paymentOnlyEdit,
            'paymentAccounts' => $this->paymentAccounts(),
            'promotions' => $this->promotionService->activeForBranch($branchId),
            'specialDiscounts' => $this->activeSpecialDiscounts($branchId),
            'totals' => $this->exchangeShowTotals($productExchange),
            'exchange' => [
                'id' => $productExchange->id,
                'sell_id' => $productExchange->sell_id,
                'customer_id' => $productExchange->customer_id,
                'sale_invoice' => $parent->invoice_number,
                'customer_name' => $productExchange->customer?->name,
                'date' => optional($productExchange->date)->format('Y-m-d'),
                'comment' => $productExchange->comment,
                'paid_amount' => (string) $productExchange->paid_amount,
                'due_amount' => (string) $productExchange->due_amount,
                'payment_type' => $productExchange->payment_type?->value,
                'payment_account_id' => $productExchange->payment_account_id,
                'is_editable' => $productExchange->isEditable(),
                'can_access_edit' => $productExchange->canAccessEdit(),
                'payment_only_edit' => $paymentOnlyEdit,
                'payment_status' => $productExchange->paymentStatusLabel(),
                'settlement_amount' => $productExchange->settlementAmount(),
                'gross_amount' => (float) $productExchange->gross_amount,
                'discount' => (float) $productExchange->discount,
                'discount_type' => $productExchange->discount_type?->value,
                'discount_value' => (float) $productExchange->discount_value,
                'vat' => (float) $productExchange->vat,
                'round_off_amount' => (float) $productExchange->round_off_amount,
                'special_discount_id' => $productExchange->special_discount_id,
                'special_discount_amount' => (float) $productExchange->special_discount_amount,
                'promotion_discount_total' => (float) $productExchange->promotion_discount_total,
                'coins_redeemed' => (float) $productExchange->coins_redeemed,
                'coin_discount_amount' => (float) $productExchange->coin_discount_amount,
                'coins_earned' => (float) $productExchange->coins_earned,
                'net_amount' => (float) $productExchange->net_amount,
                'price_difference' => (float) $productExchange->price_difference,
                'items' => $items,
            ],
            'sell_discounts' => array_merge($this->saleDiscountsPayload($parent), [
                'coin_settings' => $this->coinService->settingsPayloadForBranch($branchId),
            ]),
        ]);
    }

    public function update(Request $request, ProductExchange $productExchange): RedirectResponse
    {
        $this->authorize('inventory.product-exchange.update');
        $this->authorizeBranchUserRecord($productExchange);

        if (! $productExchange->canAccessEdit()) {
            abort(403, 'This exchange cannot be edited because payment has been recorded.');
        }

        if ($productExchange->isPaymentOnlyEditable()) {
            return $this->updateExchangePaymentOnly($request, $productExchange);
        }

        if (! $productExchange->isEditable()) {
            abort(403, 'This exchange cannot be edited because payment has been recorded.');
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:flat,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'special_discount_id' => ['nullable', 'integer', 'exists:special_discounts,id'],
            'round_off_amount' => ['nullable', 'numeric', 'min:0'],
            'coins_redeemed' => ['nullable', 'numeric', 'min:0'],
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
            ? $this->requirePaymentAccountId($request)
            : null;

        try {
            DB::transaction(function () use ($productExchange, $data, $branchId, $paymentAccountId, $paymentType) {
                $this->accounting->reverseFor($productExchange);
                $this->coinService->reverseForExchange($productExchange);
                $productExchange->load(['products', 'sell']);

                $this->rollbackProductExchange($productExchange);
                $productExchange->products()->delete();

                $parent = Sell::query()
                    ->ownBranchUser()
                    ->sale()
                    ->with(['products'])
                    ->lockForUpdate()
                    ->findOrFail($productExchange->sell_id);

                $processed = $this->processExchangeLines($data, $parent, $branchId);
                $totals = $processed['totals'];
                $lines = $processed['lines'];
                $oldTotal = $processed['old_total'];
                $grossAmount = $processed['gross_amount'];
                $signedSettlement = $this->resolveExchangeSignedSettlement($totals, $oldTotal, $grossAmount);
                $priceDifference = $signedSettlement;
                $customerAccountEffect = $signedSettlement;
                $payment = $this->exchangeDiscounts->resolvePayment($data, $signedSettlement, (int) $data['payment_type']);

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with exchange quantity greater than zero is required.');
                }

                $productExchange->update([
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'special_discount_id' => $totals['special_discount_id'],
                    'special_discount_amount' => $totals['special_discount_amount'],
                    'promotion_discount_total' => $totals['promotion_discount_total'],
                    'discount' => $totals['discount'],
                    'discount_type' => $totals['discount_type'],
                    'discount_value' => $totals['discount_value'],
                    'vat' => $totals['vat'],
                    'round_off_amount' => $totals['round_off_amount'],
                    'coins_redeemed' => $totals['coins_redeemed'],
                    'coin_discount_amount' => $totals['coin_discount_amount'],
                    'coins_earned' => $totals['coins_earned'],
                    'net_amount' => $totals['net_amount'],
                    'paid_amount' => $payment['paid_amount'],
                    'due_amount' => $payment['due_amount'],
                    'price_difference' => $priceDifference,
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $productExchange->products()->create($line);
                }

                $this->applyExchangeCustomerEffects($parent, $productExchange, $customerAccountEffect, $paymentType, $totals);
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
        $this->authorizeBranchUserRecord($productExchange);

        $productExchange->load(['products', 'sell']);

        try {
            DB::transaction(function () use ($productExchange) {
                $this->accounting->reverseFor($productExchange);
                $this->coinService->reverseForExchange($productExchange);
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

    private function updateExchangePaymentOnly(Request $request, ProductExchange $productExchange): RedirectResponse
    {
        $data = $request->validate([
            'comment' => ['nullable', 'string'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentType = ReceivedPaymentMethod::from((int) $data['payment_type']);
        $paymentAccountId = $paymentType === ReceivedPaymentMethod::Cash
            ? $this->requirePaymentAccountId($request)
            : null;

        try {
            DB::transaction(function () use ($productExchange, $data, $paymentType, $paymentAccountId) {
                $productExchange->load(['products', 'sell', 'customer']);

                $this->accounting->reverseFor($productExchange);
                $this->reverseExchangeCustomerAccountEffect($productExchange);

                $oldTotal = round($productExchange->products->sum(
                    fn ($line) => (float) $line->old_quantity * (float) $line->old_unit_price
                ), 2);
                $signedSettlement = (float) $productExchange->price_difference;
                $customerAccountEffect = $signedSettlement;
                $payment = $this->exchangeDiscounts->resolvePayment(
                    $data,
                    $signedSettlement,
                    (int) $data['payment_type'],
                );

                $productExchange->update([
                    'paid_amount' => $payment['paid_amount'],
                    'due_amount' => $payment['due_amount'],
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                    'comment' => $data['comment'] ?? $productExchange->comment,
                ]);

                $totals = [
                    'coins_redeemed' => (float) $productExchange->coins_redeemed,
                    'coin_discount_amount' => (float) $productExchange->coin_discount_amount,
                    'coins_earned' => (float) $productExchange->coins_earned,
                ];

                $this->applyExchangeCustomerEffects(
                    $parent,
                    $productExchange->fresh(),
                    $customerAccountEffect,
                    $paymentType,
                    $totals,
                );
                $this->accounting->postExchange(
                    $productExchange->fresh(['customer', 'sell', 'products']),
                    $paymentAccountId,
                );
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'paid_amount' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update exchange payment.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.product-exchange.show', $productExchange)
            ->with('success', 'Exchange payment updated successfully.');
    }

    private function reverseExchangeCustomerAccountEffect(ProductExchange $productExchange): void
    {
        if (! $productExchange->customer_id
            || $productExchange->payment_type !== ReceivedPaymentMethod::Customer_Account) {
            return;
        }

        $parent = $productExchange->sell;

        if (! $parent) {
            return;
        }

        $oldTotal = round($productExchange->products->sum(
            fn ($line) => (float) $line->old_quantity * (float) $line->old_unit_price
        ), 2);
        $effect = (float) $productExchange->price_difference;

        if ($effect > 0) {
            Customer::whereKey($productExchange->customer_id)->increment('balance', $effect);
        } elseif ($effect < 0) {
            Customer::whereKey($productExchange->customer_id)->decrement('balance', abs($effect));
        }
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

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     gross_amount: float,
     *     old_total: float
     * }
     */
    private function processExchangeLines(array $data, Sell $parent, ?int $branchId): array
    {
        $promotionResult = $this->exchangeDiscounts->resolvePromotions(
            $data['items'],
            $parent,
            $branchId,
            $data['date'],
        );
        $promoLineMap = $promotionResult['items'];
        $promotionDiscountTotal = (float) $promotionResult['promotion_discount_total'];

        $grossAmount = 0.0;
        $oldTotal = 0.0;
        $lineDiscountTotal = 0.0;
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
            $catalogPrice = (float) $item['unit_price'];
            $promoLine = $promoLineMap[$sellProduct->id] ?? null;
            $promoUnitPrice = $promoLine ? (float) $promoLine['unit_price'] : $catalogPrice;
            $newUnitPrice = min($catalogPrice, $promoUnitPrice);
            $freeQty = $promoLine ? (float) ($promoLine['free_quantity'] ?? 0) : 0;
            $totalPhysical = $qty + $freeQty;
            $lineGross = $qty * $newUnitPrice;

            $newBatchMap = [];

            if ($newVariationId) {
                $this->stock->deductVariation($newVariationId, $totalPhysical);
            } else {
                $newBatchMap = $this->stock->deductFifo(
                    $branchId,
                    $newProductId,
                    $totalPhysical,
                    fn (Batch $batch, float $deductQty) => $batch->exchangeStock($deductQty)
                );
            }

            $lineDiscount = $this->exchangeDiscounts->resolveLineDiscount(
                $sellProduct,
                $newProductId,
                $newVariationId,
                $qty,
                $lineGross,
            );

            $lineOldCatalogPrice = (float) ($sellProduct->original_unit_price ?? $sellProduct->unit_price);
            $lineOldTotal = $qty * $lineOldCatalogPrice;
            $oldTotal += $lineOldTotal;
            $grossAmount += $lineGross;
            $lineDiscountTotal += $lineDiscount;

            $lines[] = [
                'branch_id' => $branchId,
                'sell_product_id' => $sellProduct->id,
                'old_product_id' => $sellProduct->product_id,
                'old_variation_id' => $sellProduct->variation_id,
                'old_quantity' => $qty,
                'old_unit_price' => $lineOldCatalogPrice,
                'old_batches' => $oldBatchMap,
                'new_product_id' => $newProductId,
                'new_variation_id' => $newVariationId,
                'new_quantity' => $qty,
                'new_unit_price' => $newUnitPrice,
                'new_line_discount' => $lineDiscount,
                'new_promotion_id' => $promoLine['promotion_id'] ?? null,
                'new_original_unit_price' => $catalogPrice,
                'new_free_quantity' => $freeQty,
                'new_promotion_discount' => (float) ($promoLine['promotion_discount'] ?? 0),
                'new_promotion_meta' => $promoLine['promotion_meta'] ?? null,
                'new_batches' => $newBatchMap,
            ];
        }

        $totals = $this->exchangeDiscounts->resolveExchangeTotals(
            $data,
            $parent,
            $grossAmount,
            $lineDiscountTotal,
            $promotionDiscountTotal,
            $branchId,
            $oldTotal,
        );

        return [
            'lines' => $lines,
            'totals' => $totals,
            'gross_amount' => $grossAmount,
            'old_total' => $oldTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $totals
     */
    private function resolveExchangeSignedSettlement(array $totals, float $oldTotal, float $grossAmount): float
    {
        return $this->exchangeDiscounts->resolveGrossBasedSignedSettlement(
            $oldTotal,
            $grossAmount,
            (float) $totals['discount'],
            (float) $totals['round_off_amount'],
            (float) $totals['line_discount_total'],
            (float) ($totals['special_discount_amount'] ?? 0),
            (float) ($totals['coin_discount_amount'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $totals
     */
    private function applyExchangeCustomerEffects(
        Sell $parent,
        ProductExchange $exchange,
        float $priceDifference,
        ReceivedPaymentMethod $paymentType,
        array $totals,
    ): void {
        if ($parent->customer_id && $paymentType === ReceivedPaymentMethod::Customer_Account) {
            if ($priceDifference > 0) {
                Customer::whereKey($parent->customer_id)->decrement('balance', $priceDifference);
            } elseif ($priceDifference < 0) {
                Customer::whereKey($parent->customer_id)->increment('balance', abs($priceDifference));
            }
        }

        if ($totals['coins_redeemed'] > 0 || $totals['coins_earned'] > 0) {
            $customer = $parent->customer_id
                ? Customer::query()->lockForUpdate()->find($parent->customer_id)
                : null;

            if ($customer && ! $customer->is_default) {
                $this->coinService->applyToExchange($exchange, $customer, [
                    'coins_redeemed' => $totals['coins_redeemed'],
                    'coin_discount_amount' => $totals['coin_discount_amount'],
                    'coins_earned' => $totals['coins_earned'],
                    'effective_paid' => max(0.0, $priceDifference),
                ]);
            }
        }
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
     * @return array<string, mixed>
     */
    private function exchangeShowTotals(ProductExchange $exchange): array
    {
        $exchange->loadMissing(['products', 'sell.products']);

        $oldTotal = round($exchange->products->sum(
            fn ($line) => (float) $line->old_quantity * (float) $line->old_unit_price
        ), 2);
        $soldLineTotal = round($exchange->products->sum(function ($line) use ($exchange) {
            $sellLine = $exchange->sell?->products->firstWhere('id', $line->sell_product_id);
            $soldQty = $sellLine ? (float) $sellLine->quantity : (float) $line->old_quantity;

            return $soldQty * (float) $line->old_unit_price;
        }), 2);
        $grossAmount = round((float) $exchange->gross_amount, 2);
        $netAmount = round((float) $exchange->net_amount, 2);
        $vat = round((float) $exchange->vat, 2);
        $lineDiscount = round($exchange->products->sum(
            fn ($line) => (float) $line->new_line_discount
        ), 2);
        $parent = $exchange->sell;
        $oldNetTotal = $parent
            ? $this->exchangeDiscounts->resolveOldNetTotal($parent, $oldTotal)
            : $oldTotal;
        $signedSettlement = (float) $exchange->price_difference;
        $settlementAmount = abs($signedSettlement) < 0.01 ? 0.0 : round(abs($signedSettlement), 2);

        return [
            'old_total' => $oldTotal,
            'sold_line_total' => $soldLineTotal,
            'old_exchange_total' => $oldTotal,
            'gross' => $grossAmount,
            'gross_price_difference' => round($grossAmount - $oldTotal, 2),
            'new_discount_total' => round(max(0, $grossAmount + $vat - $netAmount), 2),
            'line_discount' => $lineDiscount,
            'vat' => $vat,
            'discount' => (float) $exchange->discount,
            'discount_type' => $exchange->discount_type?->value,
            'discount_value' => (float) $exchange->discount_value,
            'special_discount' => (float) $exchange->special_discount_amount,
            'special_discount_name' => $exchange->specialDiscount?->name,
            'special_discount_type' => $exchange->specialDiscount?->discount_type?->value,
            'special_discount_value' => (float) ($exchange->specialDiscount?->discount_value ?? 0),
            'promotion_discount' => (float) $exchange->promotion_discount_total,
            'coin_discount' => (float) $exchange->coin_discount_amount,
            'coins_redeemed' => (float) $exchange->coins_redeemed,
            'coins_earned' => (float) $exchange->coins_earned,
            'round_off' => (float) $exchange->round_off_amount,
            'net' => $netAmount,
            'settlement' => $settlementAmount,
            'is_refund' => $signedSettlement < 0,
            'paid' => (float) $exchange->paid_amount,
            'due' => (float) $exchange->due_amount,
            'payment_status' => $exchange->paymentStatusLabel(),
        ];
    }

    /**
     * Build a read-only summary of the source sale's discounts for informational display.
     *
     * @return array{
     *   gross_amount: float,
     *   vat: float,
     *   line_discount_total: float,
     *   invoice_discount: float,
     *   invoice_discount_type: string,
     *   invoice_discount_value: float,
     *   special_discount_amount: float,
     *   promotion_discount_total: float,
     *   coin_discount_amount: float,
     *   coins_redeemed: float,
     *   round_off_amount: float,
     *   net_amount: float,
     *   paid_amount: float
     * }
     */
    private function saleDiscountsPayload(?Sell $parent): array
    {
        if (! $parent) {
            return [
                'gross_amount' => 0.0,
                'vat' => 0.0,
                'line_discount_total' => 0.0,
                'invoice_discount' => 0.0,
                'invoice_discount_type' => 'flat',
                'invoice_discount_value' => 0.0,
                'special_discount_amount' => 0.0,
                'promotion_discount_total' => 0.0,
                'coin_discount_amount' => 0.0,
                'coins_redeemed' => 0.0,
                'round_off_amount' => 0.0,
                'net_amount' => 0.0,
                'paid_amount' => 0.0,
            ];
        }

        $parent->loadMissing('products');

        return [
            'gross_amount' => (float) $parent->gross_amount,
            'vat' => (float) $parent->vat,
            'line_discount_total' => $parent->lineDiscountTotal(),
            'invoice_discount' => (float) $parent->discount,
            'invoice_discount_type' => $parent->discount_type?->value ?? 'flat',
            'invoice_discount_value' => (float) $parent->discount_value,
            'special_discount_id' => $parent->special_discount_id,
            'special_discount_amount' => (float) $parent->special_discount_amount,
            'promotion_discount_total' => (float) $parent->promotion_discount_total,
            'coin_discount_amount' => (float) $parent->coin_discount_amount,
            'coins_redeemed' => (float) $parent->coins_redeemed,
            'coins_earned' => (float) $parent->coins_earned,
            'round_off_amount' => (float) $parent->round_off_amount,
            'net_amount' => (float) $parent->net_amount,
            'paid_amount' => (float) $parent->paid_amount,
        ];
    }
}
