<?php

namespace App\Services;

use App\Models\ManagementClass;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManagementClassExportService
{
    /**
     * @param  Builder<ManagementClass>  $query
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query
            ->with(['parent', 'accountingClass', 'costCenter'])
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
                    'Código Conta Contábil', 'Nome Conta Contábil',
                    'Código Centro de Custo', 'Nome Centro de Custo',
                    'Aceita Lançamentos', 'Ordem', 'Ativo',
                    'Criado em', 'Atualizado em',
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
                    $c->accountingClass?->code,
                    $c->accountingClass?->name,
                    $c->costCenter?->code,
                    $c->costCenter?->name,
                    $c->accepts_entries ? 'Sim' : 'Não',
                    $c->sort_order,
                    $c->is_active ? 'Sim' : 'Não',
                    $c->created_at?->format('d/m/Y H:i'),
                    $c->updated_at?->format('d/m/Y H:i'),
                ];
            }
        };

        $filename = 'plano-gerencial-'.now()->format('Ymd_His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }
}
