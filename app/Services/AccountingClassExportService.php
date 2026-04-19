<?php

namespace App\Services;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Models\AccountingClass;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exporta o plano de contas para XLSX. Ordena por `code` para preservar
 * ordem hierárquica natural (3.1 < 3.1.01 < 3.1.01.001).
 */
class AccountingClassExportService
{
    /**
     * @param  Builder<AccountingClass>  $query  Query já filtrada pelo controller.
     */
    public function exportExcel(Builder $query): BinaryFileResponse
    {
        $rows = $query
            ->with('parent')
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
                    'Natureza', 'Grupo DRE',
                    'Aceita Lançamentos', 'Ordem', 'Ativo',
                    'Criado em', 'Atualizado em',
                ];
            }

            public function map($c): array
            {
                $nature = $c->nature instanceof AccountingNature ? $c->nature : null;
                $dreGroup = $c->dre_group instanceof DreGroup ? $c->dre_group : null;

                return [
                    $c->id,
                    $c->code,
                    $c->name,
                    $c->description,
                    $c->parent?->code,
                    $c->parent?->name,
                    $nature?->label() ?? $c->nature,
                    $dreGroup?->label() ?? $c->dre_group,
                    $c->accepts_entries ? 'Sim' : 'Não',
                    $c->sort_order,
                    $c->is_active ? 'Sim' : 'Não',
                    $c->created_at?->format('d/m/Y H:i'),
                    $c->updated_at?->format('d/m/Y H:i'),
                ];
            }
        };

        $filename = 'plano-de-contas-'.now()->format('Ymd_His').'.xlsx';

        return ExcelFacade::download($export, $filename, Excel::XLSX);
    }
}
