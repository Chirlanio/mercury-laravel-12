<?php

namespace App\Services;

use App\Models\TravelExpense;
use Barryvdh\DomPDF\Facade\Pdf;
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
 * Export de Verbas de Viagem:
 *  - XLSX (listagem) com 2 abas:
 *      "Verbas" (1 linha por solicitação com cabeçalho + valores + status)
 *      "Prestação" (1 linha por item de prestação com vínculo à verba)
 *  - PDF comprovante individual: 1 verba por arquivo com seções de
 *    dados, pagamento, prestação e timeline.
 */
class TravelExpenseExportService
{
    // ==================================================================
    // XLSX — listagem com 2 abas
    // ==================================================================

    /**
     * @param  Builder<TravelExpense>  $query  Query já filtrada/escopada pelo controller
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query
            ->with([
                'employee:id,name',
                'store:id,code,name',
                'bank:id,bank_name',
                'pixType:id,name',
                'createdBy:id,name',
                'approver:id,name',
                'items.typeExpense:id,name',
            ])
            ->get();

        $export = new class($rows) implements WithMultipleSheets {
            public function __construct(public $rows) {}

            public function sheets(): array
            {
                return [
                    new class($this->rows) implements FromCollection, WithHeadings, WithMapping, WithTitle {
                        public function __construct(public $rows) {}

                        public function title(): string
                        {
                            return 'Verbas';
                        }

                        public function collection()
                        {
                            return $this->rows;
                        }

                        public function headings(): array
                        {
                            return [
                                'ID', 'Beneficiado', 'Loja', 'Solicitante',
                                'Origem', 'Destino', 'Saída', 'Retorno', 'Dias',
                                'Diária', 'Valor Verba', 'Total Prestado', 'Saldo',
                                'Status Solicitação', 'Status Prestação',
                                'Banco', 'Agência', 'Conta', 'Tipo PIX', 'Chave PIX',
                                'Solicitada em', 'Submetida em', 'Aprovada em', 'Aprovador',
                                'Rejeitada em', 'Motivo Rejeição',
                                'Finalizada em', 'Cancelada em', 'Motivo Cancelamento',
                                'Cliente/Contato', 'Justificativa',
                            ];
                        }

                        public function map($te): array
                        {
                            $accounted = (float) $te->items->sum('value');
                            $balance = (float) $te->value - $accounted;

                            return [
                                $te->id,
                                $te->employee?->name ?? '',
                                $te->store?->code ? ($te->store->code.' — '.$te->store->name) : ($te->store_code ?? ''),
                                $te->createdBy?->name ?? '',
                                $te->origin,
                                $te->destination,
                                $te->initial_date?->format('d/m/Y'),
                                $te->end_date?->format('d/m/Y'),
                                $te->days_count,
                                (float) $te->daily_rate,
                                (float) $te->value,
                                $accounted,
                                $balance,
                                $te->status?->label(),
                                $te->accountability_status?->label(),
                                $te->bank?->bank_name ?? '',
                                $te->bank_branch ?? '',
                                $te->bank_account ?? '',
                                $te->pixType?->name ?? '',
                                $te->pix_key ?? '', // accessor decripta
                                $te->created_at?->format('d/m/Y H:i'),
                                $te->submitted_at?->format('d/m/Y H:i'),
                                $te->approved_at?->format('d/m/Y H:i'),
                                $te->approver?->name ?? '',
                                $te->rejected_at?->format('d/m/Y H:i'),
                                $te->rejection_reason ?? '',
                                $te->finalized_at?->format('d/m/Y H:i'),
                                $te->cancelled_at?->format('d/m/Y H:i'),
                                $te->cancelled_reason ?? '',
                                $te->client_name ?? '',
                                $te->description ?? '',
                            ];
                        }
                    },
                    new class($this->rows) implements FromCollection, WithHeadings, WithMapping, WithTitle {
                        public function __construct(public $rows) {}

                        public function title(): string
                        {
                            return 'Prestação';
                        }

                        public function collection()
                        {
                            // 1 linha por item, preservando vínculo com a verba
                            return $this->rows->flatMap(function ($te) {
                                return $te->items->map(function ($item) use ($te) {
                                    return (object) [
                                        'expense_id' => $te->id,
                                        'expense_route' => "{$te->origin} → {$te->destination}",
                                        'employee' => $te->employee?->name ?? '',
                                        'store_label' => $te->store?->code
                                            ? ($te->store->code.' — '.$te->store->name)
                                            : ($te->store_code ?? ''),
                                        'item' => $item,
                                    ];
                                });
                            });
                        }

                        public function headings(): array
                        {
                            return [
                                'ID Verba', 'Trecho', 'Beneficiado', 'Loja',
                                'Data Despesa', 'Tipo', 'Valor',
                                'NF/Recibo', 'Descrição', 'Tem Comprovante',
                            ];
                        }

                        public function map($row): array
                        {
                            return [
                                $row->expense_id,
                                $row->expense_route,
                                $row->employee,
                                $row->store_label,
                                $row->item->expense_date?->format('d/m/Y'),
                                $row->item->typeExpense?->name ?? '',
                                (float) $row->item->value,
                                $row->item->invoice_number ?? '',
                                $row->item->description,
                                $row->item->attachment_path ? 'Sim' : 'Não',
                            ];
                        }
                    },
                ];
            }
        };

        $filename = 'verbas-viagem-'.now()->format('Y-m-d-His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    // ==================================================================
    // PDF — comprovante individual
    // ==================================================================

    public function exportPdf(TravelExpense $te): Response
    {
        $te->load([
            'employee:id,name',
            'store:id,code,name',
            'bank:id,bank_name,cod_bank',
            'pixType:id,name',
            'createdBy:id,name',
            'approver:id,name',
            'items.typeExpense:id,name',
            'statusHistory.changedBy:id,name',
        ]);

        $pdf = Pdf::loadView('pdf.travel-expense', [
            'expense' => $te,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'verba-%d-%s-%s-%s.pdf',
            $te->id,
            preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '_', $te->origin ?: 'origem')),
            preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '_', $te->destination ?: 'destino')),
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }
}
