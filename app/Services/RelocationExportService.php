<?php

namespace App\Services;

use App\Models\Relocation;
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
 * Exporta remanejos para:
 *  - XLSX com 2 abas (Cabeçalho + Itens) — listagem filtrada
 *  - PDF Romaneio de Separação — 1 remanejo, A4 retrato
 *
 * Romaneio é o documento físico que acompanha o fardo da loja origem
 * para a destino. Inclui assinaturas e checkbox de conferência.
 */
class RelocationExportService
{
    /**
     * @param  Builder<Relocation>  $query  Query JÁ filtrada/escopada pelo controller
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query
            ->with([
                'type', 'originStore', 'destinationStore', 'createdBy',
                'approvedBy', 'separatedBy', 'receivedBy', 'transfer', 'items',
            ])
            ->get();

        $export = new RelocationsMultiSheetExport($rows);

        $filename = 'remanejos-'.now()->format('Y-m-d-His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    /**
     * Gera o Romaneio de Separação em PDF para um remanejo específico.
     * Disponível a partir de `approved` (loja origem precisa pra separar).
     */
    public function exportRomaneioPdf(Relocation $relocation): Response
    {
        $relocation->load([
            'type', 'originStore', 'destinationStore', 'transfer',
            'createdBy', 'approvedBy', 'separatedBy', 'receivedBy',
            'items',
            'statusHistory.changedBy',
        ]);

        $pdf = Pdf::loadView('pdf.relocation-romaneio', [
            'relocation' => $relocation,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'romaneio-remanejo-%d-%s.pdf',
            $relocation->id,
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }
}

/**
 * Workbook com 2 abas: Cabeçalho dos remanejos + Linhas de itens.
 *
 * @internal
 */
class RelocationsMultiSheetExport implements WithMultipleSheets
{
    public function __construct(public $rows) {}

    public function sheets(): array
    {
        return [
            new RelocationHeadersSheet($this->rows),
            new RelocationItemsSheet($this->rows),
        ];
    }
}

/**
 * Aba 1 — Cabeçalho dos remanejos.
 *
 * @internal
 */
class RelocationHeadersSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(public $rows) {}

    public function title(): string
    {
        return 'Remanejos';
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'ID', 'ULID', 'Título', 'Tipo', 'Status',
            'Loja Origem', 'Loja Destino', 'Prioridade', 'Prazo (dias)',
            'NF Transferência', 'Data NF',
            'Total Solicitado', 'Total Recebido', 'Atendimento %',
            'Transfer ID', 'CIGAM Saída em', 'CIGAM Entrada em',
            'Solicitado em', 'Aprovado em', 'Separado em',
            'Em Trânsito em', 'Concluído em',
            'Criado por', 'Aprovado por', 'Separado por', 'Recebido por',
            'Observações',
        ];
    }

    public function map($r): array
    {
        return [
            $r->id,
            $r->ulid,
            $r->title,
            $r->type?->name,
            $r->status?->label(),
            $r->originStore ? "{$r->originStore->code} — {$r->originStore->name}" : '',
            $r->destinationStore ? "{$r->destinationStore->code} — {$r->destinationStore->name}" : '',
            $r->priority?->label(),
            $r->deadline_days,
            $r->invoice_number,
            $r->invoice_date?->format('d/m/Y'),
            $r->total_requested,
            $r->total_received,
            $r->fulfillment_percentage,
            $r->transfer_id,
            $r->cigam_dispatched_at?->format('d/m/Y H:i'),
            $r->cigam_received_at?->format('d/m/Y H:i'),
            $r->requested_at?->format('d/m/Y H:i'),
            $r->approved_at?->format('d/m/Y H:i'),
            $r->separated_at?->format('d/m/Y H:i'),
            $r->in_transit_at?->format('d/m/Y H:i'),
            $r->completed_at?->format('d/m/Y H:i'),
            $r->createdBy?->name,
            $r->approvedBy?->name,
            $r->separatedBy?->name,
            $r->receivedBy?->name,
            $r->observations,
        ];
    }
}

/**
 * Aba 2 — Linhas de itens. Uma linha por (relocation_id, item_id).
 *
 * @internal
 */
class RelocationItemsSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(public $rows) {}

    public function title(): string
    {
        return 'Itens';
    }

    public function collection()
    {
        // Achata: 1 linha por item, com FK e contexto do remanejo
        $flat = collect();
        foreach ($this->rows as $r) {
            foreach ($r->items as $item) {
                $flat->push((object) [
                    'relocation_id' => $r->id,
                    'relocation_ulid' => $r->ulid,
                    'origin_code' => $r->originStore?->code,
                    'destination_code' => $r->destinationStore?->code,
                    'status' => $r->status?->label(),
                    'item' => $item,
                ]);
            }
        }
        return $flat;
    }

    public function headings(): array
    {
        return [
            'Remanejo ID', 'Remanejo ULID',
            'Origem', 'Destino', 'Status Remanejo',
            'Referência', 'Produto', 'Cor', 'Tamanho', 'Código de barras',
            'Solicitado', 'Separado', 'Recebido',
            'Saída CIGAM (Origem)', 'Entrada CIGAM (Destino)',
            'Motivo Divergência', 'Observação',
        ];
    }

    public function map($row): array
    {
        $i = $row->item;
        return [
            $row->relocation_id,
            $row->relocation_ulid,
            $row->origin_code,
            $row->destination_code,
            $row->status,
            $i->product_reference,
            $i->product_name,
            $i->product_color,
            $i->size,
            $i->barcode,
            $i->qty_requested,
            $i->qty_separated,
            $i->qty_received,
            $i->dispatched_quantity,
            $i->received_quantity,
            $i->reason_code,
            $i->observations,
        ];
    }
}
