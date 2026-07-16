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
use App\Models\Promotion;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Services\CoinService;
use App\Services\InventoryAccountingService;
use App\Services\InventoryStockService;
use App\Services\ProductExchangeDiscountService;
use App\Services\PromotionService;
use App\Services\SellProductAvailabilityService;
use App\Services\SpecialDiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        private SellProductAvailabilityService $availability,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.product-exchange.view');

        $exchanges = ProductExchange::query()->ownBranchUser()
            ->with(['customer:id,name', 'sell:id'])
            ->when($request->search, function ($q, string $s): void {
                $q->where(function ($q) use ($s): void {
                    $term = trim($s);
                    $sequence = ProductExchange::extractInvoiceSequence($term);
                    $isInvoiceQuery = $sequence !== null && (
                        str_starts_with(strtoupper($term), ProductExchange::invoicePrefix())
                        || (ctype_digit($term) && strlen($term) <= 8)
                    );

                    if ($isInvoiceQuery) {
                        $q->where('invoice_sequence', $sequence)
                            ->orWhere(fn ($q) => $q->whereNull('invoice_sequence')->where('id', $sequence));

                        return;
                    }

                    $q->whereHas(
                        'customer',
                        fn ($q) => $q->where('name', 'like', "%{$term}%"),
                    );
                });
            })
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
                // Include unrecovered overpaid refund so the Due column matches
                // customer balance / due-collection, not just settlement remainder.
                'due_amount' => round($exchange->dueAmount() + $exchange->overpaidDueAmount(), 2),
                'overpaid_due_amount' => $exchange->overpaidDueAmount(),
                'is_refund' => (float) $exchange->price_difference < 0,
            ]);

        return Inertia::render('admin/inventory/product-exchange/index', [
            'exchanges' => $exchanges,
            'filters' => $request->only('search'),
            'paymentAccounts' => $this->paymentAccountsForBranch(Auth::user()?->branch_id),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('inventory.product-exchange.create');

        $branchId = Auth::user()?->branch_id;

        return Inertia::render('admin/inventory/product-exchange/create', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccountsForBranch($branchId),
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
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.return_quantity' => ['nullable', 'integer', 'min:0'],
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

                if (ProductExchange::query()->where('sell_id', $parent->id)->exists()) {
                    throw new \RuntimeException('This sale has already been exchanged.');
                }

                $processed = $this->processExchangeLines($data, $parent, $branchId);
                $totals = $processed['totals'];
                $lines = $processed['lines'];
                $oldTotal = $processed['old_total'];
                $oldPromotionTotal = $processed['old_promotion_total'];
                $grossAmount = $processed['gross_amount'];
                $returnRefund = $processed['return_refund'];

                if ($lines === []) {
                    throw new \RuntimeException('Add an exchange or return quantity for at least one line.');
                }

                $signedSettlement = round(
                    $this->resolveExchangeSignedSettlement($totals, $oldTotal - $oldPromotionTotal, $grossAmount) - $returnRefund,
                    2,
                );
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
                    'return_refund_amount' => $returnRefund,
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
            'sell.products.product',
            'sell.products.variation',
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
            abort(403, 'This exchange cannot be edited.');
        }

        $paymentOnlyEdit = false;

        $linesOnExchange = $productExchange->products->keyBy('sell_product_id');
        $returnedByLine = $this->availability->returnedQuantitiesByLine($parent->id);

        $promotionIds = $parent->products->pluck('promotion_id')->filter()->unique()->values()->all();
        $promotionMap = $promotionIds !== []
            ? Promotion::whereIn('id', $promotionIds)->get()->keyBy('id')
            : collect();

        $items = $parent->products->map(function ($sp) use ($linesOnExchange, $promotionMap, $returnedByLine) {
            $current = $linesOnExchange->get($sp->id);
            $hasSwap = $current && (float) $current->old_quantity > 0;
            $returnedElsewhere = (float) ($returnedByLine[$sp->id] ?? 0);

            $promotionDetails = null;
            if ($sp->promotion_id && $promotionMap->has($sp->promotion_id)) {
                $promo = $promotionMap->get($sp->promotion_id);
                $promotionDetails = [
                    'type' => $promo->type->value,
                    'name' => $promo->name,
                    'min_qty' => $promo->min_qty,
                    'buy_qty' => $promo->buy_qty,
                ];
            }

            $catalogPrice = (float) ($sp->original_unit_price ?? $sp->unit_price);
            $sellPrice = $sp->variation_id
                ? (float) ($sp->variation?->price ?? $sp->unit_price)
                : (float) ($sp->product?->sale_price ?? $sp->unit_price);

            return [
                'sell_product_id' => $sp->id,
                'product_id' => $sp->product_id,
                'old_product_id' => $sp->product_id,
                'old_product_name' => $sp->product?->name,
                'old_product_code' => $sp->product?->code,
                'old_variation_id' => $sp->variation_id,
                'old_variation_label' => $sp->variation?->variation_data['label'] ?? null,
                'old_unit_price' => $catalogPrice,
                'original_old_unit_price' => $catalogPrice,
                'sold_quantity' => (int) $sp->quantity,
                'returned_elsewhere' => (int) $returnedElsewhere,
                'available_quantity' => (int) max(0, (float) $sp->quantity - $returnedElsewhere),
                'quantity' => $current ? (string) (int) $current->old_quantity : '0',
                'return_quantity' => $current ? (string) (int) $current->return_quantity : '0',
                'new_product_id' => $hasSwap ? $current->new_product_id : '',
                'new_product_name' => $hasSwap ? $current->newProduct?->name : '',
                'new_product_code' => $hasSwap ? $current->newProduct?->code : null,
                'new_variation_id' => $hasSwap ? $current->new_variation_id : null,
                'new_variation_label' => $hasSwap ? ($current->newVariation?->variation_data['label'] ?? null) : null,
                'new_unit_price' => $hasSwap ? (string) $current->new_unit_price : (string) $sellPrice,
                'new_original_unit_price' => $hasSwap
                    ? ($current->new_original_unit_price ? (string) $current->new_original_unit_price : (string) $current->new_unit_price)
                    : (string) $sellPrice,
                'new_free_quantity' => $hasSwap ? (string) (float) $current->new_free_quantity : '0',
                'new_promotion_id' => $hasSwap ? $current->new_promotion_id : null,
                'new_promotion_name' => $hasSwap ? $current->newPromotion?->name : null,
                'new_promotion_discount' => $hasSwap ? (string) $current->new_promotion_discount : '0',
                'new_line_discount' => $hasSwap ? (string) (float) $current->new_line_discount : '0',
                'line_discount' => (float) $sp->discount,
                'promotion_id' => $sp->promotion_id,
                'promotion_details' => $promotionDetails,
                'promotion_discount' => (float) $sp->promotion_discount,
                'category_id' => $sp->product?->category_id,
                'brand_id' => $sp->product?->brand_id,
            ];
        })->values();

        $branchId = Auth::user()?->branch_id;

        return Inertia::render('admin/inventory/product-exchange/edit', [
            'today' => now()->format('Y-m-d'),
            'paymentOnlyEdit' => $paymentOnlyEdit,
            'paymentAccounts' => $this->paymentAccountsForBranch($branchId),
            'promotions' => $this->promotionService->activeForBranch($branchId),
            'specialDiscounts' => $this->activeSpecialDiscounts($branchId),
            'totals' => $this->exchangeShowTotals($productExchange),
            'exchange' => [
                'id' => $productExchange->id,
                'sell_id' => $productExchange->sell_id,
                'customer_id' => $productExchange->customer_id,
                'sale_invoice' => $parent->invoice_number,
                'customer_name' => $productExchange->customer?->name,
                'created_at' => $productExchange->created_at?->toIso8601String(),
                'date' => optional($productExchange->date)->format('Y-m-d'),
                'comment' => $productExchange->comment,
                'paid_amount' => (string) $productExchange->paid_amount,
                'due_amount' => (string) $productExchange->due_amount,
                'overpaid_amount' => (float) $productExchange->overpaid_amount,
                'overpaid_collected_amount' => (float) $productExchange->overpaid_collected_amount,
                'overpaid_due_amount' => $productExchange->overpaidDueAmount(),
                'payment_type' => $productExchange->payment_type?->value,
                'payment_account_id' => $productExchange->payment_account_id,
                'is_editable' => $productExchange->isEditable(),
                'can_access_edit' => $productExchange->canAccessEdit(),
                'payment_only_edit' => $paymentOnlyEdit,
                'payment_status' => $productExchange->paymentStatusLabel(),
                'settlement_amount' => $productExchange->settlementAmount(),
                'is_refund' => (float) $productExchange->price_difference < 0,
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
            'sellDiscounts' => array_merge($this->saleDiscountsPayload($parent), [
                'coin_settings' => $this->coinService->settingsPayloadForBranch($branchId),
            ]),
        ]);
    }

    public function update(Request $request, ProductExchange $productExchange): RedirectResponse
    {
        $this->authorize('inventory.product-exchange.update');
        $this->authorizeBranchUserRecord($productExchange);

        if (! $productExchange->canAccessEdit()) {
            abort(403, 'This exchange cannot be edited.');
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'customer_payment_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:flat,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'special_discount_id' => ['nullable', 'integer', 'exists:special_discounts,id'],
            'round_off_amount' => ['nullable', 'numeric', 'min:0'],
            'coins_redeemed' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sell_product_id' => ['required', 'exists:sell_products,id'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.return_quantity' => ['nullable', 'integer', 'min:0'],
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

                $processed = $this->processExchangeLines($data, $parent, $branchId, $productExchange->id);
                $totals = $processed['totals'];
                $lines = $processed['lines'];
                $oldTotal = $processed['old_total'];
                $oldPromotionTotal = $processed['old_promotion_total'];
                $grossAmount = $processed['gross_amount'];
                $returnRefund = $processed['return_refund'];

                if ($lines === []) {
                    throw new \RuntimeException('Add an exchange or return quantity for at least one line.');
                }

                $signedSettlement = round(
                    $this->resolveExchangeSignedSettlement($totals, $oldTotal - $oldPromotionTotal, $grossAmount) - $returnRefund,
                    2,
                );
                $priceDifference = $signedSettlement;
                $customerAccountEffect = $signedSettlement;
                $payment = $this->exchangeDiscounts->resolvePayment($data, $signedSettlement, (int) $data['payment_type']);

                // If line edits shrank a refund below what was already paid out on a
                // prior save, resolvePayment's clamp would otherwise silently drop the
                // excess. Track it as a customer receivable instead of losing it.
                // customer_payment_amount is cash the customer hands back now, which
                // reduces the receivable posted on this save.
                $overpaidAmount = 0.0;
                if ($signedSettlement < 0 && $paymentType !== ReceivedPaymentMethod::Customer_Account) {
                    $settlementAmount = round(abs($signedSettlement), 2);
                    $submittedPaid = round(max(0, (float) ($data['paid_amount'] ?? 0)), 2);
                    $overpaidAmount = round(max(0, $submittedPaid - $settlementAmount), 2);
                    $customerPaymentNow = round(max(0, (float) ($data['customer_payment_amount'] ?? 0)), 2);

                    if ($overpaidAmount > 0.009 && $customerPaymentNow > 0.009) {
                        $overpaidAmount = round(max(0, $overpaidAmount - min($customerPaymentNow, $overpaidAmount)), 2);
                    }
                }

                $productExchange->update([
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'return_refund_amount' => $returnRefund,
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
                    'overpaid_amount' => $overpaidAmount,
                    'price_difference' => $priceDifference,
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $productExchange->products()->create($line);
                }

                $this->applyExchangeCustomerEffects($parent, $productExchange, $customerAccountEffect, $paymentType, $totals);

                if ($overpaidAmount > 0.009 && $parent->customer_id) {
                    Customer::whereKey($parent->customer_id)->increment('balance', $overpaidAmount);
                }

                $this->accounting->postExchange($productExchange->fresh(['customer', 'sell', 'products']), $paymentAccountId);

                if ($overpaidAmount > 0.009 && $paymentAccountId !== null) {
                    $this->accounting->postExchangeOverpaymentReceivable($productExchange->fresh(['customer']), $paymentAccountId, $overpaidAmount);
                }
            });
        } catch (\Throwable $e) {
            \Log::error('Product exchange update failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

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

        // A collection already recorded against this exchange's overpayment would be
        // orphaned by the delete (and the FK restricts it anyway) — say so plainly.
        if ($productExchange->hasCollectedOverpayment()) {
            return back()->with(
                'error',
                'Delete the overpayment collection for this exchange before deleting the exchange.',
            );
        }

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

    /**
     * Record only the refund/customer-pays settlement for an exchange straight from the list,
     * without touching exchange lines or discounts.
     */
    public function settlePayment(Request $request, ProductExchange $productExchange): RedirectResponse
    {
        $this->authorize('inventory.product-exchange.update');
        $this->authorizeBranchUserRecord($productExchange);

        if ($productExchange->settlementAmount() <= 0) {
            return back()->with('error', 'This exchange has no outstanding amount to settle.');
        }

        $data = $request->validate([
            'payment_type' => ['required', 'integer', Rule::in([
                ReceivedPaymentMethod::Cash->value,
                ReceivedPaymentMethod::Customer_Account->value,
            ])],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $paymentType = ReceivedPaymentMethod::from((int) $data['payment_type']);
        $paymentAccountId = $paymentType === ReceivedPaymentMethod::Cash
            ? $this->requirePaymentAccountId($request)
            : null;

        try {
            DB::transaction(function () use ($productExchange, $data, $paymentType, $paymentAccountId) {
                $productExchange->load(['products', 'sell', 'customer']);

                $this->accounting->reverseFor($productExchange);
                $this->reverseExchangeCustomerAccountEffect($productExchange);

                $payment = $this->exchangeDiscounts->resolvePayment(
                    $data,
                    (float) $productExchange->price_difference,
                    (int) $data['payment_type'],
                );

                $productExchange->update([
                    'paid_amount' => $payment['paid_amount'],
                    'due_amount' => $payment['due_amount'],
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                ]);

                $this->applyExchangeCustomerAccountEffect($productExchange->fresh(), $paymentType);
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
                        : 'Unable to record exchange payment.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.product-exchange.index')
            ->with('success', 'Exchange payment recorded successfully.');
    }

    private function applyExchangeCustomerAccountEffect(ProductExchange $productExchange, ReceivedPaymentMethod $paymentType): void
    {
        if (! $productExchange->customer_id || $paymentType !== ReceivedPaymentMethod::Customer_Account) {
            return;
        }

        $effect = (float) $productExchange->price_difference;

        if ($effect > 0) {
            Customer::whereKey($productExchange->customer_id)->decrement('balance', $effect);
        } elseif ($effect < 0) {
            Customer::whereKey($productExchange->customer_id)->increment('balance', abs($effect));
        }
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
            // Undo the replacement deduction (swap-in).
            $newQty = (float) $line->new_quantity;

            if ($newQty > 0) {
                if ($line->new_variation_id) {
                    $this->stock->restoreVariation(
                        (int) $line->new_variation_id,
                        $newQty + (float) $line->new_free_quantity,
                    );
                } else {
                    $this->stock->restoreFromBatchMap(
                        $line->new_batches ?? [],
                        fn (Batch $batch, float $batchQty) => $batch->saleReturnStock($batchQty)
                    );
                }
            }

            // Undo the swapped-out restore (swap-out went back to stock, take it out again).
            if ((float) $line->old_quantity > 0) {
                if (! empty($line->old_batches)) {
                    $this->stock->deductFromBatchMap(
                        $line->old_batches,
                        fn (Batch $batch, float $batchQty) => $batch->outStock($batchQty)
                    );
                } elseif ($line->old_variation_id) {
                    $this->stock->deductVariation((int) $line->old_variation_id, (float) $line->old_quantity);
                }
            }

            // Undo the returned quantity restore.
            if ((float) $line->return_quantity > 0) {
                if (! empty($line->return_batches)) {
                    $this->stock->deductFromBatchMap(
                        $line->return_batches,
                        fn (Batch $batch, float $batchQty) => $batch->outStock($batchQty)
                    );
                } elseif ($line->old_variation_id) {
                    $this->stock->deductVariation((int) $line->old_variation_id, (float) $line->return_quantity);
                }
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

        $overpaidAmount = (float) $productExchange->overpaid_amount;

        if ($productExchange->customer_id && $overpaidAmount > 0.009) {
            Customer::whereKey($productExchange->customer_id)->decrement('balance', $overpaidAmount);
        }
    }

    /**
     * Split a sold-line batch map between a swap quantity and a return quantity,
     * without attributing more than was originally taken from any single batch.
     *
     * @param  array<int|string, float>  $batches
     * @return array{0: array<int|string, float>, 1: array<int|string, float>}
     */
    private function splitBatchMap(array $batches, float $swapQty, float $returnQty): array
    {
        if ($batches === []) {
            return [[], []];
        }

        $swapMap = [];
        $returnMap = [];
        $remainingSwap = $swapQty;
        $remainingReturn = $returnQty;

        foreach ($batches as $batchId => $soldQty) {
            $available = (float) $soldQty;

            if ($remainingSwap > 0 && $available > 0) {
                $take = min($available, $remainingSwap);

                if ($take > 0) {
                    $swapMap[$batchId] = $take;
                    $remainingSwap -= $take;
                    $available -= $take;
                }
            }

            if ($remainingReturn > 0 && $available > 0) {
                $take = min($available, $remainingReturn);

                if ($take > 0) {
                    $returnMap[$batchId] = $take;
                    $remainingReturn -= $take;
                }
            }

            if ($remainingSwap <= 0 && $remainingReturn <= 0) {
                break;
            }
        }

        return [$swapMap, $returnMap];
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
    private function processExchangeLines(array $data, Sell $parent, ?int $branchId, ?int $excludeExchangeId = null): array
    {
        $swapItems = array_values(array_filter(
            $data['items'],
            fn (array $item) => (int) ($item['quantity'] ?? 0) > 0,
        ));

        $promotionResult = $this->exchangeDiscounts->resolvePromotions(
            $swapItems,
            $parent,
            $branchId,
            $data['date'],
        );
        $promoLineMap = $promotionResult['items'];
        $promotionDiscountTotal = (float) $promotionResult['promotion_discount_total'];

        // A pending Sale Return against the original line reduces what's still available to
        // exchange (coexistence): the two consume the same original-quantity pool.
        $returnedByLine = $this->availability->returnedQuantitiesByLine($parent->id);
        $exchangedByLine = $this->availability->exchangedQuantitiesByLine($parent->id, $excludeExchangeId);

        $grossAmount = 0.0;
        $oldTotal = 0.0;
        $oldPromotionTotal = 0.0;
        $lineDiscountTotal = 0.0;
        $returnRefundTotal = 0.0;
        $lines = [];

        foreach ($data['items'] as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $returnQty = (int) ($item['return_quantity'] ?? 0);

            if ($qty <= 0 && $returnQty <= 0) {
                continue;
            }

            $sellProduct = $parent->products
                ->firstWhere('id', (int) $item['sell_product_id']);

            if (! $sellProduct) {
                throw new \RuntimeException('Invalid sale line.');
            }

            $availableForExchange = (float) $sellProduct->quantity
                - ($returnedByLine[$sellProduct->id] ?? 0)
                - ($exchangedByLine[$sellProduct->id] ?? 0);

            if ($qty + $returnQty > $availableForExchange) {
                throw new \RuntimeException('Exchange and return quantity exceeds the available quantity.');
            }

            $lineOldCatalogPrice = (float) ($sellProduct->original_unit_price ?? $sellProduct->unit_price);

            [$oldBatchMap, $returnBatchMap] = $this->splitBatchMap(
                $sellProduct->batches ?? [],
                $qty,
                $returnQty,
            );

            // Restore the swapped-out quantity to inventory.
            if ($qty > 0) {
                if ($oldBatchMap !== []) {
                    $this->stock->restoreFromBatchMap(
                        $oldBatchMap,
                        fn (Batch $batch, float $batchQty) => $batch->saleReturnStock($batchQty)
                    );
                } elseif ($sellProduct->variation_id) {
                    $this->stock->restoreVariation((int) $sellProduct->variation_id, $qty);
                }
            }

            // Restore the returned (refunded, no replacement) quantity to inventory.
            if ($returnQty > 0) {
                if ($returnBatchMap !== []) {
                    $this->stock->restoreFromBatchMap(
                        $returnBatchMap,
                        fn (Batch $batch, float $batchQty) => $batch->saleReturnStock($batchQty)
                    );
                } elseif ($sellProduct->variation_id) {
                    $this->stock->restoreVariation((int) $sellProduct->variation_id, $returnQty);
                }
            }

            $returnRefundLine = $returnQty > 0
                ? $this->exchangeDiscounts->resolveOldNetTotal($parent, $returnQty * $lineOldCatalogPrice)
                : 0.0;
            $returnRefundTotal += $returnRefundLine;

            // Swap replacement (only when an exchange quantity was requested).
            $newProductId = $sellProduct->product_id;
            $newVariationId = $sellProduct->variation_id;
            $newUnitPrice = 0.0;
            $newCatalogPrice = 0.0;
            $freeQty = 0.0;
            $lineDiscount = 0.0;
            $newBatchMap = [];
            $promoLine = null;

            if ($qty > 0) {
                if (empty($item['product_id'])) {
                    throw new \RuntimeException('Select a replacement product for each exchange line.');
                }

                $newProductId = (int) $item['product_id'];
                $newVariationId = ! empty($item['variation_id']) ? (int) $item['variation_id'] : null;
                $newCatalogPrice = (float) ($item['unit_price'] ?? 0);
                $promoLine = $promoLineMap[$sellProduct->id] ?? null;
                $promoUnitPrice = $promoLine ? (float) $promoLine['unit_price'] : $newCatalogPrice;
                $newUnitPrice = min($newCatalogPrice, $promoUnitPrice);
                $freeQty = $promoLine ? (float) ($promoLine['free_quantity'] ?? 0) : 0;
                $totalPhysical = $qty + $freeQty;
                $lineGross = $qty * $newUnitPrice;

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

                // Credit back the promotion the customer originally received on
                // the swapped-out units, so the old side is valued at what the
                // customer actually paid (catalog minus their original promotion)
                // and mirrors the promotion-adjusted new side. Without this a
                // like-for-like swap of a promoted item shows a phantom refund.
                $originalPromoShare = (float) $sellProduct->quantity > 0
                    ? round((float) $sellProduct->promotion_discount * ($qty / (float) $sellProduct->quantity), 2)
                    : 0.0;

                $oldTotal += $qty * $lineOldCatalogPrice;
                $oldPromotionTotal += $originalPromoShare;
                $grossAmount += $lineGross;
                $lineDiscountTotal += $lineDiscount;
            }

            $lines[] = [
                'branch_id' => $branchId,
                'sell_product_id' => $sellProduct->id,
                'old_product_id' => $sellProduct->product_id,
                'old_variation_id' => $sellProduct->variation_id,
                'old_quantity' => $qty,
                'old_unit_price' => $lineOldCatalogPrice,
                'old_batches' => $oldBatchMap,
                'return_quantity' => $returnQty,
                'return_unit_price' => $lineOldCatalogPrice,
                'return_refund_amount' => round($returnRefundLine, 2),
                'return_batches' => $returnBatchMap,
                'new_product_id' => $newProductId,
                'new_variation_id' => $newVariationId,
                'new_quantity' => $qty,
                'new_unit_price' => $newUnitPrice,
                'new_line_discount' => $lineDiscount,
                'new_promotion_id' => $promoLine['promotion_id'] ?? null,
                'new_original_unit_price' => $newCatalogPrice,
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
            'old_promotion_total' => round($oldPromotionTotal, 2),
            'return_refund' => round($returnRefundTotal, 2),
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
        $returnRefund = round((float) $exchange->return_refund_amount, 2);
        $returnQuantity = (float) $exchange->products->sum(fn ($line) => (float) $line->return_quantity);
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
            'return_refund' => $returnRefund,
            'return_quantity' => $returnQuantity,
            'settlement' => $settlementAmount,
            'is_refund' => $signedSettlement < 0,
            'paid' => (float) $exchange->paid_amount,
            'due' => (float) $exchange->due_amount,
            'overpaid' => (float) $exchange->overpaid_amount,
            'overpaid_collected' => (float) $exchange->overpaid_collected_amount,
            'overpaid_due' => $exchange->overpaidDueAmount(),
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
