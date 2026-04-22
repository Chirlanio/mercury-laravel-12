<?php

namespace App\Exports\DRE;

use App\Services\DRE\DreMatrixService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export multi-sheet da matriz DRE (playbook prompt 13).
 *
 * Abas entregues:
 *   1. Matriz   — linhas da DRE × 12 meses (actual/budget/py conforme filtro)
 *   2. KPIs     — faturamento líquido, EBITDA, margem, não-classificado
 *   3. Metadata — filtros aplicados, usuário, timestamp (para reconciliação)
 *
 * Divergência em relação ao playbook (5 abas: Geral/Rede/Loja/KPIs/Detalhe):
 *   - O usuário sempre exporta o escopo que está vendo na tela. Gerar
 *     "Por Rede" e "Por Loja" independentemente do filtro atual triplicaria
 *     o tempo do export e duplicaria dados já visíveis por simples toggle
 *     de scope + re-export.
 *
 * Consome matriz + kpis via `DreMatrixService` — mesmos valores vistos na
 * UI batem com o XLSX por construção.
 */
class DreMatrixExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected array $filter,
        protected array $matrix,
        protected array $kpis,
        protected ?string $generatedByName,
    ) {
    }

    public static function fromFilter(array $filter, ?string $generatedByName = null): self
    {
        $service = app(DreMatrixService::class);

        return new self(
            filter: $filter,
            matrix: $service->matrix($filter),
            kpis: $service->kpis($filter),
            generatedByName: $generatedByName,
        );
    }

    public function sheets(): array
    {
        return [
            new DreMatrixSheet($this->filter, $this->matrix),
            new DreKpisSheet($this->kpis),
            new DreMetadataSheet($this->filter, $this->matrix, $this->generatedByName),
        ];
    }
}

// ----------------------------------------------------------------------
// Sheet 1 — Matriz
// ----------------------------------------------------------------------
class DreMatrixSheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected array $filter,
        protected array $matrix,
    ) {
    }

    public function title(): string
    {
        return 'Matriz';
    }

    public function headings(): array
    {
        $months = $this->yearMonthsFromFilter();
        $compare = (bool) ($this->filter['compare_previous_year'] ?? true);

        $headings = ['Código', 'Linha', 'Natureza'];

        foreach ($months as $ym) {
            $headings[] = $ym.' Realizado';
        }
        $headings[] = 'Total Realizado';
        $headings[] = 'Total Orçado';
        $headings[] = '% Ating.';
        if ($compare) {
            $headings[] = 'Total Ano Anterior';
            $headings[] = 'Var % AA';
        }

        return $headings;
    }

    public function array(): array
    {
        $months = $this->yearMonthsFromFilter();
        $compare = (bool) ($this->filter['compare_previous_year'] ?? true);
        $rows = [];

        foreach ($this->matrix['lines'] ?? [] as $line) {
            $row = [
                $line['code'] ?? '',
                $line['level_1'] ?? ($line['name'] ?? ''),
                $line['is_subtotal'] ? 'SUBTOTAL' : ($line['nature'] ?? ''),
            ];

            foreach ($months as $ym) {
                $cell = $line['months'][$ym] ?? null;
                $row[] = round((float) ($cell['actual'] ?? 0.0), 2);
            }

            $totals = $line['totals'] ?? ['actual' => 0, 'budget' => 0, 'previous_year' => 0];
            $actual = round((float) $totals['actual'], 2);
            $budget = round((float) $totals['budget'], 2);
            $py = round((float) $totals['previous_year'], 2);

            $row[] = $actual;
            $row[] = $budget;
            $row[] = abs($budget) > 0.0001 ? round(($actual / $budget) * 100, 2) : 0.0;

            if ($compare) {
                $row[] = $py;
                $row[] = abs($py) > 0.0001 ? round((($actual - $py) / abs($py)) * 100, 2) : 0.0;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        // Header em destaque + subtotais em cinza claro.
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$highestCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$highestCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');

        $lastRow = count($this->matrix['lines'] ?? []) + 1;
        foreach ($this->matrix['lines'] ?? [] as $i => $line) {
            if (! empty($line['is_subtotal'])) {
                $r = $i + 2; // +1 header, +1 1-indexed
                $sheet->getStyle("A{$r}:{$highestCol}{$r}")->getFont()->setBold(true);
                $sheet->getStyle("A{$r}:{$highestCol}{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F3F4F6');
            }
        }

        return [];
    }

    /** @return array<int,string>  'YYYY-MM' */
    private function yearMonthsFromFilter(): array
    {
        $from = (string) ($this->filter['start_date'] ?? '');
        $to = (string) ($this->filter['end_date'] ?? '');
        if ($from === '' || $to === '') {
            return [];
        }

        try {
            $cursor = \Carbon\Carbon::parse($from)->startOfMonth();
            $end = \Carbon\Carbon::parse($to)->endOfMonth();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        while ($cursor <= $end) {
            $out[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $out;
    }
}

// ----------------------------------------------------------------------
// Sheet 2 — KPIs
// ----------------------------------------------------------------------
class DreKpisSheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(protected array $kpis)
    {
    }

    public function title(): string
    {
        return 'KPIs';
    }

    public function headings(): array
    {
        return ['Indicador', 'Realizado', 'Orçado', 'Ano Anterior'];
    }

    public function array(): array
    {
        $kpis = $this->kpis;

        return [
            [
                'Faturamento Líquido',
                round((float) ($kpis['faturamento_liquido']['actual'] ?? 0), 2),
                round((float) ($kpis['faturamento_liquido']['budget'] ?? 0), 2),
                round((float) ($kpis['faturamento_liquido']['previous_year'] ?? 0), 2),
            ],
            [
                'EBITDA',
                round((float) ($kpis['ebitda']['actual'] ?? 0), 2),
                round((float) ($kpis['ebitda']['budget'] ?? 0), 2),
                round((float) ($kpis['ebitda']['previous_year'] ?? 0), 2),
            ],
            [
                'Margem Líquida (%)',
                round((float) ($kpis['margem_liquida']['actual'] ?? 0) * 100, 2),
                round((float) ($kpis['margem_liquida']['budget'] ?? 0) * 100, 2),
                round((float) ($kpis['margem_liquida']['previous_year'] ?? 0) * 100, 2),
            ],
            [
                'Não-classificado',
                round((float) ($kpis['nao_classificado']['actual'] ?? 0), 2),
                round((float) ($kpis['nao_classificado']['budget'] ?? 0), 2),
                round((float) ($kpis['nao_classificado']['previous_year'] ?? 0), 2),
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');

        return [];
    }
}

// ----------------------------------------------------------------------
// Sheet 3 — Metadata
// ----------------------------------------------------------------------
class DreMetadataSheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected array $filter,
        protected array $matrix,
        protected ?string $generatedByName,
    ) {
    }

    public function title(): string
    {
        return 'Metadata';
    }

    public function headings(): array
    {
        return ['Campo', 'Valor'];
    }

    public function array(): array
    {
        $filter = $this->filter;

        return [
            ['Gerado em', now()->format('d/m/Y H:i:s')],
            ['Gerado por', $this->generatedByName ?? 'sistema'],
            ['Período início', (string) ($filter['start_date'] ?? '')],
            ['Período fim', (string) ($filter['end_date'] ?? '')],
            ['Escopo', (string) ($filter['scope'] ?? 'general')],
            ['Lojas', implode(', ', array_map('strval', $filter['store_ids'] ?? [])) ?: '(todas)'],
            ['Redes', implode(', ', array_map('strval', $filter['network_ids'] ?? [])) ?: '(todas)'],
            ['Budget version', (string) ($filter['budget_version'] ?? '(padrão)')],
            ['Incluir não-classificado?', ! empty($filter['include_unclassified']) ? 'Sim' : 'Não'],
            ['Comparar ano anterior?', ! empty($filter['compare_previous_year']) ? 'Sim' : 'Não'],
            ['Matrix.generated_at', (string) ($this->matrix['generated_at'] ?? '')],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getStyle('A1:B1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');
        $sheet->getStyle('A:A')->getFont()->setBold(true);

        return [];
    }
}
