<?php

namespace App\Services;

use App\Enums\DamageMatchStatus;
use App\Models\DamagedProduct;
use App\Models\DamagedProductMatch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
 * Exporta produtos avariados para Excel (2 abas: Registros + Matches) ou
 * PDF individual (laudo da avaria com fotos).
 *
 * Excel: paridade com paginação do v1, mas com dados normalizados +
 * coluna de loja por código + estatísticas por status.
 * PDF: 1 registro por arquivo, formato A4 com seção de fotos embedded
 * via DomPDF.
 */
class DamagedProductExportService
{
    /**
     * @param  Builder<DamagedProduct>  $query  Query JÁ filtrada/escopada pelo controller
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = (clone $query)
            ->with(['store:id,code,name', 'damageType:id,name', 'createdBy:id,name', 'updatedBy:id,name'])
            ->get();

        $matches = DamagedProductMatch::query()
            ->whereIn('product_a_id', $rows->pluck('id'))
            ->orWhereIn('product_b_id', $rows->pluck('id'))
            ->with([
                'productA:id,product_reference,store_id',
                'productB:id,product_reference,store_id',
                'productA.store:id,code',
                'productB.store:id,code',
                'suggestedOriginStore:id,code',
                'suggestedDestinationStore:id,code',
                'transfer:id,invoice_number,status',
                'respondedBy:id,name',
            ])
            ->get();

        $export = new class($rows, $matches) implements WithMultipleSheets {
            public function __construct(public Collection $rows, public Collection $matches) {}

            public function sheets(): array
            {
                return [
                    'Registros' => new class($this->rows) implements FromCollection, WithHeadings, WithMapping, WithTitle {
                        public function __construct(public Collection $rows) {}

                        public function title(): string
                        {
                            return 'Registros';
                        }

                        public function collection()
                        {
                            return $this->rows;
                        }

                        public function headings(): array
                        {
                            return [
                                'ID', 'ULID', 'Loja (código)', 'Loja (nome)', 'Referência', 'Descrição',
                                'Cor', 'Marca', 'Tamanho do par',
                                'Par trocado?', 'Pé trocado', 'Tamanho real', 'Tamanho esperado',
                                'Avariado?', 'Tipo de dano', 'Pé(s) avariado(s)', 'Descrição do dano',
                                'Reparável?', 'Custo estimado de reparo',
                                'Status', 'Cancelado em', 'Motivo cancelamento', 'Resolvido em',
                                'Cadastrado por', 'Cadastrado em', 'Atualizado por', 'Atualizado em',
                                'Observações',
                            ];
                        }

                        public function map($p): array
                        {
                            return [
                                $p->id,
                                $p->ulid,
                                $p->store?->code,
                                $p->store?->name,
                                $p->product_reference,
                                $p->product_name,
                                $p->product_color,
                                $p->brand_cigam_code,
                                $p->product_size,
                                $p->is_mismatched ? 'Sim' : 'Não',
                                $p->mismatched_foot?->label(),
                                $p->mismatched_actual_size,
                                $p->mismatched_expected_size,
                                $p->is_damaged ? 'Sim' : 'Não',
                                $p->damageType?->name,
                                $p->damaged_foot?->label(),
                                $p->damage_description,
                                $p->is_repairable ? 'Sim' : 'Não',
                                $p->estimated_repair_cost !== null
                                    ? number_format((float) $p->estimated_repair_cost, 2, ',', '.')
                                    : '',
                                $p->status?->label(),
                                $p->cancelled_at?->format('d/m/Y H:i'),
                                $p->cancel_reason,
                                $p->resolved_at?->format('d/m/Y H:i'),
                                $p->createdBy?->name,
                                $p->created_at?->format('d/m/Y H:i'),
                                $p->updatedBy?->name,
                                $p->updated_at?->format('d/m/Y H:i'),
                                $p->notes,
                            ];
                        }
                    },

                    'Matches' => new class($this->matches) implements FromCollection, WithHeadings, WithMapping, WithTitle {
                        public function __construct(public Collection $matches) {}

                        public function title(): string
                        {
                            return 'Matches';
                        }

                        public function collection()
                        {
                            return $this->matches;
                        }

                        public function headings(): array
                        {
                            return [
                                'ID Match', 'Tipo', 'Status', 'Score',
                                'Produto A (ID)', 'Produto A (referência)', 'Loja A',
                                'Produto B (ID)', 'Produto B (referência)', 'Loja B',
                                'Origem sugerida', 'Destino sugerido',
                                'Transferência', 'NF Transferência', 'Status Transferência',
                                'Motivo Rejeição', 'Respondido por', 'Respondido em',
                                'Notificado em', 'Resolvido em', 'Criado em',
                            ];
                        }

                        public function map($m): array
                        {
                            return [
                                $m->id,
                                $m->match_type?->label(),
                                $m->status?->label(),
                                number_format((float) $m->match_score, 2, ',', '.'),
                                $m->product_a_id,
                                $m->productA?->product_reference,
                                $m->productA?->store?->code,
                                $m->product_b_id,
                                $m->productB?->product_reference,
                                $m->productB?->store?->code,
                                $m->suggestedOriginStore?->code,
                                $m->suggestedDestinationStore?->code,
                                $m->transfer_id ?? '',
                                $m->transfer?->invoice_number,
                                $m->transfer?->status,
                                $m->reject_reason,
                                $m->respondedBy?->name,
                                $m->responded_at?->format('d/m/Y H:i'),
                                $m->notified_at?->format('d/m/Y H:i'),
                                $m->resolved_at?->format('d/m/Y H:i'),
                                $m->created_at?->format('d/m/Y H:i'),
                            ];
                        }
                    },
                ];
            }
        };

        $filename = 'produtos-avariados-' . now()->format('Y-m-d-His') . '.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }

    /**
     * Gera o laudo individual em PDF para um produto avariado específico.
     */
    public function exportPdf(DamagedProduct $product): Response
    {
        $product->load([
            'store:id,code,name',
            'damageType:id,name',
            'photos',
            'createdBy:id,name',
            'updatedBy:id,name',
            'cancelledBy:id,name',
            'statusHistory.actor:id,name',
        ]);

        $matches = DamagedProductMatch::query()
            ->forProduct($product->id)
            ->with([
                'productA.store:id,code',
                'productB.store:id,code',
                'suggestedOriginStore:id,code',
                'suggestedDestinationStore:id,code',
                'transfer:id,invoice_number,status',
            ])
            ->get();

        $pdf = Pdf::loadView('pdf.damaged-product', [
            'product' => $product,
            'matches' => $matches,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'avariado-%d-%s-%s.pdf',
            $product->id,
            preg_replace('/[^A-Za-z0-9_-]/', '', $product->product_reference),
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }
}
