<?php

namespace App\Http\Controllers\Inventory;

use App\Concerns\ExportsFilteredList;
use App\Enums\ProductLogType;
use App\Enums\StockAdjustmentType;
use App\Http\Controllers\Concerns\AuthorizesBranchUserRecords;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockAdjustment;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\InventoryStockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StockAdjustmentController extends Controller
{
    use AuthorizesBranchUserRecords;
    use ExportsFilteredList;

    public function __construct(
        private InventoryStockService $stock,
        private InventoryAccountingService $accounting,
        private InventoryCostService $costService,
    ) {}

    private function listQuery(Request $request): Builder
    {
        return $this->applyDateColumnFilters(
            StockAdjustment::query()->ownBranchUser()
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('invoice_sequence', 'like', "%{$s}%")
                        ->orWhere('serial', 'like', "%{$s}%")
                        ->orWhere('id', 'like', "%{$s}%")
                        ->orWhere('comment', 'like', "%{$s}%")
                        ->orWhere('type', 'like', "%{$s}%");
                })),
            $request,
            'date',
        )->latest();
    }

    /**
     * @return Collection<int, list<string|int|float>>
     */
    private function exportRows(Request $request): Collection
    {
        return $this->listQuery($request)
            ->limit(self::LIST_EXPORT_LIMIT)
            ->get()
            ->values()
            ->map(fn (StockAdjustment $adjustment, int $index): array => [
                $index + 1,
                $adjustment->invoice_number,
                optional($adjustment->date)?->format('Y-m-d') ?? '—',
                $adjustment->type?->label() ?? '—',
                $adjustment->comment ?: '—',
            ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('inventory.stock-adjustment.view');

        $adjustments = $this->listQuery($request)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/stock-adjustment/index', [
            'adjustments' => $adjustments,
            'filters' => $request->only('search', 'date_from', 'date_to'),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize('inventory.stock-adjustment.view');

        return $this->downloadListExcel(
            'stock-adjustments',
            ['#', 'Invoice', 'Date', 'Type', 'Note'],
            $this->exportRows($request),
        );
    }

    public function exportPdf(Request $request): SymfonyResponse
    {
        $this->authorize('inventory.stock-adjustment.view');

        return $this->downloadListPdf(
            'Stock Adjustment',
            ['#', 'Invoice', 'Date', 'Type', 'Note'],
            $this->exportRows($request),
        );
    }

    public function exportPrint(Request $request): SymfonyResponse
    {
        $this->authorize('inventory.stock-adjustment.view');

        return $this->printListHtml(
            'Stock Adjustment',
            ['#', 'Invoice', 'Date', 'Type', 'Note'],
            $this->exportRows($request),
        );
    }

    public function create(): Response
    {
        $this->authorize('inventory.stock-adjustment.create');

        return Inertia::render('admin/inventory/stock-adjustment/create', [
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.stock-adjustment.create');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', Rule::enum(StockAdjustmentType::class)],
            'comment' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $type = StockAdjustmentType::from($data['type']);
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return back()
                ->withErrors(['items' => 'Stock adjustment is only available for branch users.'])
                ->withInput();
        }

        try {
            DB::transaction(function () use ($data, $type, $branchId) {
                $lines = [];

                foreach ($data['items'] as $item) {
                    $qty = (float) $item['quantity'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $productId = (int) $item['product_id'];
                    $variationId = ! empty($item['variation_id']) ? (int) $item['variation_id'] : null;
                    $unitCost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : 0.0;
                    $batchMap = [];

                    $product = Product::query()
                        ->whereKey($productId)
                        ->where('branch_id', $branchId)
                        ->first();

                    if ($product === null) {
                        throw new \RuntimeException('One or more products are not available in your branch.');
                    }

                    if ($product->hasVariationsAtBranch($branchId) && $variationId === null) {
                        throw new \RuntimeException("Select a size or variation for {$product->name}.");
                    }

                    if ($variationId !== null) {
                        $variationExists = $product->variationsAtBranch($branchId)
                            ->whereKey($variationId)
                            ->exists();

                        if (! $variationExists) {
                            throw new \RuntimeException("The selected variation is not valid for {$product->name}.");
                        }
                    }

                    if ($unitCost <= 0) {
                        $unitCost = $this->resolveUnitCost($productId, $variationId);
                    }

                    if ($type === StockAdjustmentType::Decrease) {
                        if ($variationId) {
                            $this->stock->deductVariation($variationId, $qty, ProductLogType::Adjustment_Out);
                        } else {
                            $batchMap = $this->stock->deductFifo(
                                $branchId,
                                $productId,
                                $qty,
                                fn (Batch $batch, float $deductQty) => $batch->adjustmentStock(-$deductQty),
                            );
                        }
                    } else {
                        if ($variationId) {
                            $this->stock->restoreVariation($variationId, $qty, ProductLogType::Adjustment_In);
                        } else {
                            $batchMap = $this->stock->addToProductBatch(
                                $branchId,
                                $productId,
                                $qty,
                                $unitCost,
                                fn (Batch $batch, float $addQty) => $batch->adjustmentStock($addQty),
                            );
                        }
                    }

                    $lines[] = [
                        'branch_id' => $branchId,
                        'product_id' => $productId,
                        'variation_id' => $variationId,
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'batches' => $batchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with quantity greater than zero is required.');
                }

                $adjustment = StockAdjustment::create([
                    'branch_id' => $branchId,
                    'user_id' => $this->currentUserId(),
                    'date' => $data['date'],
                    'type' => $type,
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $adjustment->products()->create($line);
                }

                $adjustment->load('products');
                $this->accounting->postStockAdjustment($adjustment);
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to record stock adjustment.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.stock-adjustment.index')
            ->with('success', 'Stock adjustment recorded successfully.');
    }

    public function show(StockAdjustment $stockAdjustment): Response
    {
        $this->authorize('inventory.stock-adjustment.view');
        $this->authorizeBranchUserRecord($stockAdjustment);

        $stockAdjustment->load(['products.product', 'products.variation']);

        return Inertia::render('admin/inventory/stock-adjustment/show', [
            'adjustment' => $stockAdjustment,
        ]);
    }

    public function destroy(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('inventory.stock-adjustment.delete');
        $this->authorizeBranchUserRecord($stockAdjustment);

        try {
            DB::transaction(function () use ($stockAdjustment) {
                $this->accounting->reverseFor($stockAdjustment);
                $this->rollbackAdjustment($stockAdjustment);
                $stockAdjustment->products()->delete();
                $stockAdjustment->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to delete stock adjustment.');
        }

        return redirect()->route('inventory.stock-adjustment.index')
            ->with('success', 'Stock adjustment deleted successfully.');
    }

    private function resolveUnitCost(int $productId, ?int $variationId): float
    {
        if ($variationId) {
            return (float) (ProductVariation::query()->find($variationId)?->purchase_price ?? 0);
        }

        return (float) (Product::query()->find($productId)?->purchase_price ?? 0);
    }

    private function rollbackAdjustment(StockAdjustment $adjustment): void
    {
        $adjustment->loadMissing('products');

        foreach ($adjustment->products as $line) {
            $qty = (float) $line->quantity;
            $variationId = $line->variation_id ? (int) $line->variation_id : null;
            $batchMap = is_array($line->batches) ? $line->batches : [];

            if ($adjustment->isIncrease()) {
                if ($variationId) {
                    $this->stock->deductVariation($variationId, $qty, ProductLogType::Adjustment_Out);
                } elseif ($batchMap !== []) {
                    $this->stock->deductFromBatchMap(
                        $batchMap,
                        fn (Batch $batch, float $deductQty) => $batch->adjustmentStock(-$deductQty),
                    );
                }
            } else {
                if ($variationId) {
                    $this->stock->restoreVariation($variationId, $qty, ProductLogType::Adjustment_In);
                } elseif ($batchMap !== []) {
                    $this->stock->restoreFromBatchMap(
                        $batchMap,
                        fn (Batch $batch, float $addQty) => $batch->adjustmentStock($addQty),
                    );
                }
            }
        }
    }
}
