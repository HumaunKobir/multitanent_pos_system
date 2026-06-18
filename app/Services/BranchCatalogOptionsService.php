<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use App\Models\Unit;
use App\Models\Warranty;
use Illuminate\Support\Facades\Auth;

class BranchCatalogOptionsService
{
    public function resolveBranchId(?int $requestedBranchId = null): int
    {
        $userBranchId = Auth::user()?->branch_id;

        if ($userBranchId !== null) {
            return (int) $userBranchId;
        }

        if ($requestedBranchId !== null && $requestedBranchId > 0) {
            return $requestedBranchId;
        }

        return Branch::resolveAdminCatalogBranchId();
    }

    /**
     * @return array<string, mixed>
     */
    public function forBranch(?int $requestedBranchId = null): array
    {
        $branchId = $this->resolveBranchId($requestedBranchId);

        return [
            'branch_id' => $branchId,
            'categories' => Category::query()->where('branch_id', $branchId)->active()->pluck('name', 'id'),
            'brands' => Brand::query()->where('branch_id', $branchId)->active()->pluck('name', 'id'),
            'units' => Unit::query()->where('branch_id', $branchId)->active()->pluck('name', 'id'),
            'warranties' => Warranty::query()->where('branch_id', $branchId)->active()->pluck('name', 'id'),
            'colorOptions' => Color::query()->where('branch_id', $branchId)->active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Color $color): array => [
                    'value' => $color->name,
                    'label' => $color->name,
                    'id' => (string) $color->id,
                ])
                ->all(),
            'sizeOptions' => Size::query()->where('branch_id', $branchId)->active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Size $size): array => [
                    'value' => $size->name,
                    'label' => $size->name,
                    'id' => (string) $size->id,
                ])
                ->all(),
        ];
    }
}
