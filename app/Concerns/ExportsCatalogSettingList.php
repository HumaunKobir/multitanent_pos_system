<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

trait ExportsCatalogSettingList
{
    use ExportsFilteredList;

    abstract protected function catalogExportPermission(): string;

    abstract protected function catalogExportTitle(): string;

    protected function catalogListQuery(Request $request): Builder
    {
        return $this->applyCreatedAtDateFilters(
            $this->branchCatalogQuery()
                ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")),
            $request,
        )->latest();
    }

    /**
     * @return Collection<int, list<string|int>>
     */
    protected function catalogExportRows(Request $request): Collection
    {
        return $this->catalogListQuery($request)
            ->limit(self::LIST_EXPORT_LIMIT)
            ->get()
            ->values()
            ->map(fn ($row, int $index): array => [
                $index + 1,
                $row->name,
                (int) $row->status === 1 ? 'Active' : 'InActive',
                optional($row->created_at)?->format('Y-m-d H:i') ?? '—',
            ]);
    }

    /**
     * @return list<string>
     */
    protected function catalogExportHeadings(): array
    {
        return ['#', 'Name', 'Status', 'Created At'];
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize($this->catalogExportPermission());

        return $this->downloadListExcel(
            str($this->catalogExportTitle())->slug()->toString(),
            $this->catalogExportHeadings(),
            $this->catalogExportRows($request),
        );
    }

    public function exportPdf(Request $request): SymfonyResponse
    {
        $this->authorize($this->catalogExportPermission());

        return $this->downloadListPdf(
            $this->catalogExportTitle(),
            $this->catalogExportHeadings(),
            $this->catalogExportRows($request),
        );
    }

    public function exportPrint(Request $request): SymfonyResponse
    {
        $this->authorize($this->catalogExportPermission());

        return $this->printListHtml(
            $this->catalogExportTitle(),
            $this->catalogExportHeadings(),
            $this->catalogExportRows($request),
        );
    }
}
