<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BarcodeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('barcode.view');

        $barcodes = Barcode::with(['product:id,name,image', 'variation:id,variation_data'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/barcode/index', [
            'barcodes' => $barcodes,
            'filters' => $request->only('search'),
        ]);
    }
}
