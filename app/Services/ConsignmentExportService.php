<?php

namespace App\Services;

use App\Models\Consignment;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder as QrBuilder;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export de Consignações:
 *  - XLSX (listagem) com 2 abas: "Consignações" (1 linha por consignação)
 *    e "Itens" (1 linha por item com ref, tamanho, quantidades).
 *  - PDF comprovante individual com itens + QR Code apontando para a
 *    página de detalhe (usa endroid/qr-code, renderizado inline como
 *    data URI PNG pra funcionar no dompdf sem dependência de URL).
 */
class ConsignmentExportService
{
    // ==================================================================
    // XLSX — listagem com 2 abas
    // ==================================================================

    /**
     * @param  Builder<Consignment>  $query  Query já filtrada/escopada pelo controller
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query->with(['store', 'employee', 'items'])->get();

        $export = new class($rows) implements WithMultipleSheets {
            public function __construct(public $rows) {}

            public function sheets(): array
            {
                return [
                    new class($this->rows) implements FromCollection, WithHeadings, WithMapping, WithTitle {
                        public function __construct(public $rows) {}

                        public function title(): string
                        {
                            return 'Consignações';
                        }

                        public function collection()
                        {
                            return $this->rows;
                        }

                        public function headings(): array
                        {
                            return [
                                'ID', 'UUID', 'Tipo', 'Status',
                                'Loja', 'Consultor(a)',
                                'Destinatário', 'Documento',
                                'NF Saída', 'Data NF',
                                'Prazo Retorno', 'Dias',
                                'Itens', 'Valor Total',
                                'Devolvidos', 'Valor Dev.',
                                'Vendidos', 'Valor Vend.',
                                'Perdidos', 'Valor Perd.',
                                'Emitida em', 'Finalizada em', 'Cancelada em',
                                'Motivo Cancelamento', 'Observações',
                            ];
                        }

                        public function map($c): array
                        {
                            return [
                                $c->id,
                                $c->uuid,
                                $c->type?->label(),
                                $c->status?->label(),
                                $c->store?->code ? ($c->store->code.' — '.$c->store->name) : '',
                                $c->employee?->name ?? '',
                                $c->recipient_name,
                                $c->recipient_document ?? '',
                                $c->outbound_invoice_number,
                                $c->outbound_invoice_date?->format('d/m/Y'),
                                $c->expected_return_date?->format('d/m/Y'),
                                $c->return_period_days,
                                (int) $c->outbound_items_count,
                                (float) $c->outbound_total_value,
                                (int) $c->returned_items_count,
                                (float) $c->returned_total_value,
                                (int) $c->sold_items_count,
                                (float) $c->sold_total_value,
                                (int) $c->lost_items_count,
                                (float) $c->lost_total_value,
                                $c->issued_at?->format('d/m/Y H:i'),
                                $c->completed_at?->format('d/m/Y H:i'),
                                $c->cancelled_at?->format('d/m/Y H:i'),
                                $c->cancelled_reason ?? '',
                                $c->notes ?? '',
                            ];
                        }
                    },
                    new class($this->rows) implements FromCollection, WithHeadings, WithMapping, WithTitle {
                        public function __construct(public $rows) {}

                        public function title(): string
                        {
                            return 'Itens';
                        }

                        public function collection()
                        {
                            // Flatten — 1 linha por item (filho) preservando vínculo com consignação
                            return $this->rows->flatMap(function ($c) {
                                return $c->items->map(fn ($item) => (object) [
                                    'consignment_id' => $c->id,
                                    'consignment_uuid' => $c->uuid,
                                    'recipient_name' => $c->recipient_name,
                                    'outbound_invoice_number' => $c->outbound_invoice_number,
                                    'item' => $item,
                                ]);
                            });
                        }

                        public function headings(): array
                        {
                            return [
                                'Consignação #', 'UUID',
                                'Destinatário', 'NF Saída',
                                'Referência', 'EAN', 'Tamanho', 'Descrição',
                                'Quantidade', 'Valor Unit.', 'Valor Total',
                                'Devolvidos', 'Vendidos', 'Perdidos', 'Pendente',
                                'Status Item',
                            ];
                        }

                        public function map($row): array
                        {
                            $it = $row->item;

                            return [
                                $row->consignment_id,
                                $row->consignment_uuid,
                                $row->recipient_name,
                                $row->outbound_invoice_number,
                                $it->reference,
                                $it->barcode ?? '',
                                $it->size_label ?? $it->size_cigam_code ?? '',
                                $it->description ?? '',
                                (int) $it->quantity,
                                (float) $it->unit_value,
                                (float) $it->total_value,
                                (int) $it->returned_quantity,
                                (int) $it->sold_quantity,
                                (int) $it->lost_quantity,
                                (int) $it->pending_quantity,
                                $it->status?->label(),
                            ];
                        }
                    },
                ];
            }
        };

        $filename = 'consignacoes-'.now()->format('Y-m-d-His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    // ==================================================================
    // PDF — comprovante individual com QR Code
    // ==================================================================

    /**
     * Gera comprovante em PDF da consignação. Inclui itens, valores,
     * dados do destinatário e QR Code apontando para a URL de detalhe
     * (scan do cliente abre diretamente a consignação no app).
     */
    public function exportPdf(Consignment $consignment): Response
    {
        $consignment->load([
            'store', 'employee',
            'items.product', 'items.variant',
            'createdBy',
        ]);

        $detailUrl = route('consignments.index').'?id='.$consignment->id;

        // QR Code como data URI PNG — evita dependência de URL externa no dompdf
        $qrDataUri = $this->generateQrDataUri($detailUrl);

        $pdf = Pdf::loadView('pdf.consignment', [
            'consignment' => $consignment,
            'qrDataUri' => $qrDataUri,
            'detailUrl' => $detailUrl,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'consignacao-%d-%s-%s.pdf',
            $consignment->id,
            preg_replace('/[^A-Za-z0-9_-]/', '', $consignment->recipient_name ?? 'sem-nome'),
            now()->format('Y-m-d'),
        );

        return $pdf->download($filename);
    }

    /**
     * Gera QR Code PNG inline como data URI (base64). Facilita o embed
     * no dompdf via <img src="data:image/png;base64,...">.
     *
     * Endroid QR Code v6+ usa construtor (sem fluent ::create()).
     */
    protected function generateQrDataUri(string $url): string
    {
        $builder = new QrBuilder(
            data: $url,
            size: 200,
            margin: 8,
        );

        return $builder->build()->getDataUri();
    }
}
