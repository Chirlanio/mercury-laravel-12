<?php

namespace App\Services;

use App\Models\Reversal;
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
 * Exporta estornos para Excel (listagem com filtros) ou PDF (comprovante
 * individual para anexar ao ressarcimento com a adquirente).
 *
 * Excel: paridade com paginação do v1 (usa streaming do maatwebsite).
 * PDF individual: 1 estorno por arquivo, formato de comprovante em A4.
 */
class ReversalExportService
{
    /**
     * @param  Builder<Reversal>  $query  Query JÁ filtrada/escopada pelo controller
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query
            ->with(['reason', 'paymentType', 'employee', 'store', 'createdBy', 'authorizedBy', 'processedBy'])
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
                    'Consultor', 'Total da NF', 'Tipo', 'Modo', 'Valor Original',
                    'Valor Correto', 'Valor Estorno', 'Status', 'Motivo',
                    'Forma de Pagamento', 'Bandeira', 'Parcelas', 'NSU', 'Autorização',
                    'Tipo Chave PIX', 'Chave PIX', 'Beneficiário', 'Banco PIX',
                    'Previsão Devolução', 'Criado por', 'Autorizado por', 'Processado por',
                    'Criado em', 'Estornado em', 'Cancelado em', 'Motivo Cancelamento',
                    'Observações',
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
                    $r->cpf_consultant,
                    number_format((float) $r->sale_total, 2, ',', '.'),
                    $r->type?->label(),
                    $r->partial_mode?->label(),
                    number_format((float) $r->amount_original, 2, ',', '.'),
                    $r->amount_correct !== null ? number_format((float) $r->amount_correct, 2, ',', '.') : '',
                    number_format((float) $r->amount_reversal, 2, ',', '.'),
                    $r->status?->label(),
                    $r->reason?->name,
                    $r->paymentType?->name,
                    $r->payment_brand,
                    $r->installments_count,
                    $r->nsu,
                    $r->authorization_code,
                    $r->pix_key_type,
                    $r->pix_key,
                    $r->pix_beneficiary,
                    $r->pixBank?->bank_name,
                    $r->expected_refund_date?->format('d/m/Y'),
                    $r->createdBy?->name,
                    $r->authorizedBy?->name,
                    $r->processedBy?->name,
                    $r->created_at?->format('d/m/Y H:i'),
                    $r->reversed_at?->format('d/m/Y H:i'),
                    $r->cancelled_at?->format('d/m/Y H:i'),
                    $r->cancelled_reason,
                    $r->notes,
                ];
            }
        };

        $filename = 'estornos-'.now()->format('Y-m-d-His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    /**
     * Gera o comprovante individual em PDF para um estorno específico.
     * Usado pelo botão "Comprovante" no modal de detalhe.
     */
    public function exportPdf(Reversal $reversal): Response
    {
        $reversal->load([
            'reason', 'paymentType', 'pixBank', 'employee', 'store',
            'createdBy', 'authorizedBy', 'processedBy',
            'items', 'statusHistory.changedBy',
        ]);

        $pdf = Pdf::loadView('pdf.reversal', [
            'reversal' => $reversal,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'estorno-%d-NF%s-%s.pdf',
            $reversal->id,
            preg_replace('/[^A-Za-z0-9_-]/', '', $reversal->invoice_number),
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }
}
