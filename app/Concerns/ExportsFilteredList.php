<?php

namespace App\Concerns;

use App\Exports\SimpleListExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

trait ExportsFilteredList
{
    protected const LIST_EXPORT_LIMIT = 5000;

    protected function applyCreatedAtDateFilters(Builder $query, Request $request): Builder
    {
        return $this->applyDateColumnFilters($query, $request, 'created_at');
    }

    /**
     * Filter by a date/datetime column using date_from / date_to request params.
     */
    protected function applyDateColumnFilters(
        Builder $query,
        Request $request,
        string $column = 'date',
    ): Builder {
        return $query
            ->when(
                $request->filled('date_from'),
                fn ($q) => $q->whereDate($column, '>=', $request->date('date_from')),
            )
            ->when(
                $request->filled('date_to'),
                fn ($q) => $q->whereDate($column, '<=', $request->date('date_to')),
            );
    }

    /**
     * @param  list<string>  $headings
     * @param  Collection<int, list<string|int|float|null>>  $rows
     */
    protected function downloadListExcel(string $filename, array $headings, Collection $rows): BinaryFileResponse
    {
        return Excel::download(
            new SimpleListExport($headings, $rows),
            $filename.'-'.now()->format('Y-m-d-His').'.xlsx',
        );
    }

    /**
     * @param  list<string>  $headings
     * @param  Collection<int, list<string|int|float|null>>|list<list<string|int|float|null>>  $rows
     */
    protected function downloadListPdf(string $title, array $headings, Collection|array $rows): SymfonyResponse
    {
        $pdf = Pdf::loadView('pdf.simple-list', [
            'title' => $title,
            'headings' => $headings,
            'rows' => Collection::wrap($rows)->values()->all(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download(str($title)->slug().'-'.now()->format('Y-m-d-His').'.pdf');
    }

    /**
     * @param  list<string>  $headings
     * @param  Collection<int, list<string|int|float|null>>|list<list<string|int|float|null>>  $rows
     */
    protected function printListHtml(string $title, array $headings, Collection|array $rows): SymfonyResponse
    {
        return response()->view('print.simple-list', [
            'title' => $title,
            'headings' => $headings,
            'rows' => Collection::wrap($rows)->values()->all(),
        ]);
    }

    /**
     * @param  list<string>  $headings
     * @param  Collection<int, list<string|int|float|null>>|list<list<string|int|float|null>>  $rows
     */
    protected function downloadListCsv(string $filename, array $headings, Collection|array $rows): SymfonyResponse
    {
        $downloadName = $filename.'-'.now()->format('Y-m-d-His').'.csv';
        $exportRows = Collection::wrap($rows)->values();

        return response()->streamDownload(function () use ($headings, $exportRows): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens CSV correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings);

            foreach ($exportRows as $row) {
                fputcsv($handle, is_array($row) ? $row : Collection::wrap($row)->all());
            }

            fclose($handle);
        }, $downloadName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
