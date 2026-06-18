<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BranchCatalogReplicationService
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $data
     * @return list<Model>
     */
    public function createSingle(string $modelClass, array $data, ?int $branchId = null): Model
    {
        $resolvedBranchId = $this->resolveStoreBranchId($branchId);

        $data['branch_id'] = $resolvedBranchId ?? Branch::resolveAdminCatalogBranchId();

        return $modelClass::create($data);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $data
     * @return list<Model>
     */
    public function createForAllBranches(string $modelClass, array $data, ?string $catalogGroupId = null, ?Collection $branchIds = null): array
    {
        $catalogGroupId ??= (string) Str::uuid();
        $branchIds ??= Branch::query()->active()->orderBy('id')->pluck('id');
        $created = [];

        foreach ($branchIds as $branchId) {
            $branchData = $data;
            $branchData['branch_id'] = (int) $branchId;
            $branchData['catalog_group_id'] = $catalogGroupId;

            if (array_key_exists('slug', $branchData)) {
                $branchData['slug'] = '';
            }

            $created[] = $modelClass::create($branchData);
        }

        return $created;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<int>  $sourceIds
     * @return list<int>
     */
    public function mapIdsForBranch(string $modelClass, array $sourceIds, int $branchId): array
    {
        return collect($sourceIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => $this->mapIdForBranch($modelClass, (int) $id, $branchId))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function mapIdForBranch(string $modelClass, ?int $sourceId, int $branchId): ?int
    {
        if ($sourceId === null) {
            return null;
        }

        /** @var Model|null $source */
        $source = $modelClass::query()->find($sourceId);

        if ($source === null) {
            return null;
        }

        if ((int) $source->branch_id === $branchId) {
            return $sourceId;
        }

        $groupId = $source->catalog_group_id ?? $this->assignCatalogGroup($source);

        $siblingId = $modelClass::query()
            ->where('catalog_group_id', $groupId)
            ->where('branch_id', $branchId)
            ->value('id');

        if ($siblingId !== null) {
            return (int) $siblingId;
        }

        $existingByNameId = $this->findExistingIdByName($modelClass, $source, $branchId);

        if ($existingByNameId !== null) {
            $this->linkCatalogGroup($modelClass, $existingByNameId, $groupId);

            return $existingByNameId;
        }

        return $this->replicateSingleToBranch($source, $branchId, $groupId)->id;
    }

    public function resolveStoreBranchId(?int $requestedBranchId): ?int
    {
        $userBranchId = Auth::user()?->branch_id;

        if ($userBranchId !== null) {
            return $userBranchId;
        }

        if ($requestedBranchId === null || $requestedBranchId <= 0) {
            return null;
        }

        return $requestedBranchId;
    }

    private function assignCatalogGroup(Model $source): string
    {
        $groupId = (string) Str::uuid();
        $source->forceFill(['catalog_group_id' => $groupId])->save();

        return $groupId;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function findExistingIdByName(string $modelClass, Model $source, int $branchId): ?int
    {
        $name = $source->getAttribute('name');

        if (! is_string($name) || $name === '') {
            return null;
        }

        $existingId = $modelClass::query()
            ->where('branch_id', $branchId)
            ->where('name', $name)
            ->value('id');

        return $existingId !== null ? (int) $existingId : null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function linkCatalogGroup(string $modelClass, int $recordId, string $groupId): void
    {
        $modelClass::query()
            ->whereKey($recordId)
            ->whereNull('catalog_group_id')
            ->update(['catalog_group_id' => $groupId]);
    }

    private function replicateSingleToBranch(Model $source, int $branchId, string $groupId): Model
    {
        $attributes = collect($source->getAttributes())
            ->except(['id', 'created_at', 'updated_at'])
            ->all();

        $attributes['branch_id'] = $branchId;
        $attributes['catalog_group_id'] = $groupId;

        if (array_key_exists('slug', $attributes)) {
            unset($attributes['slug']);
        }

        return $source->newQuery()->create($attributes);
    }
}
