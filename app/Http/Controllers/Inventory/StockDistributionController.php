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
            ->with([
                'toBranch:id,name',
                'receivedBy:id,name',
                'returnSentBy:id,name',
                'returnReceivedBy:id,name',
            ])
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
            ->with([
                'fromBranch:id,name',
                'receivedBy:id,name',
                'returnSentBy:id,name',
                'returnReceivedBy:id,name',
            ])
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
            'products.receivedBy:id,name',
            'toBranch:id,name',
            'fromBranch:id,name',
            'receivedBy:id,name',
            'returnSentBy:id,name',
            'returnReceivedBy:id,name',
            'purchase:id,serial',
        ]);

        $user = Auth::user();
        $formatted = $this->formatDistribution($stockDistribution);

        return Inertia::render('admin/inventory/stock-distribution/show', [
            'distribution' => $formatted,
            'canManage' => $this->canManageDistributions($user),
            'canReceive' => $this->canReceiveDistribution($user, $stockDistribution),
            'canSendReturn' => $this->canSendReturnToMain($user, $stockDistribution),
            'canReceiveReturn' => $this->canReceiveReturn($user, $stockDistribution),
        ]);
    }

    public function sendReturn(StockDistribution $stockDistribution): RedirectResponse
    {
        $this->authorize('inventory.stock-distribution.receive');
        $this->authorizeBranchReceiverOnly();
        $this->authorizeDistributionAccess($stockDistribution);

        abort_unless(
            (int) $stockDistribution->to_branch_id === (int) Auth::user()?->branch_id,
            403,
        );

        abort_unless(
            $stockDistribution->isReceived(),
            422,
            'Only fully received distributions can be returned to the main warehouse.',
        );

        $stockDistribution->load(['products']);

        try {
            DB::transaction(function () use ($stockDistribution) {
                foreach ($stockDistribution->products as $line) {
                    $this->distribution->withdrawReceivedLineFromBranch($line, (int) $stockDistribution->to_branch_id);
                }

                $stockDistribution->update([
                    'status' => StockDistributionStatus::ReturnPending,
                    'return_sent_at' => now(),
                    'return_sent_by_user_id' => Auth::id(),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to send stock to the main warehouse.');
        }

        return redirect()->route('inventory.stock-distribution.received')
            ->with('success', 'Stock sent to the main warehouse. Awaiting admin receipt.');
    }

    public function receiveReturn(StockDistribution $stockDistribution): RedirectResponse
    {
        $this->authorize('inventory.stock-distribution.update');
        $this->authorizeMainBranchManager();

        abort_unless(
            $stockDistribution->isReturnPending(),
            422,
            'This distribution is not awaiting a return receipt.',
        );

        $stockDistribution->load(['products']);

        try {
            DB::transaction(function () use ($stockDistribution) {
                $this->accounting->reverseStockDistributionLines($stockDistribution);

                foreach ($stockDistribution->products as $line) {
                    $this->distribution->restoreReturnedLineToMain($line);
                }

                $stockDistribution->update([
                    'status' => StockDistributionStatus::Returned,
                    'return_received_at' => now(),
                    'return_received_by_user_id' => Auth::id(),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to receive the returned stock.');
        }

        return redirect()->route('inventory.stock-distribution.index')
            ->with('success', 'Returned stock received into the main warehouse.');
    }

    public function receive(Request $request, StockDistribution $stockDistribution): RedirectResponse
    {
        $this->authorize('inventory.stock-distribution.receive');
        $this->authorizeBranchReceiverOnly();
        $this->authorizeDistributionAccess($stockDistribution);

        abort_unless($stockDistribution->isReceivable(), 422, 'This distribution is already fully received.');
        abort_unless(
            (int) $stockDistribution->to_branch_id === (int) Auth::user()?->branch_id,
            403,
        );

        $data = $request->validate([
            'line_ids' => ['nullable', 'array', 'min:1'],
            'line_ids.*' => ['integer', 'exists:stock_distribution_products,id'],
        ]);

        $lineIds = collect($data['line_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($lineIds === []) {
            $lineIds = $stockDistribution->products()
                ->whereNull('received_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $validLineIds = $stockDistribution->products()
            ->whereIn('id', $lineIds)
            ->whereNull('received_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($validLineIds === []) {
            return back()->with('error', 'No pending products selected for receipt.');
        }

        try {
            DB::transaction(function () use ($stockDistribution, $validLineIds) {
                $receivedLines = $this->distribution->receiveLines(
                    $stockDistribution,
                    $validLineIds,
                    (int) Auth::id(),
                );

                foreach ($receivedLines as $line) {
                    $this->accounting->postStockDistributionLine(
                        $stockDistribution,
                        $line,
                        $this->costService->costForStockDistributionLine($line),
                    );
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Unable to receive stock.');
        }

        $stockDistribution->refresh();

        $message = $stockDistribution->isReceived()
            ? 'All stock received successfully.'
            : 'Selected products received successfully.';

        return redirect()
            ->route('inventory.stock-distribution.received')
            ->with('success', $message);
    }

    public function edit(StockDistribution $stockDistribution): Response
    {
        $this->authorizeMainBranchManager();
        $this->authorize('inventory.stock-distribution.update');
        $this->authorizeDistributionAccess($stockDistribution, write: true);
        abort_unless($stockDistribution->isPending() && ! $stockDistribution->hasReceivedLines(), 403, 'Distributions with received products cannot be edited.');

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
        abort_unless($stockDistribution->isPending() && ! $stockDistribution->hasReceivedLines(), 403, 'Distributions with received products cannot be updated.');

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
        abort_unless($stockDistribution->isPending() && ! $stockDistribution->hasReceivedLines(), 403, 'Distributions with received products cannot be deleted.');

        $stockDistribution->load(['products']);

        try {
            DB::transaction(function () use ($stockDistribution) {
                $this->accounting->reverseStockDistributionLines($stockDistribution);
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
        $products = $stockDistribution->products->map(function ($line) {
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
                'is_received' => $line->isReceived(),
                'received_at' => optional($line->received_at)?->toIso8601String(),
                'received_by' => $line->receivedBy,
            ];
        })->values();

        $receivedCount = $products->filter(fn (array $line) => $line['is_received'])->count();
        $pendingCount = $products->count() - $receivedCount;

        return [
            'id' => $stockDistribution->id,
            'invoice_number' => $stockDistribution->invoice_number,
            'date' => optional($stockDistribution->date)->format('Y-m-d'),
            'comment' => $stockDistribution->comment,
            'status' => $stockDistribution->status?->value,
            'status_label' => $stockDistribution->status?->label(),
            'received_at' => optional($stockDistribution->received_at)?->toIso8601String(),
            'received_by' => $stockDistribution->receivedBy,
            'return_sent_at' => optional($stockDistribution->return_sent_at)?->toIso8601String(),
            'return_sent_by' => $stockDistribution->returnSentBy,
            'return_received_at' => optional($stockDistribution->return_received_at)?->toIso8601String(),
            'return_received_by' => $stockDistribution->returnReceivedBy,
            'received_count' => $receivedCount,
            'pending_count' => $pendingCount,
            'total_count' => $products->count(),
            'purchase' => $stockDistribution->purchase ? [
                'id' => $stockDistribution->purchase->id,
                'invoice_number' => $stockDistribution->purchase->invoice_number,
            ] : null,
            'from_branch' => $stockDistribution->fromBranch,
            'to_branch' => $stockDistribution->toBranch,
            'products' => $products,
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
            && $distribution->isReceivable()
            && $distribution->hasPendingLines()
            && (int) $distribution->to_branch_id === (int) $user->branch_id;
    }

    private function canSendReturnToMain($user, StockDistribution $distribution): bool
    {
        return $user !== null
            && $user->usesBranchPanel()
            && $user->can('inventory.stock-distribution.receive')
            && $distribution->isReceived()
            && (int) $distribution->to_branch_id === (int) $user->branch_id;
    }

    private function canReceiveReturn($user, StockDistribution $distribution): bool
    {
        return $user !== null
            && $user->usesAdminPanel()
            && $user->can('inventory.stock-distribution.update')
            && $distribution->isReturnPending();
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
