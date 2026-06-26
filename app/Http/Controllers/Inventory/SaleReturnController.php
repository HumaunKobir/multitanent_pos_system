<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ReceivedPaymentMethod;
use App\Http\Controllers\Concerns\AuthorizesBranchUserRecords;
use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Services\CoinService;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\InventoryStockService;
use App\Services\PromotionService;
use App\Services\SaleReturnDiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'specialDiscounts' => $this->activeSpecialDiscounts(Auth::user()?->branch_id),
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
            'manual_invoice_discount' => ['nullable', 'numeric', 'min:0'],
            'manual_special_discount' => ['nullable', 'numeric', 'min:0'],
            'manual_round_off' => ['nullable', 'numeric', 'min:0'],
            'manual_coin_discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($request, $data, $branchId) {
                $parent = Sell::query()
                    ->ownBranchUser()
                    ->sale()
                    ->with(['products', 'customer'])
                    ->lockForUpdate()
                    ->findOrFail($data['sell_id']);

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
                    isset($data['manual_invoice_discount']) ? (float) $data['manual_invoice_discount'] : null,
                    isset($data['manual_special_discount']) ? (float) $data['manual_special_discount'] : null,
                    isset($data['manual_round_off']) ? (float) $data['manual_round_off'] : null,
                    isset($data['manual_coin_discount']) ? (float) $data['manual_coin_discount'] : null,
                );
                $discountAmount = $totals['discount_amount'];
                $netReturnAmount = $totals['net_return_amount'];
                $refund = $this->resolveReturnRefund($data, $request, $netReturnAmount);
                $paidAmount = $refund['paid_amount'];
                $paymentType = $refund['payment_type'];
                $paymentAccountId = $refund['payment_account_id'];
                $paymentLines = $refund['payment_lines'];

                $saleReturn = SaleReturn::create([
                    'branch_id' => $branchId,
                    'user_id' => $this->currentUserId(),
                    'sell_id' => $parent->id,
                    'customer_id' => $parent->customer_id,
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount_amount' => $discountAmount,
                    'paid_amount' => $paidAmount,
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $saleReturn->products()->create($line);
                }

                if ($parent->customer_id && $paymentType === ReceivedPaymentMethod::Customer_Account && $paidAmount > 0) {
                    Customer::whereKey($parent->customer_id)->increment('balance', $paidAmount);
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
        $this->authorizeBranchUserRecord($saleReturn);

        $saleReturn->load(['customer', 'sell', 'products.product', 'payments']);

        $parent = Sell::query()
            ->ownBranchUser()
            ->sale()
            ->with(['products.product:id,name,code,category_id,brand_id', 'payments'])
            ->findOrFail($saleReturn->sell_id);

        $returnedByLine = $this->returnedQuantities($parent->id, $saleReturn->id);
        $linesOnReturn = $saleReturn->products->keyBy('sell_product_id');

        $promotionIds = $parent->products->pluck('promotion_id')->filter()->unique()->values()->all();
        $promotionMap = $promotionIds !== []
            ? Promotion::whereIn('id', $promotionIds)->get()->keyBy('id')
            : collect();

        $items = $parent->products
            ->map(function ($sp) use ($returnedByLine, $linesOnReturn, $promotionMap) {
                $maxReturn = max(0, (float) $sp->quantity - ($returnedByLine[$sp->id] ?? 0));
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

                return [
                    'sell_product_id' => $sp->id,
                    'product_id' => $sp->product_id,
                    'variation_id' => $sp->variation_id,
                    'category_id' => $sp->product?->category_id,
                    'brand_id' => $sp->product?->brand_id,
                    'product_name' => $sp->product?->name,
                    'product_code' => $sp->product?->code,
                    'unit_price' => (float) ($sp->original_unit_price ?? $sp->unit_price),
                    'line_discount' => (float) $sp->discount,
                    'promotion_discount' => (float) $sp->promotion_discount,
                    'promotion_id' => $sp->promotion_id,
                    'promotion_details' => $promotionDetails,
                    'sold_quantity' => (float) $sp->quantity,
                    'max_return_quantity' => (int) $maxReturn,
                    'quantity' => $current ? (string) (int) $current->quantity : '0',
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('admin/inventory/sale-return/edit', [
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccounts(),
            'specialDiscounts' => $this->activeSpecialDiscounts($parent->branch_id),
            'saleReturn' => [
                'id' => $saleReturn->id,
                'sell_id' => $saleReturn->sell_id,
                'sale_invoice' => $saleReturn->sell?->invoice_number,
                'customer_name' => $saleReturn->customer?->name,
                'date' => optional($saleReturn->date)->format('Y-m-d'),
                'comment' => $saleReturn->comment,
                'paid_amount' => (string) $saleReturn->paid_amount,
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
                'items' => $items,
                'sell_discounts' => [
                    'gross_amount' => (float) $parent->gross_amount,
                    'line_discount_total' => $parent->lineDiscountTotal(),
                    'invoice_discount' => (float) $parent->discount,
                    'special_discount_id' => $parent->special_discount_id,
                    'special_discount_amount' => (float) $parent->special_discount_amount,
                    'promotion_discount_total' => (float) $parent->promotion_discount_total,
                    'coin_discount_amount' => (float) $parent->coin_discount_amount,
                    'round_off_amount' => (float) $parent->round_off_amount,
                    'net_amount' => (float) $parent->net_amount,
                    'paid_amount' => (float) $parent->paid_amount,
                    'coins_redeemed' => (float) $parent->coins_redeemed,
                ],
                'sale_date' => optional($parent->date)->format('Y-m-d'),
                'coin_settings' => $this->coinService->settingsPayloadForBranch($parent->branch_id),
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
            'manual_invoice_discount' => ['nullable', 'numeric', 'min:0'],
            'manual_special_discount' => ['nullable', 'numeric', 'min:0'],
            'manual_round_off' => ['nullable', 'numeric', 'min:0'],
            'manual_coin_discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($request, $saleReturn, $data, $branchId) {
                $this->accounting->reverseFor($saleReturn);
                $saleReturn->load(['products']);

                $this->rollbackSaleReturn($saleReturn);
                $saleReturn->products()->delete();
                $saleReturn->payments()->delete();

                $parent = Sell::query()
                    ->ownBranchUser()
                    ->sale()
                    ->with(['products', 'customer'])
                    ->lockForUpdate()
                    ->findOrFail($saleReturn->sell_id);

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
                    isset($data['manual_invoice_discount']) ? (float) $data['manual_invoice_discount'] : null,
                    isset($data['manual_special_discount']) ? (float) $data['manual_special_discount'] : null,
                    isset($data['manual_round_off']) ? (float) $data['manual_round_off'] : null,
                    isset($data['manual_coin_discount']) ? (float) $data['manual_coin_discount'] : null,
                );
                $discountAmount = $totals['discount_amount'];
                $netReturnAmount = $totals['net_return_amount'];
                $refund = $this->resolveReturnRefund($data, $request, $netReturnAmount);
                $paidAmount = $refund['paid_amount'];
                $paymentType = $refund['payment_type'];
                $paymentAccountId = $refund['payment_account_id'];
                $paymentLines = $refund['payment_lines'];

                $saleReturn->update([
                    'date' => $data['date'],
                    'gross_amount' => $grossAmount,
                    'discount_amount' => $discountAmount,
                    'paid_amount' => $paidAmount,
                    'payment_type' => $paymentType,
                    'payment_account_id' => $paymentAccountId,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $saleReturn->products()->create($line);
                }

                if ($parent->customer_id && $paymentType === ReceivedPaymentMethod::Customer_Account && $paidAmount > 0) {
                    Customer::whereKey($parent->customer_id)->increment('balance', $paidAmount);
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

    public function destroy(SaleReturn $saleReturn): RedirectResponse
    {
        $this->authorizeBranchUserRecord($saleReturn);

        $saleReturn->load(['products']);

        try {
            DB::transaction(function () use ($saleReturn) {
                $this->accounting->reverseFor($saleReturn);
                $this->rollbackSaleReturn($saleReturn);
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

    /** @return array<int, array<string, mixed>> */
    private function activeSpecialDiscounts(?int $branchId): array
    {
        return SpecialDiscount::query()
            ->active()
            ->when($branchId, fn ($q, $b) => $q->accessibleAtBranch($b))
            ->orderBy('min_amount')
            ->get(['id', 'name', 'min_amount', 'max_amount', 'discount_type', 'discount_value'])
            ->map(fn (SpecialDiscount $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'min_amount' => (float) $d->min_amount,
                'max_amount' => $d->max_amount !== null ? (float) $d->max_amount : null,
                'discount_type' => $d->discount_type->value,
                'discount_value' => (float) $d->discount_value,
            ])
            ->values()
            ->all();
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
