<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SimpleListExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  Collection<int, list<string|int|float|null>>  $rows
     */
    public function __construct(
        private array $headings,
        private Collection $rows,
    ) {}

    public function collection(): Collection
    {
        return $this->rows->values();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }
}
