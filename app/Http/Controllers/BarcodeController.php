<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BarcodeController extends Controller
{
    private function listQuery(?string $search)
    {
        return Barcode::query()
            ->when($search, fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            }))
            ->listed();
    }

    public function index(Request $request): Response
    {
        $this->authorize('barcode.view');

        $barcodes = $this->listQuery($request->search)
            ->with(['product:id,name,image,sale_price,discount_price', 'variation:id,variation_data,price'])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/barcode/index', [
            'barcodes' => $barcodes,
            'filters' => $request->only('search'),
        ]);
    }

    public function print(Request $request): Response
    {
        $this->authorize('barcode.view');

        $ids = array_filter(explode(',', (string) $request->query('ids', '')));

        $barcodes = Barcode::with(['product:id,name,image,sale_price,discount_price', 'variation:id,variation_data,price'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->listed()
            ->get();

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

        $ids = $this->listQuery($validated['search'] ?? null)
            ->skip($from - 1)
            ->take($to - $from + 1)
            ->pluck('id')
            ->all();

        return response()->json(['ids' => $ids]);
    }
}
