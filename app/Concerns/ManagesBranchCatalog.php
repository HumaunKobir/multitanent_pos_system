<?php

namespace App\Concerns;

use App\Services\BranchCatalogReplicationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait ManagesBranchCatalog
{
    abstract protected function catalogModelClass(): string;

    /**
     * @return list<string>
     */
    protected function catalogFillableKeys(): array
    {
        return ['name', 'status'];
    }

    protected function branchCatalogQuery(): Builder
    {
        return $this->catalogModelClass()::query()->forCatalogPanel();
    }

    protected function catalogReplication(): BranchCatalogReplicationService
    {
        return app(BranchCatalogReplicationService::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedCatalogData(Request $request): array
    {
        return $request->validate($this->catalogValidationRules());
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeCatalogRecords(array $data): Model
    {
        $requestedBranchId = filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null;
        unset($data['branch_id']);

        return $this->catalogReplication()->createSingle(
            $this->catalogModelClass(),
            $data,
            $requestedBranchId,
        );
    }

    protected function authorizeCatalogAccess(Model $record): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return;
        }

        abort_unless((int) $record->branch_id === $branchId, 404);
    }
}
