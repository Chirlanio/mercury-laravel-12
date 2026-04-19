<?php

namespace App\Services;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exporta centros de custo para XLSX.
 */
class CostCenterExportService
{
    /**
     * @param  Builder<CostCenter>  $query  Query já filtrada pelo controller.
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query
            ->with(['manager', 'parent'])
            ->orderBy('code')
            ->get();

        $export = new class($rows) implements FromCollection, WithHeadings, WithMapping
        {
            public function __construct(public $rows) {}

            public function collection()
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return [
                    'ID', 'Código', 'Nome', 'Descrição',
                    'Código Pai', 'Nome Pai',
                    'Responsável', 'Área (ID)',
                    'Ativo', 'Criado em', 'Atualizado em',
                ];
            }

            public function map($c): array
            {
                return [
                    $c->id,
                    $c->code,
                    $c->name,
                    $c->description,
                    $c->parent?->code,
                    $c->parent?->name,
                    $c->manager?->name,
                    $c->area_id,
                    $c->is_active ? 'Sim' : 'Não',
                    $c->created_at?->format('d/m/Y H:i'),
                    $c->updated_at?->format('d/m/Y H:i'),
                ];
            }
        };

        $filename = 'centros-de-custo-'.now()->format('Ymd_His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }
}
