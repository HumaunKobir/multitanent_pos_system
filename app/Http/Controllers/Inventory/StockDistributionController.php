<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockDistribution;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\StockDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockDistributionController extends Controller
{
    public function __construct(
        private StockDistributionService $distribution,
        private InventoryAccountingService $accounting,
        private InventoryCostService $costService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('inventory.stock-distribution.view');

        $user = Auth::user();
        $branchId = $user?->branch_id;

        $distributions = $this->distributionQueryForUser($user)
            ->with('toBranch:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('serial', 'like', "%{$s}%")
                    ->orWhere('comment', 'like', "%{$s}%")
                    ->orWhereHas('toBranch', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/stock-distribution/index', [
            'distributions' => $distributions,
            'filters' => $request->only('search'),
            'canManage' => $this->canManageDistributions($user),
            'isReceiverView' => $branchId !== null && ! Branch::isMainBranch($branchId),
        ]);
    }

    public function create(): Response
    {
        $this->authorizeMainBranchManager();
        $this->authorize('inventory.stock-distribution.create');

        return Inertia::render('admin/inventory/stock-distribution/create', [
            'today' => now()->format('Y-m-d'),
            'branches' => Branch::query()
                ->operating()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMainBranchManager();
        $this->authorize('inventory.stock-distribution.create');

        $data = $this->validatedDistributionData($request);
        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($data, $branchId) {
                $distribution = $this->persistDistribution($data, $branchId);
                $this->accounting->postStockDistribution(
                    $distribution,
                    $this->costService->costForStockDistribution($distribution),
                );
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to distribute stock.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.stock-distribution.index')
            ->with('success', 'Stock distributed successfully.');
    }

    public function show(StockDistribution $stockDistribution): Response
    {
        $this->authorize('inventory.stock-distribution.view');
        $this->authorizeDistributionAccess($stockDistribution);

        $stockDistribution->load(['products.product', 'products.variation', 'toBranch:id,name', 'fromBranch:id,name']);

        return Inertia::render('admin/inventory/stock-distribution/show', [
            'distribution' => [
                'id' => $stockDistribution->id,
                'invoice_number' => $stockDistribution->invoice_number,
                'date' => optional($stockDistribution->date)->format('Y-m-d'),
                'comment' => $stockDistribution->comment,
                'from_branch' => $stockDistribution->fromBranch,
                'to_branch' => $stockDistribution->toBranch,
                'products' => $stockDistribution->products->map(function ($line) {
                    $qty = (float) $line->quantity;
                    $mainStockBefore = $line->main_stock_before !== null
                        ? (float) $line->main_stock_before
                        : $this->distribution->mainWarehouseStock(
                            (int) $line->product_id,
                            $line->variation_id ? (int) $line->variation_id : null,
                        ) + $qty;

                    return [
                        'id' => $line->id,
                        'quantity' => $qty,
                        'main_stock_before' => $mainStockBefore,
                        'main_stock_after' => $mainStockBefore - $qty,
                        'product' => $line->product,
                        'variation' => $line->variation,
                    ];
                })->values(),
            ],
            'canManage' => $this->canManageDistributions(Auth::user()),
        ]);
    }

    public function edit(StockDistribution $stockDistribution): Response
    {
        $this->authorizeMainBranchManager();
        $this->authorize('inventory.stock-distribution.update');
        $this->authorizeDistributionAccess($stockDistribution, write: true);

        $stockDistribution->load(['products.product', 'products.variation']);

        return Inertia::render('admin/inventory/stock-distribution/edit', [
            'today' => now()->format('Y-m-d'),
            'branches' => Branch::query()
                ->operating()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'distribution' => [
                'id' => $stockDistribution->id,
                'invoice_number' => $stockDistribution->invoice_number,
                'to_branch_id' => (string) $stockDistribution->to_branch_id,
                'date' => optional($stockDistribution->date)->format('Y-m-d'),
                'comment' => $stockDistribution->comment,
                'items' => $stockDistribution->products->map(function ($line) {
                    $lineQty = (float) $line->quantity;
                    $mainStock = $this->distribution->mainWarehouseStock(
                        (int) $line->product_id,
                        $line->variation_id ? (int) $line->variation_id : null,
                    );

                    return [
                        'product_id' => $line->product_id,
                        'variation_id' => $line->variation_id,
                        'product_name' => $line->product?->name,
                        'variation_label' => $line->variation?->variation_data['label'] ?? null,
                        'quantity' => (string) (int) $line->quantity,
                        'available_stock' => $mainStock,
                        'max_quantity' => $mainStock + $lineQty,
                    ];
                })->values(),
            ],
        ]);
    }

    public function update(Request $request, StockDistribution $stockDistribution): RedirectResponse
    {
        $this->authorizeMainBranchManager();
        $this->authorize('inventory.stock-distribution.update');
        $this->authorizeDistributionAccess($stockDistribution, write: true);

        $data = $this->validatedDistributionData($request);
        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($stockDistribution, $data, $branchId) {
                $this->accounting->reverseFor($stockDistribution);
                $stockDistribution->load(['products']);
                $this->distribution->rollbackDistribution($stockDistribution);
                $stockDistribution->products()->delete();

                $stockDistribution->update([
                    'to_branch_id' => (int) $data['to_branch_id'],
                    'date' => $data['date'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($this->buildProductLines($data, $branchId) as $line) {
                    $stockDistribution->products()->create($line);
                }

                $stockDistribution->load('products');
                $this->accounting->postStockDistribution(
                    $stockDistribution,
                    $this->costService->costForStockDistribution($stockDistribution),
                );
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update distribution.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.stock-distribution.index')
            ->with('success', 'Distribution updated successfully.');
    }

    public function destroy(StockDistribution $stockDistribution): RedirectResponse
    {
        $this->authorizeMainBranchManager();
        $this->authorize('inventory.stock-distribution.delete');
        $this->authorizeDistributionAccess($stockDistribution, write: true);

        $stockDistribution->load(['products']);

        try {
            DB::transaction(function () use ($stockDistribution) {
                $this->accounting->reverseFor($stockDistribution);
                $this->distribution->rollbackDistribution($stockDistribution);
                $stockDistribution->products()->delete();
                $stockDistribution->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to delete distribution.');
        }

        return redirect()->route('inventory.stock-distribution.index')
            ->with('success', 'Distribution deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDistributionData(Request $request): array
    {
        $operatingBranchIds = Branch::query()->operating()->active()->pluck('id')->all();

        return $request->validate([
            'to_branch_id' => ['required', 'integer', Rule::in($operatingBranchIds)],
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistDistribution(array $data, ?int $branchId): StockDistribution
    {
        $distribution = StockDistribution::create([
            'branch_id' => $branchId,
            'from_branch_id' => Branch::MAIN_BRANCH_ID,
            'to_branch_id' => (int) $data['to_branch_id'],
            'date' => $data['date'],
            'comment' => $data['comment'] ?? null,
            'serial' => 'INVT'.str_pad((string) (StockDistribution::max('id') + 1), 8, '0', STR_PAD_LEFT),
        ]);

        foreach ($this->buildProductLines($data, $branchId) as $line) {
            $distribution->products()->create($line);
        }

        return $distribution->load('products');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function buildProductLines(array $data, ?int $branchId): array
    {
        $lines = [];

        foreach ($data['items'] as $item) {
            $qty = (float) $item['quantity'];

            if ($qty <= 0) {
                continue;
            }

            $productId = (int) $item['product_id'];
            $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;
            $mainStockBefore = $this->distribution->mainWarehouseStock($productId, $variationId);

            if ($mainStockBefore < $qty) {
                throw new \RuntimeException('Quantity exceeds available main branch stock.');
            }

            $batchMaps = $this->distribution->distributeLine(
                (int) $data['to_branch_id'],
                $productId,
                $variationId,
                $qty,
            );

            $lines[] = [
                'branch_id' => $branchId,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $qty,
                'main_stock_before' => $mainStockBefore,
                'source_batches' => $batchMaps['source_batches'],
                'destination_batches' => $batchMaps['destination_batches'],
            ];
        }

        if ($lines === []) {
            throw new \RuntimeException('At least one line with quantity greater than zero is required.');
        }

        return $lines;
    }

    private function distributionQueryForUser($user)
    {
        $branchId = $user?->branch_id;

        if ($user?->isSuperAdmin()) {
            return StockDistribution::query();
        }

        if (Branch::isMainBranch($branchId)) {
            return StockDistribution::query()->where('branch_id', $branchId);
        }

        if ($branchId !== null) {
            return StockDistribution::query()->where('to_branch_id', $branchId);
        }

        return StockDistribution::query()->whereRaw('1 = 0');
    }

    private function canManageDistributions($user): bool
    {
        return $user !== null
            && ($user->isSuperAdmin() || Branch::isMainBranch($user->branch_id));
    }

    private function authorizeMainBranchManager(): void
    {
        abort_unless($this->canManageDistributions(Auth::user()), 403);
    }

    private function authorizeDistributionAccess(StockDistribution $distribution, bool $write = false): void
    {
        $user = Auth::user();
        $branchId = $user?->branch_id;

        if ($user?->isSuperAdmin()) {
            return;
        }

        if ($write) {
            if (! Branch::isMainBranch($branchId) || $distribution->branch_id !== $branchId) {
                abort(404);
            }

            return;
        }

        $canViewOutgoing = Branch::isMainBranch($branchId) && $distribution->branch_id === $branchId;
        $canViewIncoming = $branchId !== null && (int) $distribution->to_branch_id === $branchId;

        if (! $canViewOutgoing && ! $canViewIncoming) {
            abort(404);
        }
    }
}
