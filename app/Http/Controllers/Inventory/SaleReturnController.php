<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ReceivedPaymentMethod;
use App\Http\Controllers\Concerns\AuthorizesBranchUserRecords;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\ProductVariation;
use App\Models\Promotion;
use App\Models\SaleReturn;
use App\Models\SaleReturnPayment;
use App\Models\SaleReturnProduct;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Services\CoinService;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\InventoryStockService;
use App\Services\PromotionService;
use App\Services\SaleReturnDiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleReturnController extends Controller
{
    use AuthorizesBranchUserRecords;
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(
        private InventoryStockService $stock,
        private InventoryAccountingService $accounting,
        private InventoryCostService $costService,
        private SaleReturnDiscountService $returnDiscounts,
        private PromotionService $promotionService,
        private CoinService $coinService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.sale-return.view');

        $returns = SaleReturn::query()->ownBranchUser()
            ->with(['customer:id,name', 'sell:id'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('invoice_sequence', 'like', "%{$s}%")
                    ->orWhere('id', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SaleReturn $saleReturn) => [
                ...$saleReturn->toArray(),
                'is_editable' => $saleReturn->isEditable(),
                'can_access_edit' => $saleReturn->canAccessEdit(),
                'payment_only_edit' => $saleReturn->isPaymentOnlyEditable(),
                'payment_status' => $saleReturn->paymentStatusLabel(),
                'refund_amount' => $saleReturn->refundAmount(),
                'due_amount' => $saleReturn->dueAmount(),
            ]);

        return Inertia::render('admin/inventory/sale-return/index', [
            'returns' => $returns,
            'filters' => $request->only('search'),
            'paymentAccounts' => $this->paymentAccountsForBranch(Auth::user()?->branch_id),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('inventory.sale-return.create');

        $branchId = Auth::user()?->branch_id;

        return Inertia::render('admin/inventory/sale-return/create', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccountsForBranch($branchId),
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
            'payments' => ['nullable', 'array'],
            'payments.*.payment_account_id' => ['required_with:payments', 'integer', 'exists:chart_of_accounts,id'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sell_product_id' => ['required', 'exists:sell_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'manual_invoice_discount_type' => ['nullable', 'in:flat,percent'],
            'manual_invoice_discount_value' => ['nullable', 'numeric', 'min:0'],
            'manual_round_off' => ['nullable', 'numeric', 'min:0'],
            'manual_vat_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($request, $data, &$branchId) {
                $parent = Sell::query()
                    ->ownBranchUser()
                    ->sale()
                    ->with(['products', 'customer'])
                    ->lockForUpdate()
                    ->findOrFail($data['sell_id']);

                $branchId = $this->resolveSaleReturnBranchId($branchId, $parent);

                $returnedByLine = $this->returnedQuantities($parent->id);
                $grossAmount = 0.0;
                $returnLineDiscount = 0.0;
                $returnPromotionDiscount = 0.0;
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

                    $catalogUnitPrice = (float) ($sellProduct->original_unit_price ?? $sellProduct->unit_price);
                    $grossAmount += $returnQty * $catalogUnitPrice;

                    $soldQty = (float) $sellProduct->quantity;
                    if ($soldQty > 0) {
                        $ratio = $returnQty / $soldQty;
                        $returnLineDiscount += (float) $sellProduct->discount * $ratio;
                        $returnPromotionDiscount += $this->returnDiscounts->promotionClawback(
                            $sellProduct,
                            $returnQty,
                            $parent,
                        );
                    }

                    $lines[] = [
                        'branch_id' => $branchId,
                        'sell_product_id' => $sellProduct->id,
                        'product_id' => $sellProduct->product_id,
                        'variation_id' => $sellProduct->variation_id,
                        'quantity' => $returnQty,
                        'unit_price' => $catalogUnitPrice,
                        'batches' => $batchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with return quantity greater than zero is required.');
                }

                $totals = $this->returnDiscounts->calculate(
                    $parent,
                    $grossAmount,
                    $returnLineDiscount,
                    $returnPromotionDiscount,
                    $data['manual_invoice_discount_type'] ?? null,
                    isset($data['manual_invoice_discount_value']) ? (float) $data['manual_invoice_discount_value'] : null,
                    isset($data['manual_round_off']) ? (float) $data['manual_round_off'] : null,
                    isset($data['manual_vat_percent']) ? (float) $data['manual_vat_percent'] : null,
                );
                $discountAmount = $totals['discount_amount'];
                $vatPercent = $totals['vat_percent'];
                $vatAmount = $totals['vat_amount'];
                $netReturnAmount = $totals['net_return_amount'];

                if ($netReturnAmount > $totals['max_net_return_amount'] + 0.01) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'Sale return total (৳%s) cannot exceed the sale value (৳%s). Increase the discount or reduce the VAT.',
                            number_format($netReturnAmount, 2),
                            number_format($totals['max_net_return_amount'], 2),
                        ),
                    ]);
                }

                $refund = $this->resolveReturnRefund($data, $request, $netReturnAmount);
                $paidAmount = $refund['paid_amount'];
                $dueAmount = $refund['due_amount'];
                $paymentType = $refund['payment_type'];
                $paymentAccountId = $refund['payment_account_id'];
                $paymentLines = $refund['payment_lines'];

                $breakdown = $this->returnDiscountBreakdown($data, $totals);

                $saleReturn = SaleReturn::create([
                    'branch_id' => $branchId,
                    'user_id' => $this->currentUserId(),
                    'sell_id' => $parent->id,
                    'customer_id' => $parent->customer_id,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'vat_amount' => $vatAmount,
                    'vat_percent' => $vatPercent,
                    'discount_amount' => $discountAmount,
                    'invoice_discount_type' => $breakdown['invoice_discount_type'],
                    'invoice_discount_value' => $breakdown['invoice_discount_value'],
                    'round_off_amount' => $breakdown['round_off_amount'],
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $saleReturn->products()->create($line);
                }

                if ($parent->customer_id) {
                    $this->syncSaleReturnCustomerBalance(
                        (int) $parent->customer_id,
                        $netReturnAmount,
                        $paymentType,
                        $paidAmount,
                    );
                    $this->syncSaleReturnCustomerCoins(
                        $parent,
                        $saleReturn,
                        $grossAmount,
                        $returnLineDiscount,
                        $returnPromotionDiscount,
                    );
                }

                $this->syncSaleReturnPayments($saleReturn, $paymentLines);
                $saleReturn->load('products');
                $this->accounting->postSaleReturn(
                    $saleReturn->fresh(['customer', 'sell']),
                    $paymentLines,
                    $this->costService->costForSaleReturn($saleReturn),
                );
            });
        } catch (ValidationException $e) {
            throw $e;
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
        $this->authorizeBranchUserRecord($saleReturn);

        $saleReturn->load([
            'customer',
            'sell',
            'branch',
            'products.product',
            'products.variation',
            'payments.paymentAccount:id,code,name',
        ]);

        $parent = Sell::query()
            ->ownBranchUser()
            ->sale()
            ->with('products')
            ->find($saleReturn->sell_id);

        $returnLineDiscount = 0.0;
        $returnPromotionDiscount = 0.0;

        if ($parent) {
            foreach ($saleReturn->products as $line) {
                $sellProduct = $parent->products->firstWhere('id', $line->sell_product_id);

                if (! $sellProduct) {
                    continue;
                }

                $soldQty = (float) $sellProduct->quantity;
                $returnQty = (float) $line->quantity;

                if ($soldQty > 0) {
                    $returnLineDiscount += (float) $sellProduct->discount * ($returnQty / $soldQty);
                    $returnPromotionDiscount += $this->returnDiscounts->promotionClawback($sellProduct, $returnQty, $parent);
                }
            }
        }

        $returnLineDiscount = round($returnLineDiscount, 2);
        $returnPromotionDiscount = round($returnPromotionDiscount, 2);
        $roundOff = round((float) ($saleReturn->round_off_amount ?? 0), 2);
        $invoiceDiscount = round(
            max(0, (float) $saleReturn->discount_amount - $returnLineDiscount - $returnPromotionDiscount - $roundOff),
            2,
        );
        $net = (float) $saleReturn->net_amount;
        $refund = (float) $saleReturn->paid_amount;

        return Inertia::render('admin/inventory/sale-return/show', [
            'saleReturn' => $saleReturn,
            'totals' => [
                'gross' => (float) $saleReturn->gross_amount,
                'line_discount' => $returnLineDiscount,
                'promotion_discount' => $returnPromotionDiscount,
                'invoice_discount' => $invoiceDiscount,
                'invoice_discount_type' => $saleReturn->invoice_discount_type,
                'invoice_discount_value' => $saleReturn->invoice_discount_value !== null
                    ? (float) $saleReturn->invoice_discount_value
                    : null,
                'round_off' => $roundOff,
                'vat' => (float) $saleReturn->vat_amount,
                'vat_percent' => (float) $saleReturn->vat_percent,
                'net' => $net,
                'refund' => $refund,
                'due_refund' => $saleReturn->dueAmount(),
            ],
        ]);
    }

    public function edit(SaleReturn $saleReturn): Response
    {
        $this->authorize('inventory.sale-return.update');
        $this->authorizeBranchUserRecord($saleReturn);

        $saleReturn->load(['customer', 'sell', 'products.product', 'payments']);

        $parent = Sell::query()
            ->ownBranchUser()
            ->sale()
            ->with(['products.product:id,name,code,category_id,brand_id', 'products.variation:id,variation_data', 'payments'])
            ->findOrFail($saleReturn->sell_id);

        $returnedByLine = $this->returnedQuantities($parent->id, $saleReturn->id);
        $linesOnReturn = $saleReturn->products->keyBy('sell_product_id');

        $promotionIds = $parent->products->pluck('promotion_id')->filter()->unique()->values()->all();
        $promotionMap = $promotionIds !== []
            ? Promotion::whereIn('id', $promotionIds)->get()->keyBy('id')
            : collect();

        $items = $parent->products
            ->map(function ($sp) use ($returnedByLine, $linesOnReturn, $promotionMap) {
                $returnedElsewhere = (float) ($returnedByLine[$sp->id] ?? 0);
                $maxReturn = max(0, (float) $sp->quantity - $returnedElsewhere);
                $current = $linesOnReturn->get($sp->id);

                if ($maxReturn <= 0 && ! $current) {
                    return null;
                }

                $promotionDetails = null;
                if ($sp->promotion_id && $promotionMap->has($sp->promotion_id)) {
                    $promo = $promotionMap->get($sp->promotion_id);
                    $promotionDetails = [
                        'type' => $promo->type->value,
                        'min_qty' => $promo->min_qty,
                        'buy_qty' => $promo->buy_qty,
                    ];
                }

                $currentQty = $current ? (float) $current->quantity : 0.0;
                $returnedOnSale = $returnedElsewhere + $currentQty;

                return [
                    'sell_product_id' => $sp->id,
                    'product_id' => $sp->product_id,
                    'variation_id' => $sp->variation_id,
                    'category_id' => $sp->product?->category_id,
                    'brand_id' => $sp->product?->brand_id,
                    'product_name' => $sp->product?->name,
                    'product_code' => $sp->product?->code,
                    'variation_label' => $sp->variation?->variation_data['label'] ?? null,
                    'unit_price' => (float) ($sp->original_unit_price ?? $sp->unit_price),
                    'line_discount' => (float) $sp->discount,
                    'promotion_discount' => (float) $sp->promotion_discount,
                    'promotion_id' => $sp->promotion_id,
                    'promotion_details' => $promotionDetails,
                    'sold_quantity' => (float) $sp->quantity,
                    'returned_elsewhere' => (int) $returnedElsewhere,
                    'returned_quantity' => (int) $returnedOnSale,
                    'available_quantity' => (int) max(0, (float) $sp->quantity - $returnedOnSale),
                    'max_return_quantity' => (int) $maxReturn,
                    'quantity' => $current ? (string) (int) $current->quantity : '0',
                ];
            })
            ->filter()
            ->values();

        $breakdown = $this->resolveEditBreakdown($saleReturn, $parent);

        return Inertia::render('admin/inventory/sale-return/edit', [
            'today' => now()->format('Y-m-d'),
            'paymentOnlyEdit' => false,
            'paymentAccounts' => $this->paymentAccountsForBranch($parent->branch_id),
            'saleReturn' => [
                'id' => $saleReturn->id,
                'sell_id' => $saleReturn->sell_id,
                'sale_invoice' => $saleReturn->sell?->invoice_number,
                'customer_name' => $saleReturn->customer?->name,
                'date' => optional($saleReturn->date)->format('Y-m-d'),
                'comment' => $saleReturn->comment,
                'vat_percent' => (float) $saleReturn->vat_percent,
                'invoice_discount_type' => $breakdown['invoice_discount_type'],
                'invoice_discount_value' => $breakdown['invoice_discount_value'],
                'saved_round_off_amount' => $breakdown['round_off_amount'],
                'paid_amount' => (string) $saleReturn->paid_amount,
                'due_amount' => (string) $saleReturn->due_amount,
                'payment_type' => $saleReturn->payment_type?->value,
                'payment_account_id' => $saleReturn->payment_account_id
                    ?? ($saleReturn->payment_type === ReceivedPaymentMethod::Cash
                        ? $parent->payments->sortByDesc('amount')->first()?->payment_account_id
                        : null),
                'refund_payments' => $saleReturn->payments
                    ->map(fn ($payment) => [
                        'payment_account_id' => $payment->payment_account_id,
                        'amount' => (float) $payment->amount,
                    ])
                    ->values(),
                'is_editable' => $saleReturn->isEditable(),
                'can_access_edit' => $saleReturn->canAccessEdit(),
                'payment_only_edit' => false,
                'payment_status' => $saleReturn->paymentStatusLabel(),
                'refund_amount' => $saleReturn->refundAmount(),
                'items' => $items,
                'sell_discounts' => [
                    'gross_amount' => (float) $parent->gross_amount,
                    'vat' => (float) $parent->vat,
                    'line_discount_total' => $parent->lineDiscountTotal(),
                    'invoice_discount' => (float) $parent->discount,
                    'invoice_discount_type' => $parent->discount_type?->value ?? 'flat',
                    'invoice_discount_value' => (float) $parent->discount_value,
                    'round_off_amount' => (float) $parent->round_off_amount,
                    'net_amount' => (float) $parent->net_amount,
                    'paid_amount' => (float) $parent->paid_amount,
                ],
                'sale_date' => optional($parent->date)->format('Y-m-d'),
                'promotions' => $this->promotionService->activeForBranch($parent->branch_id),
                'payments' => $parent->payments
                    ->map(fn ($payment) => [
                        'payment_account_id' => $payment->payment_account_id,
                        'amount' => (float) $payment->amount,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function update(Request $request, SaleReturn $saleReturn): RedirectResponse
    {
        $this->authorize('inventory.sale-return.update');
        $this->authorizeBranchUserRecord($saleReturn);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', 'integer'],
            'payment_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_account_id' => ['required_with:payments', 'integer', 'exists:chart_of_accounts,id'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sell_product_id' => ['required', 'exists:sell_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'manual_invoice_discount_type' => ['nullable', 'in:flat,percent'],
            'manual_invoice_discount_value' => ['nullable', 'numeric', 'min:0'],
            'manual_round_off' => ['nullable', 'numeric', 'min:0'],
            'manual_vat_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        $saleReturn->loadMissing(['products', 'sell']);

        if ($this->isPaymentOnlySaleReturnUpdate($saleReturn, $data)) {
            return $this->updateSaleReturnPaymentOnly($request, $saleReturn, $data);
        }

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($request, $saleReturn, $data, &$branchId) {
                $saleReturn->load(['products', 'payments']);
                $oldProducts = $saleReturn->products->keyBy('sell_product_id');

                $parent = Sell::query()
                    ->ownBranchUser()
                    ->sale()
                    ->with(['products', 'customer'])
                    ->lockForUpdate()
                    ->findOrFail($saleReturn->sell_id);

                $branchId = $this->resolveSaleReturnBranchId($branchId, $parent);

                $built = $this->buildSaleReturnLines($parent, $saleReturn, $data, $branchId);
                $lines = $built['lines'];
                $grossAmount = $built['gross_amount'];
                $returnLineDiscount = $built['return_line_discount'];
                $returnPromotionDiscount = $built['return_promotion_discount'];

                $saleReturn->payments->each(fn (SaleReturnPayment $payment) => $this->accounting->reverseFor($payment));
                $this->accounting->reverseFor($saleReturn);
                $this->rollbackIncrementalRefundCustomerBalance($saleReturn);
                $this->rollbackSaleReturnCustomerEffects($saleReturn);

                $saleReturn->products()->delete();
                $saleReturn->payments()->delete();

                $this->applySaleReturnStockDelta($oldProducts, $lines, $parent);

                $totals = $this->returnDiscounts->calculate(
                    $parent,
                    $grossAmount,
                    $returnLineDiscount,
                    $returnPromotionDiscount,
                    $data['manual_invoice_discount_type'] ?? null,
                    isset($data['manual_invoice_discount_value']) ? (float) $data['manual_invoice_discount_value'] : null,
                    isset($data['manual_round_off']) ? (float) $data['manual_round_off'] : null,
                    isset($data['manual_vat_percent']) ? (float) $data['manual_vat_percent'] : null,
                );
                $discountAmount = $totals['discount_amount'];
                $vatPercent = $totals['vat_percent'];
                $vatAmount = $totals['vat_amount'];
                $netReturnAmount = $totals['net_return_amount'];

                if ($netReturnAmount > $totals['max_net_return_amount'] + 0.01) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'Sale return total (৳%s) cannot exceed the sale value (৳%s). Increase the discount or reduce the VAT.',
                            number_format($netReturnAmount, 2),
                            number_format($totals['max_net_return_amount'], 2),
                        ),
                    ]);
                }

                $refund = $this->resolveReturnRefund($data, $request, $netReturnAmount);
                $paidAmount = $refund['paid_amount'];
                $dueAmount = $refund['due_amount'];
                $paymentType = $refund['payment_type'];
                $paymentAccountId = $refund['payment_account_id'];
                $paymentLines = $refund['payment_lines'];

                $breakdown = $this->returnDiscountBreakdown($data, $totals);

                $saleReturn->update([
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'vat_amount' => $vatAmount,
                    'vat_percent' => $vatPercent,
                    'discount_amount' => $discountAmount,
                    'invoice_discount_type' => $breakdown['invoice_discount_type'],
                    'invoice_discount_value' => $breakdown['invoice_discount_value'],
                    'round_off_amount' => $breakdown['round_off_amount'],
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $saleReturn->products()->create($line);
                }

                if ($parent->customer_id) {
                    $this->syncSaleReturnCustomerBalance(
                        (int) $parent->customer_id,
                        $netReturnAmount,
                        $paymentType,
                        $paidAmount,
                    );
                    $this->syncSaleReturnCustomerCoins(
                        $parent,
                        $saleReturn,
                        $grossAmount,
                        $returnLineDiscount,
                        $returnPromotionDiscount,
                    );
                }

                $this->syncSaleReturnPayments($saleReturn, $paymentLines);
                $saleReturn->load('products');
                $this->accounting->postSaleReturn(
                    $saleReturn->fresh(['customer', 'sell']),
                    $paymentLines,
                    $this->costService->costForSaleReturn($saleReturn),
                );
            });
        } catch (ValidationException $e) {
            throw $e;
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

    /**
     * Record an incremental cash refund against outstanding sale return due from the list.
     */
    public function settleRefund(Request $request, SaleReturn $saleReturn): RedirectResponse
    {
        $this->authorize('inventory.sale-return.update');
        $this->authorizeBranchUserRecord($saleReturn);

        $currentDue = $saleReturn->dueAmount();

        if ($currentDue <= 0) {
            return back()->with('error', 'This sale return has no outstanding refund due.');
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $amount = round((float) $data['amount'], 2);

        if ($amount > $currentDue + 0.009) {
            return back()->withErrors([
                'amount' => "Amount cannot exceed the remaining refund due of ৳{$currentDue}.",
            ])->withInput();
        }

        try {
            DB::transaction(function () use ($saleReturn, $data, $amount) {
                $payment = SaleReturnPayment::create([
                    'sale_return_id' => $saleReturn->id,
                    'branch_id' => $saleReturn->branch_id,
                    'date' => $data['date'],
                    'payment_account_id' => (int) $data['payment_account_id'],
                    'amount' => $amount,
                ]);

                $saleReturn->increment('paid_amount', $amount);
                $saleReturn->decrement('due_amount', $amount);
                $saleReturn->update([
                    'payment_type' => ReceivedPaymentMethod::Cash,
                    'payment_account_id' => (int) $data['payment_account_id'],
                ]);

                if ($saleReturn->customer_id) {
                    Customer::whereKey($saleReturn->customer_id)->increment('balance', $amount);
                }

                $this->accounting->postSaleReturnRefundPayment($payment->load('saleReturn.customer'));
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to record refund: '.$e->getMessage());
        }

        return back()->with('success', 'Refund recorded successfully.');
    }

    private function updateSaleReturnPaymentOnly(Request $request, SaleReturn $saleReturn, array $data): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $saleReturn, $data) {
                $saleReturn->load(['products', 'payments']);

                $saleReturn->payments->each(fn (SaleReturnPayment $payment) => $this->accounting->reverseFor($payment));
                $this->accounting->reverseFor($saleReturn);
                $this->rollbackIncrementalRefundCustomerBalance($saleReturn);
                $this->rollbackSaleReturnCustomerBalance($saleReturn);

                $netReturnAmount = (float) $saleReturn->net_amount;
                $refund = $this->resolveReturnRefund($data, $request, $netReturnAmount);

                $saleReturn->update([
                    'date' => $data['date'],
                    'paid_amount' => $refund['paid_amount'],
                    'due_amount' => $refund['due_amount'],
                    'payment_type' => $refund['payment_type'],
                    'payment_account_id' => $refund['payment_account_id'],
                    'comment' => $data['comment'] ?? $saleReturn->comment,
                ]);

                if ($saleReturn->customer_id) {
                    $this->syncSaleReturnCustomerBalance(
                        (int) $saleReturn->customer_id,
                        $netReturnAmount,
                        $refund['payment_type'],
                        $refund['paid_amount'],
                    );
                }

                $this->syncSaleReturnPayments($saleReturn, $refund['payment_lines']);
                $this->accounting->postSaleReturn(
                    $saleReturn->fresh(['customer', 'sell']),
                    $refund['payment_lines'],
                    $this->costService->costForSaleReturn($saleReturn->load('products')),
                );
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'paid_amount' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update sale return payment.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.sale-return.index')
            ->with('success', 'Sale return updated successfully.');
    }

    public function destroy(SaleReturn $saleReturn): RedirectResponse
    {
        $this->authorizeBranchUserRecord($saleReturn);

        $returnLines = $saleReturn->products()->get();

        if ($returnLines->isEmpty() && (float) $saleReturn->gross_amount > 0.009) {
            return back()->with('error', 'Cannot cancel this sale return because product lines are missing.');
        }

        try {
            $this->assertStockAvailableForSaleReturnRollback($returnLines, (int) $saleReturn->sell_id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        try {
            DB::transaction(function () use ($saleReturn, $returnLines) {
                $this->accounting->reverseFor($saleReturn);
                $saleReturn->payments()->each(function (SaleReturnPayment $payment) {
                    $this->accounting->reverseFor($payment);
                });
                $this->rollbackSaleReturn($saleReturn, $returnLines);
                $saleReturn->products()->delete();
                $saleReturn->payments()->delete();
                $saleReturn->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Unable to delete sale return.');
        }

        return redirect()->route('inventory.sale-return.index')
            ->with('success', 'Sale return deleted successfully.');
    }

    /**
     * @param  Collection<int, SaleReturnProduct>  $returnLines
     */
    private function rollbackSaleReturn(SaleReturn $saleReturn, Collection $returnLines): void
    {
        $this->rollbackSaleReturnStock($saleReturn, $returnLines);
        $this->rollbackSaleReturnCustomerEffects($saleReturn);
    }

    /**
     * @param  Collection<int, SaleReturnProduct>  $returnLines
     */
    private function rollbackSaleReturnStock(SaleReturn $saleReturn, Collection $returnLines): void
    {
        $sellProducts = SellProduct::query()
            ->where('sell_id', $saleReturn->sell_id)
            ->get()
            ->keyBy('id');

        foreach ($returnLines as $line) {
            $qty = (float) $line->quantity;

            if ($qty <= 0) {
                continue;
            }

            $variationId = $line->variation_id
                ?? $sellProducts->get($line->sell_product_id)?->variation_id;

            if ($variationId) {
                $this->stock->deductVariation((int) $variationId, $qty);

                continue;
            }

            $batches = $line->batches ?? [];

            if ($batches !== []) {
                $this->stock->deductFromBatchMap(
                    $batches,
                    fn (Batch $batch, float $batchQty) => $batch->outStock($batchQty)
                );

                continue;
            }

            throw new \RuntimeException('Unable to reverse stock for a returned product. Insufficient stock or missing batch information.');
        }
    }

    /**
     * @param  Collection<int, SaleReturnProduct>  $returnLines
     */
    private function assertStockAvailableForSaleReturnRollback(Collection $returnLines, int $sellId): void
    {
        if ($returnLines->isEmpty()) {
            return;
        }

        $sellProducts = SellProduct::query()
            ->where('sell_id', $sellId)
            ->get()
            ->keyBy('id');

        foreach ($returnLines as $line) {
            $qty = (float) $line->quantity;

            if ($qty <= 0) {
                continue;
            }

            $variationId = $line->variation_id
                ?? $sellProducts->get($line->sell_product_id)?->variation_id;

            if ($variationId) {
                $variation = ProductVariation::query()->find($variationId);

                if ($variation === null || (float) $variation->stock < $qty) {
                    throw new \RuntimeException('Insufficient stock for variation.');
                }

                continue;
            }

            $batches = $line->batches ?? [];

            if ($batches !== []) {
                foreach ($batches as $batchId => $deductQty) {
                    $batch = Batch::query()->find($batchId);

                    if ($batch === null || (float) $batch->available < (float) $deductQty) {
                        throw new \RuntimeException('Insufficient stock in batch.');
                    }
                }

                continue;
            }

            throw new \RuntimeException('Unable to reverse stock for a returned product. Insufficient stock or missing batch information.');
        }
    }

    private function rollbackSaleReturnCustomerEffects(SaleReturn $saleReturn): void
    {
        if ($saleReturn->customer_id) {
            $this->rollbackSaleReturnCustomerBalance($saleReturn);
            $this->rollbackSaleReturnCustomerCoins($saleReturn);
        }
    }

    /**
     * @param  Collection<int, SaleReturnProduct>  $oldProducts
     * @param  array<int, array<string, mixed>>  $newLines
     */
    private function applySaleReturnStockDelta($oldProducts, array $newLines, Sell $parent): void
    {
        $newBySellProduct = collect($newLines)->keyBy('sell_product_id');
        $sellProductIds = $oldProducts->keys()->merge($newBySellProduct->keys())->unique();

        foreach ($sellProductIds as $sellProductId) {
            $oldLine = $oldProducts->get($sellProductId);
            $newLine = $newBySellProduct->get($sellProductId);
            $oldQty = $oldLine ? (float) $oldLine->quantity : 0.0;
            $newQty = $newLine ? (float) $newLine['quantity'] : 0.0;
            $delta = round($newQty - $oldQty, 2);

            if (abs($delta) < 0.001) {
                continue;
            }

            $sellProduct = $parent->products->firstWhere('id', (int) $sellProductId);

            if ($sellProduct === null) {
                throw new \RuntimeException('Invalid sale line.');
            }

            if ($delta > 0) {
                $this->restoreStockForReturnLine($sellProduct, $delta);

                continue;
            }

            $deductQty = abs($delta);

            if ($oldLine?->variation_id) {
                $this->stock->deductVariation((int) $oldLine->variation_id, $deductQty);
            } elseif (! empty($oldLine?->batches)) {
                $batchMap = $this->scaleBatchMapForReturn($oldLine->batches, $deductQty);
                $this->stock->deductFromBatchMap(
                    $batchMap,
                    fn (Batch $batch, float $batchQty) => $batch->outStock($batchQty)
                );
            } elseif ($sellProduct->variation_id) {
                $this->stock->deductVariation((int) $sellProduct->variation_id, $deductQty);
            } else {
                throw new \RuntimeException('Unable to adjust stock for a sale line.');
            }
        }
    }

    private function restoreStockForReturnLine(SellProduct $sellProduct, float $returnQty): void
    {
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
    }

    private function syncSaleReturnCustomerCoins(
        Sell $parent,
        SaleReturn $saleReturn,
        float $returnGross,
        float $returnLineDiscount,
        float $returnPromotionDiscount,
    ): void {
        $proportion = $this->returnDiscounts->returnProportion(
            $parent,
            $returnGross,
            $returnLineDiscount,
            $returnPromotionDiscount,
        );

        $this->coinService->reverseProportionalForSaleReturn($parent, $saleReturn, $proportion);
    }

    private function rollbackSaleReturnCustomerCoins(SaleReturn $saleReturn): void
    {
        $parent = Sell::query()->find($saleReturn->sell_id);

        if ($parent === null) {
            return;
        }

        $this->coinService->restoreForSaleReturn($parent, $saleReturn);
    }

    private function resolveSaleReturnBranchId(?int $branchId, Sell $parent): ?int
    {
        return $branchId ?? $parent->branch_id ?? $parent->customer?->branch_id;
    }

    private function syncSaleReturnCustomerBalance(
        int $customerId,
        float $netReturnAmount,
        ReceivedPaymentMethod $paymentType,
        float $paidAmount,
    ): void {
        $cashRefund = $paymentType === ReceivedPaymentMethod::Cash
            ? round(min($paidAmount, $netReturnAmount), 2)
            : 0.0;
        $dueReduction = round(max(0, $netReturnAmount - $cashRefund), 2);

        if ($dueReduction > 0) {
            Customer::whereKey($customerId)->decrement('balance', $dueReduction);
        }
    }

    private function rollbackSaleReturnCustomerBalance(SaleReturn $saleReturn): void
    {
        $netReturnAmount = (float) $saleReturn->net_amount;
        $paidAmount = (float) $saleReturn->paid_amount;
        $cashRefund = $saleReturn->payment_type === ReceivedPaymentMethod::Cash
            ? round(min($paidAmount, $netReturnAmount), 2)
            : 0.0;
        $dueReduction = round(max(0, $netReturnAmount - $cashRefund), 2);

        if ($dueReduction > 0) {
            Customer::whereKey($saleReturn->customer_id)->increment('balance', $dueReduction);
        }
    }

    /**
     * Resolve the invoice discount and round off values to persist so the edit screen can
     * restore exactly what was entered. Falls back to the computed amounts (as a flat value)
     * when manual inputs are absent, e.g. for non-UI callers.
     *
     * @param  array<string, mixed>  $data
     * @param  array{return_invoice_discount: float, return_round_off: float}  $totals
     * @return array{invoice_discount_type: string, invoice_discount_value: float, round_off_amount: float}
     */
    private function returnDiscountBreakdown(array $data, array $totals): array
    {
        return [
            'invoice_discount_type' => $data['manual_invoice_discount_type'] ?? 'flat',
            'invoice_discount_value' => isset($data['manual_invoice_discount_value'])
                ? (float) $data['manual_invoice_discount_value']
                : (float) $totals['return_invoice_discount'],
            'round_off_amount' => isset($data['manual_round_off'])
                ? (float) $data['manual_round_off']
                : (float) $totals['return_round_off'],
        ];
    }

    /**
     * Resolve the invoice discount / round off values to pre-fill the edit screen. Always sourced
     * from the sale return itself — never the parent sale. Returns saved values when present;
     * for legacy returns (saved before the breakdown was persisted) it reconstructs the editable
     * invoice-level discount from the return's own stored total, keeping the figures consistent
     * with what is recorded in the sale_returns table.
     *
     * @return array{invoice_discount_type: string, invoice_discount_value: float, round_off_amount: float}
     */
    private function resolveEditBreakdown(SaleReturn $saleReturn, Sell $parent): array
    {
        if ($saleReturn->invoice_discount_value !== null || $saleReturn->round_off_amount !== null) {
            return [
                'invoice_discount_type' => $saleReturn->invoice_discount_type ?? 'flat',
                'invoice_discount_value' => (float) $saleReturn->invoice_discount_value,
                'round_off_amount' => (float) $saleReturn->round_off_amount,
            ];
        }

        $returnLineDiscount = 0.0;
        $returnPromotionDiscount = 0.0;

        foreach ($saleReturn->products as $line) {
            $sellProduct = $parent->products->firstWhere('id', $line->sell_product_id);

            if (! $sellProduct) {
                continue;
            }

            $soldQty = (float) $sellProduct->quantity;
            $returnQty = (float) $line->quantity;

            if ($soldQty > 0) {
                $returnLineDiscount += (float) $sellProduct->discount * ($returnQty / $soldQty);
                $returnPromotionDiscount += $this->returnDiscounts->promotionClawback($sellProduct, $returnQty, $parent);
            }
        }

        $invoiceLevel = max(0, (float) $saleReturn->discount_amount - $returnLineDiscount - $returnPromotionDiscount);

        return [
            'invoice_discount_type' => 'flat',
            'invoice_discount_value' => round($invoiceLevel, 2),
            'round_off_amount' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     gross_amount: float,
     *     return_line_discount: float,
     *     return_promotion_discount: float
     * }
     */
    private function buildSaleReturnLines(Sell $parent, SaleReturn $saleReturn, array $data, ?int $branchId): array
    {
        $returnedByLine = $this->returnedQuantities($parent->id, $saleReturn->id);
        $grossAmount = 0.0;
        $returnLineDiscount = 0.0;
        $returnPromotionDiscount = 0.0;
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
            $catalogUnitPrice = (float) ($sellProduct->original_unit_price ?? $sellProduct->unit_price);
            $grossAmount += $returnQty * $catalogUnitPrice;

            $soldQty = (float) $sellProduct->quantity;
            if ($soldQty > 0) {
                $ratio = $returnQty / $soldQty;
                $returnLineDiscount += (float) $sellProduct->discount * $ratio;
                $returnPromotionDiscount += $this->returnDiscounts->promotionClawback(
                    $sellProduct,
                    $returnQty,
                    $parent,
                );
            }

            $lines[] = [
                'branch_id' => $branchId,
                'sell_product_id' => $sellProduct->id,
                'product_id' => $sellProduct->product_id,
                'variation_id' => $sellProduct->variation_id,
                'quantity' => $returnQty,
                'unit_price' => $catalogUnitPrice,
                'batches' => $batchMap,
            ];
        }

        if ($lines === []) {
            throw new \RuntimeException('At least one line with return quantity greater than zero is required.');
        }

        return [
            'lines' => $lines,
            'gross_amount' => $grossAmount,
            'return_line_discount' => $returnLineDiscount,
            'return_promotion_discount' => $returnPromotionDiscount,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function isPaymentOnlySaleReturnUpdate(SaleReturn $saleReturn, array $data): bool
    {
        if ($saleReturn->products->isEmpty()) {
            return false;
        }

        $submitted = collect($data['items'])->mapWithKeys(
            fn (array $item) => [(int) $item['sell_product_id'] => (int) $item['quantity']]
        );

        $existing = $saleReturn->products->mapWithKeys(
            fn (SaleReturnProduct $line) => [(int) $line->sell_product_id => (int) $line->quantity]
        );

        if ($submitted->count() !== $existing->count()) {
            return false;
        }

        foreach ($existing as $sellProductId => $quantity) {
            if ((int) ($submitted[$sellProductId] ?? -1) !== $quantity) {
                return false;
            }
        }

        $parent = $saleReturn->sell ?? Sell::query()->find($saleReturn->sell_id);

        if ($parent === null) {
            return false;
        }

        $breakdown = $this->resolveEditBreakdown($saleReturn, $parent);
        $manualType = $data['manual_invoice_discount_type'] ?? 'flat';
        $manualInvoice = (float) ($data['manual_invoice_discount_value'] ?? 0);
        $manualRound = (float) ($data['manual_round_off'] ?? 0);
        $manualVat = (float) ($data['manual_vat_percent'] ?? 0);

        if ($manualType !== ($breakdown['invoice_discount_type'] ?? 'flat')) {
            return false;
        }

        if (abs($manualInvoice - (float) $breakdown['invoice_discount_value']) > 0.009) {
            return false;
        }

        if (abs($manualRound - (float) $breakdown['round_off_amount']) > 0.009) {
            return false;
        }

        if (abs($manualVat - (float) $saleReturn->vat_percent) > 0.009) {
            return false;
        }

        return true;
    }

    private function rollbackIncrementalRefundCustomerBalance(SaleReturn $saleReturn): void
    {
        if ($saleReturn->customer_id === null) {
            return;
        }

        $saleReturn->loadMissing('payments');

        foreach ($saleReturn->payments as $payment) {
            $amount = round((float) $payment->amount, 2);

            if ($amount > 0) {
                Customer::whereKey($saleReturn->customer_id)->decrement('balance', $amount);
            }
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
