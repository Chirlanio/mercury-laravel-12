<?php

namespace App\Exports;

use App\Models\StockAuditItem;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockAuditExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(
        private int $auditId,
    ) {}

    public function query()
    {
        return StockAuditItem::where('audit_id', $this->auditId)
            ->orderBy('product_reference');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Referencia',
            'Descricao',
            'Codigo Barras',
            'Tamanho',
            'Qtd Sistema',
            'Contagem 1',
            'Contagem 2',
            'Contagem 3',
            'Qtd Aceita',
            'Resolucao',
            'Divergencia',
            'Valor Divergencia (Venda)',
            'Valor Divergencia (Custo)',
            'Preco Venda',
            'Preco Custo',
            'Justificado',
            'Justificativa',
            'Loja Justificou',
        ];
    }

    public function map($item): array
    {
        return [
            $item->id,
            $item->product_reference,
            $item->product_description,
            $item->product_barcode,
            $item->product_size ?? '-',
            number_format((float) $item->system_quantity, 2, ',', '.'),
            $item->count_1 !== null ? number_format((float) $item->count_1, 2, ',', '.') : '-',
            $item->count_2 !== null ? number_format((float) $item->count_2, 2, ',', '.') : '-',
            $item->count_3 !== null ? number_format((float) $item->count_3, 2, ',', '.') : '-',
            $item->accepted_count !== null ? number_format((float) $item->accepted_count, 2, ',', '.') : '-',
            $item->resolution_type ?? '-',
            number_format((float) $item->divergence, 2, ',', '.'),
            number_format((float) $item->divergence_value, 2, ',', '.'),
            number_format((float) $item->divergence_value_cost, 2, ',', '.'),
            number_format((float) $item->unit_price, 2, ',', '.'),
            number_format((float) $item->cost_price, 2, ',', '.'),
            $item->is_justified ? 'Sim' : 'Nao',
            $item->justification_note ?? '-',
            $item->store_justified ? 'Sim' : 'Nao',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
