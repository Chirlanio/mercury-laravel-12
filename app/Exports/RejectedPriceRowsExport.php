<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RejectedPriceRowsExport implements FromArray, WithHeadings
{
    public function __construct(
        protected array $rows,
    ) {}

    public function headings(): array
    {
        return ['Linha', 'Referência', 'Preço Venda', 'Custo', 'Motivo'];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
