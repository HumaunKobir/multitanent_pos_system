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
            'categories' => $this->pluckCatalogOptions(Category::class, $branchId),
            'brands' => $this->pluckCatalogOptions(Brand::class, $branchId),
            'units' => $this->pluckCatalogOptions(Unit::class, $branchId),
            'warranties' => $this->pluckCatalogOptions(Warranty::class, $branchId),
            'colorOptions' => $this->getCatalogQuery(Color::class, $branchId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Color $color): array => [
                    'value' => $color->name,
                    'label' => $color->name,
                    'id' => (string) $color->id,
                ])
                ->all(),
            'sizeOptions' => $this->getCatalogQuery(Size::class, $branchId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Size $size): array => [
                    'value' => $size->name,
                    'label' => $size->name,
                    'id' => (string) $size->id,
                ])
                ->all(),
        ];
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function pluckCatalogOptions(string $modelClass, int $branchId)
    {
        return $this->getCatalogQuery($modelClass, $branchId)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function getCatalogQuery(string $modelClass, int $branchId)
    {
        $mainBranchId = Branch::resolveAdminCatalogBranchId();

        if ($branchId === $mainBranchId) {
            return $modelClass::query()->where('branch_id', $mainBranchId)->active();
        }

        $branchGroupIds = $modelClass::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('catalog_group_id')
            ->pluck('catalog_group_id')
            ->filter()
            ->all();

        $branchNames = $modelClass::query()
            ->where('branch_id', $branchId)
            ->pluck('name')
            ->filter()
            ->all();

        return $modelClass::query()
            ->active()
            ->where(function ($q) use ($branchId, $mainBranchId, $branchGroupIds, $branchNames) {
                $q->where('branch_id', $branchId);

                $q->orWhere(function ($mq) use ($mainBranchId, $branchGroupIds, $branchNames) {
                    $mq->where('branch_id', $mainBranchId);

                    if (! empty($branchGroupIds)) {
                        $mq->whereNotIn('catalog_group_id', $branchGroupIds);
                    }

                    if (! empty($branchNames)) {
                        $mq->whereNotIn('name', $branchNames);
                    }
                });
            });
    }
}
