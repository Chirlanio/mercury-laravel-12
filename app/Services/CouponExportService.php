<?php

namespace App\Services;

use App\Models\Coupon;
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
 * Exporta cupons para Excel (listagem com filtros aplicados) ou PDF
 * (comprovante individual ao anexar em comunicações/e-mail).
 *
 * CPF é sempre mascarado na exportação (LGPD). Para receber o CPF
 * em claro, o operador precisa acessar o cupom individualmente via UI.
 */
class CouponExportService
{
    /**
     * @param  Builder<Coupon>  $query  Query já filtrada/escopada pelo controller
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query
            ->with(['employee', 'store', 'socialMedia', 'createdBy', 'issuedBy'])
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
                    'ID', 'Tipo', 'Status',
                    'Beneficiário', 'CPF (mascarado)',
                    'Loja', 'Cidade', 'Rede Social',
                    'Cupom Sugerido', 'Cupom Emitido', 'Campanha',
                    'Válido de', 'Válido até', 'Usos', 'Máximo',
                    'Criado por', 'Emitido por',
                    'Criado em', 'Solicitado em', 'Emitido em', 'Cancelado em',
                    'Motivo do Cancelamento',
                ];
            }

            public function map($c): array
            {
                return [
                    $c->id,
                    $c->type?->label(),
                    $c->status?->label(),
                    $c->beneficiary_name,
                    $c->masked_cpf,
                    $c->store_code ? ($c->store_code.' — '.($c->store?->name ?? '')) : '',
                    $c->city,
                    $c->socialMedia?->name,
                    $c->suggested_coupon,
                    $c->coupon_site,
                    $c->campaign_name,
                    $c->valid_from?->format('d/m/Y'),
                    $c->valid_until?->format('d/m/Y'),
                    (int) $c->usage_count,
                    $c->max_uses,
                    $c->createdBy?->name,
                    $c->issuedBy?->name,
                    $c->created_at?->format('d/m/Y H:i'),
                    $c->requested_at?->format('d/m/Y H:i'),
                    $c->issued_at?->format('d/m/Y H:i'),
                    $c->cancelled_at?->format('d/m/Y H:i'),
                    $c->cancelled_reason,
                ];
            }
        };

        $filename = 'cupons-'.now()->format('Y-m-d-His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    /**
     * Gera comprovante individual em PDF. CPF exibido mascarado.
     */
    public function exportPdf(Coupon $coupon): Response
    {
        $coupon->load([
            'employee', 'store', 'socialMedia',
            'createdBy', 'issuedBy',
            'statusHistory.changedBy',
        ]);

        $pdf = Pdf::loadView('pdf.coupon', [
            'coupon' => $coupon,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'cupom-%d-%s-%s.pdf',
            $coupon->id,
            preg_replace('/[^A-Za-z0-9_-]/', '', $coupon->coupon_site ?? $coupon->suggested_coupon ?? 'sem-codigo'),
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }
}
