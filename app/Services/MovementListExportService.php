<?php

namespace App\Services;

use App\Models\MovementType;
use App\Models\Store;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporta a listagem de movimentações (com filtros aplicados) para XLSX e PDF.
 *
 * XLSX: usa cursor() + FromGenerator para streaming — não carrega todos registros
 * em memória. Suporta listagens grandes até ROW_LIMIT.
 *
 * PDF: carrega até PDF_ROW_LIMIT (menor, dompdf não suporta streaming).
 */
class MovementListExportService
{
    const ROW_LIMIT = 100000;       // XLSX
    const PDF_ROW_LIMIT = 5000;     // PDF (dompdf carrega tudo em memória)

    /**
     * @param  Builder  $query  Query já filtrada (incluindo with('movementType'))
     * @param  array  $filters  Resumo dos filtros para cabeçalho do arquivo
     */
    public function exportXlsx(Builder $query, array $filters): BinaryFileResponse
    {
        $total = (clone $query)->count();

        if ($total > static::ROW_LIMIT) {
            abort(422, sprintf(
                'Export limitado a %s linhas (filtro atual: %s). Refine os filtros.',
                number_format(static::ROW_LIMIT, 0, ',', '.'),
                number_format($total, 0, ',', '.'),
            ));
        }

        $export = new class($query, $filters) implements FromGenerator, WithHeadings {
            public function __construct(public Builder $query, public array $filters) {}

            public function generator(): \Generator
            {
                foreach ($this->query->cursor() as $m) {
                    yield [
                        $m->id,
                        $m->movement_date?->format('d/m/Y'),
                        $m->movement_time ? substr($m->movement_time, 0, 8) : '',
                        $m->store_code,
                        $m->invoice_number,
                        $m->movement_code,
                        $m->movementType?->description ?? '',
                        $m->entry_exit === 'E' ? 'Entrada' : 'Saída',
                        $m->ref_size,
                        $m->barcode,
                        $m->cpf_customer,
                        $m->cpf_consultant,
                        number_format((float) $m->quantity, 3, ',', '.'),
                        number_format((float) $m->net_quantity, 3, ',', '.'),
                        number_format((float) $m->sale_price, 2, ',', '.'),
                        number_format((float) $m->cost_price, 2, ',', '.'),
                        number_format((float) $m->realized_value, 2, ',', '.'),
                        number_format((float) $m->discount_value, 2, ',', '.'),
                        number_format((float) $m->net_value, 2, ',', '.'),
                        $m->synced_at?->format('d/m/Y H:i'),
                    ];
                }
            }

            public function headings(): array
            {
                return [
                    'ID', 'Data', 'Hora', 'Loja', 'NF', 'Tipo (Cod.)', 'Tipo',
                    'E/S', 'Ref/Tam', 'Barcode', 'CPF Cliente', 'CPF Consultor',
                    'Qtde', 'Qtde Líq.', 'Preço Venda', 'Preço Custo',
                    'Vlr. Realizado', 'Desconto', 'Vlr. Líquido', 'Sincronizado em',
                ];
            }
        };

        $filename = 'movimentacoes-'.now()->format('Y-m-d-His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    public function exportPdf(Builder $query, array $filters): Response
    {
        $total = (clone $query)->count();

        if ($total > static::PDF_ROW_LIMIT) {
            abort(422, sprintf(
                'Export PDF limitado a %s linhas (filtro atual: %s). Refine os filtros ou use XLSX.',
                number_format(static::PDF_ROW_LIMIT, 0, ',', '.'),
                number_format($total, 0, ',', '.'),
            ));
        }

        $items = $query->get();

        $totals = [
            'items' => $items->count(),
            'quantity' => (float) $items->sum('quantity'),
            'realized_value' => (float) $items->sum('realized_value'),
            'discount_value' => (float) $items->sum('discount_value'),
            'net_value' => (float) $items->sum('net_value'),
        ];

        // Resolve labels para filtros (loja e tipo de movimento)
        $storeLabel = null;
        if (! empty($filters['store_code'])) {
            $store = Store::where('code', $filters['store_code'])->first();
            $storeLabel = $store
                ? $filters['store_code'].' · '.($store->display_name ?? $store->name)
                : $filters['store_code'];
        }

        $typeLabel = null;
        if (! empty($filters['movement_code'])) {
            $type = MovementType::where('code', $filters['movement_code'])->first();
            $typeLabel = $type
                ? $filters['movement_code'].' · '.$type->description
                : (string) $filters['movement_code'];
        }

        $pdf = Pdf::loadView('pdf.movement-list', [
            'items' => $items,
            'totals' => $totals,
            'filters' => $filters,
            'storeLabel' => $storeLabel,
            'typeLabel' => $typeLabel,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        $filename = 'movimentacoes-'.now()->format('Y-m-d-His').'.pdf';

        return $pdf->download($filename);
    }
}
