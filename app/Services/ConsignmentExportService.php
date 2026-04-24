<?php

namespace App\Services;

use App\Enums\ConsignmentStatus;
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
        $rows = $query->with(['store', 'employee', 'items', 'returns'])->get();

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
                                'ID', 'Tipo', 'Status',
                                'Loja', 'Consultor(a)',
                                'Destinatário', 'Documento',
                                'NF Saída', 'Data NF',
                                'NF Retorno',
                                'Prazo Retorno', 'Dias Consignado',
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
                                $c->type?->label(),
                                $c->status?->label(),
                                $c->store?->code ? ($c->store->code.' — '.$c->store->name) : '',
                                $c->employee?->name ?? '',
                                $c->recipient_name,
                                $c->recipient_document ?? '',
                                $c->outbound_invoice_number,
                                $c->outbound_invoice_date?->format('d/m/Y'),
                                \App\Services\ConsignmentExportService::formatReturnInvoices($c),
                                $c->expected_return_date?->format('d/m/Y'),
                                \App\Services\ConsignmentExportService::calculateDaysOnConsignment($c),
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
                                $returnInvoices = \App\Services\ConsignmentExportService::formatReturnInvoices($c);

                                return $c->items->map(fn ($item) => (object) [
                                    'consignment_id' => $c->id,
                                    'recipient_name' => $c->recipient_name,
                                    'outbound_invoice_number' => $c->outbound_invoice_number,
                                    'return_invoices' => $returnInvoices,
                                    'item' => $item,
                                ]);
                            });
                        }

                        public function headings(): array
                        {
                            return [
                                'Consignação #',
                                'Destinatário', 'NF Saída', 'NF Retorno',
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
                                $row->recipient_name,
                                $row->outbound_invoice_number,
                                $row->return_invoices,
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

    /**
     * Formata as NFs de retorno (pode haver múltiplas em retornos parciais)
     * no padrão "numero (dd/mm/yyyy)", separadas por " | ". Vazio se não
     * houver retorno registrado ainda. Usa a relação já carregada quando
     * disponível pra evitar query extra.
     */
    public static function formatReturnInvoices(Consignment $c): string
    {
        $returns = $c->relationLoaded('returns')
            ? $c->returns
            : $c->returns()->get();

        if ($returns->isEmpty()) {
            return '';
        }

        return $returns
            ->sortBy('return_date')
            ->map(function ($r) {
                $number = $r->return_invoice_number ?: '(sem nº)';
                $date = $r->return_date ? \Carbon\Carbon::parse($r->return_date)->format('d/m/Y') : '—';

                return $number.' ('.$date.')';
            })
            ->implode(' | ');
    }

    /**
     * Quantos dias o produto esteve consignado. Conta o dia da saída como
     * dia 1 fechado (independe de horário). Data de referência:
     *  - último retorno (ConsignmentReturn::return_date), se existir;
     *  - completed_at (se finalizada sem retornos);
     *  - cancelled_at (se cancelada sem retornos);
     *  - hoje (ainda em aberto).
     *
     * Usado tanto pelo export como por outras views que queiram exibir
     * "dias consignado" sem recalcular a lógica.
     */
    public static function calculateDaysOnConsignment(Consignment $c): ?int
    {
        if (! $c->outbound_invoice_date) {
            return null;
        }

        $outbound = $c->outbound_invoice_date->copy()->startOfDay();

        $reference = null;
        if ($c->relationLoaded('returns') && $c->returns->isNotEmpty()) {
            $reference = $c->returns
                ->pluck('return_date')
                ->filter()
                ->sortDesc()
                ->first();
        } elseif (! $c->relationLoaded('returns') && $c->returns()->exists()) {
            $reference = $c->returns()->max('return_date');
            $reference = $reference ? \Carbon\Carbon::parse($reference) : null;
        }

        if (! $reference) {
            if ($c->status === ConsignmentStatus::COMPLETED && $c->completed_at) {
                $reference = $c->completed_at;
            } elseif ($c->status === ConsignmentStatus::CANCELLED && $c->cancelled_at) {
                $reference = $c->cancelled_at;
            } else {
                $reference = now();
            }
        }

        $referenceDay = $reference instanceof \Carbon\Carbon
            ? $reference->copy()->startOfDay()
            : \Carbon\Carbon::parse($reference)->startOfDay();

        // diffInDays é absoluto (positivo). +1 para contar a saída como dia 1.
        return (int) $outbound->diffInDays($referenceDay) + 1;
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
