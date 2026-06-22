<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\StockDistributionStatus;
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
        $this->authorizeAdminPanelOnly();

        $user = Auth::user();

        $distributions = $this->distributionQueryForUser($user)
            ->with(['toBranch:id,name', 'receivedBy:id,name'])
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
            'isReceiverView' => false,
        ]);
    }

    public function receivedIndex(Request $request): Response
    {
        $this->authorize('inventory.stock-distribution.receive');
        $this->authorizeBranchReceiverOnly();

        $user = Auth::user();

        $distributions = StockDistribution::query()
            ->where('to_branch_id', $user?->branch_id)
            ->with(['fromBranch:id,name', 'receivedBy:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('serial', 'like', "%{$s}%")
                    ->orWhere('comment', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/stock-distribution/index', [
            'distributions' => $distributions,
            'filters' => $request->only('search'),
            'canManage' => false,
            'isReceiverView' => true,
        ]);
    }

    public function create(): Response
    {
        $this->authorizeMainBranchManager();
        $this->authorize('inventory.stock-distribution.create');

        return Inertia::render('admin/inventory/stock-distribution/create', [
            'today' => now()->format('Y-m-d'),
            'branches' => $this->operatingBranches(),
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
                $this->distribution->createPendingDistribution($data, $branchId);
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
            ->with('success', 'Stock distribution created and pending branch receipt.');
    }

    public function show(StockDistribution $stockDistribution): Response
    {
        $user = Auth::user();

        if ($user?->usesBranchPanel()) {
            $this->authorize('inventory.stock-distribution.receive');
        } else {
            $this->authorize('inventory.stock-distribution.view');
        }

        $this->authorizeDistributionAccess($stockDistribution);

        $stockDistribution->load([
            'products.product',
            'products.variation',
            'toBranch:id,name',
            'fromBranch:id,name',
            'receivedBy:id,name',
            'purchase:id,serial',
        ]);

        $user = Auth::user();

        return Inertia::render('admin/inventory/stock-distribution/show', [
            'distribution' => $this->formatDistribution($stockDistribution),
            'canManage' => $this->canManageDistributions($user),
            'canReceive' => $this->canReceiveDistribution($user, $stockDistribution),
        ]);
    }

    public function receive(StockDistribution $stockDistribution): RedirectResponse
    {
        $this->authorize('inventory.stock-distribution.receive');
        $this->authorizeBranchReceiverOnly();
        $this->authorizeDistributionAccess($stockDistribution);

        abort_unless($stockDistribution->isPending(), 422, 'This distribution has already been received.');
        abort_unless(
            (int) $stockDistribution->to_branch_id === (int) Auth::user()?->branch_id,
            403,
        );

        try {
            DB::transaction(function () use ($stockDistribution) {
                $this->distribution->receiveDistribution($stockDistribution);
                $stockDistribution->update([
                    'status' => StockDistributionStatus::Received,
                    'received_at' => now(),
                    'received_by_user_id' => Auth::id(),
                ]);

                $stockDistribution->load('products');
                $this->accounting->postStockDistribution(
                    $stockDistribution,
                    $this->costService->costForStockDistribution($stockDistribution),
                );
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to receive stock.');
        }

        return redirect()
            ->route('inventory.stock-distribution.received')
            ->with('success', 'Stock received successfully.');
    }

    public function edit(StockDistribution $stockDistribution): Response
    {
        $this->authorizeMainBranchManager();
        $this->authorize('inventory.stock-distribution.update');
        $this->authorizeDistributionAccess($stockDistribution, write: true);
        abort_unless($stockDistribution->isPending(), 403, 'Received distributions cannot be edited.');

        $stockDistribution->load(['products.product', 'products.variation']);

        return Inertia::render('admin/inventory/stock-distribution/edit', [
            'today' => now()->format('Y-m-d'),
            'branches' => $this->operatingBranches(),
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
        abort_unless($stockDistribution->isPending(), 403, 'Received distributions cannot be updated.');

        $data = $this->validatedDistributionData($request);
        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($stockDistribution, $data, $branchId) {
                $stockDistribution->load(['products']);
                $this->distribution->rollbackDistribution($stockDistribution);
                $stockDistribution->products()->delete();

                $stockDistribution->update([
                    'to_branch_id' => (int) $data['to_branch_id'],
                    'date' => $data['date'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($this->distribution->buildPendingProductLines($data, $branchId) as $line) {
                    $stockDistribution->products()->create($line);
                }
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
        abort_unless($stockDistribution->isPending(), 403, 'Received distributions cannot be deleted.');

        $stockDistribution->load(['products']);

        try {
            DB::transaction(function () use ($stockDistribution) {
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
    private function formatDistribution(StockDistribution $stockDistribution): array
    {
        return [
            'id' => $stockDistribution->id,
            'invoice_number' => $stockDistribution->invoice_number,
            'date' => optional($stockDistribution->date)->format('Y-m-d'),
            'comment' => $stockDistribution->comment,
            'status' => $stockDistribution->status?->value,
            'status_label' => $stockDistribution->status?->label(),
            'received_at' => optional($stockDistribution->received_at)?->toIso8601String(),
            'received_by' => $stockDistribution->receivedBy,
            'purchase' => $stockDistribution->purchase ? [
                'id' => $stockDistribution->purchase->id,
                'invoice_number' => $stockDistribution->purchase->invoice_number,
            ] : null,
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
        ];
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
     * @return list<array{id: int, name: string}>
     */
    private function operatingBranches(): array
    {
        return Branch::query()
            ->operating()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    private function distributionQueryForUser($user)
    {
        if ($user?->usesAdminPanel()) {
            return StockDistribution::query();
        }

        return StockDistribution::query()->whereRaw('1 = 0');
    }

    private function canManageDistributions($user): bool
    {
        return $user !== null && $user->usesAdminPanel();
    }

    private function canReceiveDistribution($user, StockDistribution $distribution): bool
    {
        return $user !== null
            && $user->usesBranchPanel()
            && $user->can('inventory.stock-distribution.receive')
            && $distribution->isPending()
            && (int) $distribution->to_branch_id === (int) $user->branch_id;
    }

    private function authorizeMainBranchManager(): void
    {
        abort_unless($this->canManageDistributions(Auth::user()), 403);
    }

    private function authorizeAdminPanelOnly(): void
    {
        abort_unless(Auth::user()?->usesAdminPanel(), 404);
    }

    private function authorizeBranchReceiverOnly(): void
    {
        abort_unless(Auth::user()?->usesBranchPanel(), 404);
    }

    private function authorizeDistributionAccess(StockDistribution $distribution, bool $write = false): void
    {
        $user = Auth::user();

        if ($user?->usesAdminPanel()) {
            return;
        }

        if ($user?->usesBranchPanel()
            && (int) $distribution->to_branch_id === (int) $user->branch_id
            && $user->can('inventory.stock-distribution.receive')) {
            abort_if($write, 403);

            return;
        }

        abort(404);
    }
}
