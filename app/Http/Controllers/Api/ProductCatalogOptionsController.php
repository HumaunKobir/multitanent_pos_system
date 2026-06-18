<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BranchCatalogOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCatalogOptionsController extends Controller
{
    public function __invoke(Request $request, BranchCatalogOptionsService $catalogOptions): JsonResponse
    {
        abort_unless($request->user()?->canAny(['product.create', 'product.update']), 403);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
        ]);

        $requestedBranchId = filled($validated['branch_id'] ?? null)
            ? (int) $validated['branch_id']
            : null;

        return response()->json($catalogOptions->forBranch($requestedBranchId));
    }
}
