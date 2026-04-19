<?php

namespace App\Services;

use App\Models\ReturnOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporta devoluções para Excel (listagem com filtros) ou PDF
 * (comprovante individual para anexar ao atendimento).
 */
class ReturnOrderExportService
{
    /**
     * @param  Builder<ReturnOrder>  $query  Query já filtrada/escopada pelo controller
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query
            ->with(['reason', 'employee', 'store', 'createdBy', 'approvedBy', 'processedBy'])
            ->get();

        $export = new class($rows) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(public $rows) {}

            public function collection()
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return [
                    'ID', 'NF/Cupom', 'Loja', 'Data da Venda', 'Cliente', 'CPF Cliente',
                    'Tipo', 'Categoria Motivo', 'Motivo', 'Status',
                    'Valor Itens', 'Valor Reembolso', 'Total da NF',
                    'Código Rastreio', 'Observações',
                    'Criado por', 'Aprovado por', 'Processado por',
                    'Criado em', 'Aprovado em', 'Concluído em',
                    'Cancelado em', 'Motivo Cancelamento',
                ];
            }

            public function map($r): array
            {
                return [
                    $r->id,
                    $r->invoice_number,
                    $r->store_code,
                    $r->movement_date?->format('d/m/Y'),
                    $r->customer_name,
                    $r->cpf_customer,
                    $r->type?->label(),
                    $r->reason_category?->label(),
                    $r->reason?->name,
                    $r->status?->label(),
                    number_format((float) $r->amount_items, 2, ',', '.'),
                    $r->refund_amount !== null
                        ? number_format((float) $r->refund_amount, 2, ',', '.')
                        : '',
                    number_format((float) $r->sale_total, 2, ',', '.'),
                    $r->reverse_tracking_code,
                    $r->notes,
                    $r->createdBy?->name,
                    $r->approvedBy?->name,
                    $r->processedBy?->name,
                    $r->created_at?->format('d/m/Y H:i'),
                    $r->approved_at?->format('d/m/Y H:i'),
                    $r->completed_at?->format('d/m/Y H:i'),
                    $r->cancelled_at?->format('d/m/Y H:i'),
                    $r->cancelled_reason,
                ];
            }
        };

        $filename = 'devolucoes-'.now()->format('Y-m-d-His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    /**
     * Gera o comprovante individual em PDF. Usado pelo botão
     * "Comprovante" no modal de detalhe.
     */
    public function exportPdf(ReturnOrder $order): Response
    {
        $order->load([
            'reason', 'employee', 'store',
            'createdBy', 'approvedBy', 'processedBy',
            'items', 'statusHistory.changedBy',
        ]);

        $pdf = Pdf::loadView('pdf.return-order', [
            'order' => $order,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'devolucao-%d-NF%s-%s.pdf',
            $order->id,
            preg_replace('/[^A-Za-z0-9_-]/', '', $order->invoice_number),
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }
}
