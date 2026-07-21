<?php

namespace App\Http\Controllers\Inventory;

use App\Concerns\ExportsFilteredList;
use App\Enums\ProductLogType;
use App\Http\Controllers\Concerns\AuthorizesBranchUserRecords;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Damage;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\InventoryStockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DamageController extends Controller
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
            Damage::query()->ownBranchUser()
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('invoice_sequence', 'like', "%{$s}%")
                        ->orWhere('serial', 'like', "%{$s}%")
                        ->orWhere('id', 'like', "%{$s}%")
                        ->orWhere('comment', 'like', "%{$s}%");
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
            ->map(fn (Damage $damage, int $index): array => [
                $index + 1,
                $damage->invoice_number,
                optional($damage->date)?->format('Y-m-d') ?? '—',
                $damage->comment ?: '—',
            ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('inventory.damage.view');

        $damages = $this->listQuery($request)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/damage/index', [
            'damages' => $damages,
            'filters' => $request->only('search', 'date_from', 'date_to'),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize('inventory.damage.view');

        return $this->downloadListExcel(
            'damages',
            ['#', 'Invoice', 'Date', 'Note'],
            $this->exportRows($request),
        );
    }

    public function exportPdf(Request $request): SymfonyResponse
    {
        $this->authorize('inventory.damage.view');

        return $this->downloadListPdf(
            'Damage',
            ['#', 'Invoice', 'Date', 'Note'],
            $this->exportRows($request),
        );
    }

    public function exportPrint(Request $request): SymfonyResponse
    {
        $this->authorize('inventory.damage.view');

        return $this->printListHtml(
            'Damage',
            ['#', 'Invoice', 'Date', 'Note'],
            $this->exportRows($request),
        );
    }

    public function create(): Response
    {
        $this->authorize('inventory.damage.create');

        return Inertia::render('admin/inventory/damage/create', [
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.damage.create');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($data, $branchId) {
                $lines = [];

                foreach ($data['items'] as $item) {
                    $qty = (float) $item['quantity'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $productId = (int) $item['product_id'];
                    $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;
                    $batchMap = [];

                    if ($variationId) {
                        $this->stock->deductVariation($variationId, $qty, ProductLogType::Damage);
                    } else {
                        $batchMap = $this->stock->deductFifo(
                            $branchId,
                            $productId,
                            $qty,
                            fn (Batch $batch, float $deductQty) => $batch->damageStock($deductQty)
                        );
                    }

                    $lines[] = [
                        'branch_id' => $branchId,
                        'product_id' => $productId,
                        'variation_id' => $variationId,
                        'quantity' => $qty,
                        'batches' => $batchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with quantity greater than zero is required.');
                }

                $damage = Damage::create([
                    'branch_id' => $branchId,
                    'user_id' => $this->currentUserId(),
                    'date' => $data['date'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $damage->products()->create($line);
                }

                $damage->load('products');
                $this->accounting->postDamage($damage, $this->costService->costForDamage($damage));
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to record damage.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.damage.index')
            ->with('success', 'Damage recorded successfully.');
    }

    public function show(Damage $damage): Response
    {
        $this->authorize('inventory.damage.view');
        $this->authorizeBranchUserRecord($damage);

        $damage->load(['products.product', 'products.variation']);

        return Inertia::render('admin/inventory/damage/show', [
            'damage' => $damage,
        ]);
    }

    public function edit(Damage $damage): Response
    {
        $this->authorize('inventory.damage.update');
        $this->authorizeBranchUserRecord($damage);

        $damage->load(['products.product', 'products.variation']);

        return Inertia::render('admin/inventory/damage/edit', [
            'today' => now()->format('Y-m-d'),
            'damage' => [
                'id' => $damage->id,
                'invoice_number' => $damage->invoice_number,
                'date' => optional($damage->date)->format('Y-m-d'),
                'comment' => $damage->comment,
                'items' => $damage->products->map(fn ($line) => [
                    'product_id' => $line->product_id,
                    'variation_id' => $line->variation_id,
                    'product_name' => $line->product?->name,
                    'product_code' => $line->product?->code,
                    'variation_label' => $line->variation?->variation_data['label'] ?? null,
                    'quantity' => (string) (int) $line->quantity,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, Damage $damage): RedirectResponse
    {
        $this->authorize('inventory.damage.update');
        $this->authorizeBranchUserRecord($damage);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = Auth::user()?->branch_id;

        try {
            DB::transaction(function () use ($damage, $data, $branchId) {
                $this->accounting->reverseFor($damage);
                $damage->load(['products']);

                $this->rollbackDamage($damage);
                $damage->products()->delete();

                $lines = [];

                foreach ($data['items'] as $item) {
                    $qty = (float) $item['quantity'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $productId = (int) $item['product_id'];
                    $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;
                    $batchMap = [];

                    if ($variationId) {
                        $this->stock->deductVariation($variationId, $qty, ProductLogType::Damage);
                    } else {
                        $batchMap = $this->stock->deductFifo(
                            $branchId,
                            $productId,
                            $qty,
                            fn (Batch $batch, float $deductQty) => $batch->damageStock($deductQty)
                        );
                    }

                    $lines[] = [
                        'branch_id' => $branchId,
                        'product_id' => $productId,
                        'variation_id' => $variationId,
                        'quantity' => $qty,
                        'batches' => $batchMap,
                    ];
                }

                if ($lines === []) {
                    throw new \RuntimeException('At least one line with quantity greater than zero is required.');
                }

                $damage->update([
                    'date' => $data['date'],
                    'comment' => $data['comment'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $damage->products()->create($line);
                }

                $damage->load('products');
                $this->accounting->postDamage($damage, $this->costService->costForDamage($damage));
            });
        } catch (\Throwable $e) {
            return back()
                ->withErrors([
                    'items' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update damage record.',
                ])
                ->withInput();
        }

        return redirect()->route('inventory.damage.index')
            ->with('success', 'Damage record updated successfully.');
    }

    public function destroy(Damage $damage): RedirectResponse
    {
        $this->authorize('inventory.damage.delete');
        $this->authorizeBranchUserRecord($damage);

        $damage->load(['products']);

        try {
            DB::transaction(function () use ($damage) {
                $this->accounting->reverseFor($damage);
                $this->rollbackDamage($damage);
                $damage->products()->delete();
                $damage->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to delete damage record.');
        }

        return redirect()->route('inventory.damage.index')
            ->with('success', 'Damage record deleted successfully.');
    }

    private function rollbackDamage(Damage $damage): void
    {
        foreach ($damage->products as $line) {
            $qty = (float) $line->quantity;

            $this->stock->restoreFromBatchMap(
                $line->batches ?? [],
                fn (Batch $batch, float $batchQty) => $batch->inStock($batchQty)
            );

            if ($line->variation_id) {
                $this->stock->restoreVariation((int) $line->variation_id, $qty, ProductLogType::Purchase);
            }
        }
    }
}
