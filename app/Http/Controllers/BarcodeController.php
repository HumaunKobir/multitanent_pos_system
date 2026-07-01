<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Color;
use App\Models\Size;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BarcodeController extends Controller
{
    private function listQuery(?string $search, ?int $listBranchId)
    {
        return Barcode::query()
            ->when($listBranchId !== null, fn ($q) => $q->where('branch_id', $listBranchId))
            ->when($search, fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            }))
            ->listed();
    }

    private function resolveListBranchId(Request $request): ?int
    {
        $user = Auth::user();

        if ($user?->usesBranchPanel()) {
            return $user->branch_id;
        }

        $filter = $request->input('branch_id');

        if ($filter === 'all') {
            return null;
        }

        if ($filter !== null && $filter !== '') {
            return (int) $filter;
        }

        return Branch::resolveMainBranchId();
    }

    private function canFilterByBranch(): bool
    {
        return Auth::user()?->usesAdminPanel() ?? false;
    }

    /**
     * @param  Collection<int, Barcode>  $barcodes
     * @return Collection<int, Barcode>
     */
    private function withResolvedProductAttributes($barcodes)
    {
        $colorIds = $barcodes
            ->pluck('product.colors')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $sizeIds = $barcodes
            ->pluck('product.sizes')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $colorNames = $colorIds->isNotEmpty()
            ? Color::query()->whereIn('id', $colorIds)->pluck('name', 'id')
            : collect();

        $sizeNames = $sizeIds->isNotEmpty()
            ? Size::query()->whereIn('id', $sizeIds)->pluck('name', 'id')
            : collect();

        return $barcodes->each(function (Barcode $barcode) use ($colorNames, $sizeNames): void {
            $product = $barcode->product;

            if ($product === null) {
                return;
            }

            $product->setAttribute(
                'color_labels',
                collect($product->colors ?? [])
                    ->map(fn ($id) => $colorNames[(int) $id] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
            );

            $product->setAttribute(
                'size_labels',
                collect($product->sizes ?? [])
                    ->map(fn ($id) => $sizeNames[(int) $id] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
            );
        });
    }

    private function barcodeRelations(): array
    {
        return [
            'product:id,name,code,image,sale_price,discount_price,colors,sizes',
            'variation:id,variation_data,price,sku,sku_code',
        ];
    }

    public function index(Request $request): Response
    {
        $this->authorize('barcode.view');

        $listBranchId = $this->resolveListBranchId($request);
        $canFilterByBranch = $this->canFilterByBranch();
        $mainBranchId = Branch::resolveMainBranchId();

        $barcodes = $this->listQuery($request->search, $listBranchId)
            ->with($this->barcodeRelations())
            ->paginate(10)
            ->withQueryString();

        $this->withResolvedProductAttributes($barcodes->getCollection());

        return Inertia::render('admin/barcode/index', [
            'barcodes' => $barcodes,
            'mainBranchId' => $mainBranchId,
            'filters' => array_merge(
                $request->only('search'),
                $canFilterByBranch ? [
                    'branch_id' => $request->input('branch_id', (string) $mainBranchId),
                ] : [],
            ),
            'branches' => $canFilterByBranch ? Branch::active()->orderBy('name')->pluck('name', 'id') : [],
        ]);
    }

    public function print(Request $request): Response
    {
        $this->authorize('barcode.view');

        $ids = array_filter(explode(',', (string) $request->query('ids', '')));

        $barcodes = Barcode::with($this->barcodeRelations())
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->listed()
            ->get();

        $this->withResolvedProductAttributes($barcodes);

        return Inertia::render('admin/barcode/print', [
            'barcodes' => $barcodes,
        ]);
    }

    public function serialRange(Request $request): JsonResponse
    {
        $this->authorize('barcode.view');

        $validated = $request->validate([
            'from' => ['required', 'integer', 'min:1'],
            'to' => ['required', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $from = min($validated['from'], $validated['to']);
        $to = max($validated['from'], $validated['to']);

        $listBranchId = $this->resolveListBranchId($request);

        $ids = $this->listQuery($validated['search'] ?? null, $listBranchId)
            ->skip($from - 1)
            ->take($to - $from + 1)
            ->pluck('id')
            ->all();

        return response()->json(['ids' => $ids]);
    }
}
