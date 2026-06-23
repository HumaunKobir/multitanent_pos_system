<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use Illuminate\Support\Facades\DB;

class PromotionReplicationService
{
    public function replicateToBranch(Promotion $promotion, int $targetBranchId): Promotion
    {
        return DB::transaction(function () use ($promotion, $targetBranchId) {
            $clone = $promotion->replicate(['id']);
            $clone->branch_id = $targetBranchId;
            $clone->push();

            foreach ($promotion->targets as $target) {
                $mappedId = $this->mapTargetId($target->target_type, (int) $target->target_id, $targetBranchId);

                if ($mappedId === null) {
                    continue;
                }

                PromotionTarget::create([
                    'promotion_id' => $clone->id,
                    'target_type' => $target->target_type,
                    'target_id' => $mappedId,
                ]);
            }

            if ($promotion->bundle_product_ids) {
                $clone->bundle_product_ids = collect($promotion->bundle_product_ids)
                    ->map(fn ($id) => Product::query()->find($id)?->siblingForBranch($targetBranchId)?->id)
                    ->filter()
                    ->values()
                    ->all();
                $clone->save();
            }

            return $clone->fresh('targets');
        });
    }

    private function mapTargetId(string $targetType, int $sourceId, int $targetBranchId): ?int
    {
        return match ($targetType) {
            'category' => Category::query()->find($sourceId)?->siblingForBranch($targetBranchId)?->id,
            'brand' => Brand::query()->find($sourceId)?->siblingForBranch($targetBranchId)?->id,
            'product' => Product::query()->find($sourceId)?->siblingForBranch($targetBranchId)?->id,
            default => null,
        };
    }

    /** @return array<int, int> */
    public function replicateToOperatingBranches(Promotion $promotion): array
    {
        $created = [];

        Branch::query()
            ->operating()
            ->whereKeyNot($promotion->branch_id)
            ->pluck('id')
            ->each(function (int $branchId) use ($promotion, &$created) {
                $clone = $this->replicateToBranch($promotion, $branchId);
                $created[$branchId] = $clone->id;
            });

        return $created;
    }
}
