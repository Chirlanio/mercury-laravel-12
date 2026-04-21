<?php

namespace App\Exports;

use App\Models\BudgetUpload;
use App\Services\BudgetConsumptionService;
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
 * Export consolidado do Orçamento em xlsx multi-sheet — Fase 6.
 *
 * 6 sheets:
 *   1. Resumo Anual       — KPIs de previsto/comprometido/realizado
 *   2. Por Centro de Custo — agregado
 *   3. Por Conta Contábil  — agregado
 *   4. Por Área            — agregado pelo area_department_id (Fase 5)
 *   5. Por Mês             — 12 meses × 3 métricas
 *   6. Detalhe por Item    — tabela completa linha-a-linha
 *
 * Reusa o BudgetConsumptionService — mesmos valores que aparecem no
 * dashboard vão estar no xlsx, batendo por construção.
 */
class BudgetExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected BudgetUpload $budget,
        protected array $consumption,
    ) {}

    public static function fromBudget(BudgetUpload $budget, BudgetConsumptionService $service): self
    {
        return new self($budget, $service->getConsumption($budget));
    }

    public function sheets(): array
    {
        return [
            new BudgetSummarySheet($this->budget, $this->consumption),
            new BudgetByDimensionSheet('Por Centro de Custo', $this->consumption['by_cost_center'] ?? [], 'Centro de Custo'),
            new BudgetByDimensionSheet('Por Conta Contábil', $this->consumption['by_accounting_class'] ?? [], 'Conta Contábil'),
            new BudgetByAreaSheet($this->budget, $this->consumption),
            new BudgetByMonthSheet($this->consumption),
            new BudgetItemsSheet($this->consumption['by_item'] ?? []),
        ];
    }
}

/**
 * Sheet 1 — Resumo Anual. Cabeçalho do orçamento + KPIs de consumo.
 */
class BudgetSummarySheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected BudgetUpload $budget,
        protected array $consumption,
    ) {}

    public function title(): string
    {
        return 'Resumo';
    }

    public function headings(): array
    {
        return ['Indicador', 'Valor'];
    }

    public function array(): array
    {
        $totals = $this->consumption['totals'] ?? [];
        $forecast = $totals['forecast'] ?? 0;
        $committed = $totals['committed'] ?? 0;
        $realized = $totals['realized'] ?? 0;
        $available = $totals['available'] ?? ($forecast - $committed);
        $utilizationPct = $totals['utilization_pct'] ?? 0;
        $realizedPct = $totals['realized_pct'] ?? 0;

        return [
            ['Orçamento', "{$this->budget->scope_label} · {$this->budget->year} · v{$this->budget->version_label}"],
            ['Área (departamento)', $this->budget->areaDepartment?->name ?? '—'],
            ['Tipo', $this->budget->upload_type instanceof \BackedEnum ? $this->budget->upload_type->value : (string) $this->budget->upload_type],
            ['Status', $this->budget->is_active ? 'Ativo' : 'Inativo'],
            ['Itens', (int) $this->budget->items_count],
            ['', ''],
            ['Previsto total (R$)', $forecast],
            ['Comprometido (R$)', $committed],
            ['Realizado — pago (R$)', $realized],
            ['Disponível (R$)', $available],
            ['Utilização (%)', $utilizationPct],
            ['Realizado / Previsto (%)', $realizedPct],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4338CA']],
        ]);
        $sheet->getStyle('B8:B13')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('B12:B13')->getNumberFormat()->setFormatCode('0.00"%"');

        return [];
    }
}

/**
 * Sheet reusável — agregado por dimensão (CC, AC). Usa a estrutura
 * uniforme de `by_cost_center` / `by_accounting_class` do
 * BudgetConsumptionService.
 */
class BudgetByDimensionSheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected string $sheetTitle,
        protected array $rows,
        protected string $dimensionLabel,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return [
            $this->dimensionLabel,
            'Itens',
            'Previsto (R$)',
            'Comprometido (R$)',
            'Realizado (R$)',
            'Disponível (R$)',
            'Utilização (%)',
            'Status',
        ];
    }

    public function array(): array
    {
        $statusLabels = ['ok' => 'Normal', 'warning' => 'Atenção', 'exceeded' => 'Excedido'];

        return collect($this->rows)
            ->map(fn ($r) => [
                $r['name'] ?? '—',
                (int) ($r['items_count'] ?? 0),
                (float) ($r['forecast'] ?? 0),
                (float) ($r['committed'] ?? 0),
                (float) ($r['realized'] ?? 0),
                (float) ($r['available'] ?? 0),
                (float) ($r['utilization_pct'] ?? 0),
                $statusLabels[$r['status'] ?? ''] ?? ($r['status'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4338CA']],
        ]);
        $maxRow = max(2, $sheet->getHighestDataRow());
        $sheet->getStyle("C2:F{$maxRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("G2:G{$maxRow}")->getNumberFormat()->setFormatCode('0.00');

        return [];
    }
}

/**
 * Sheet 4 — Por Área. Como o budget é de uma única área (area_department_id),
 * na prática tem 1 linha — mas mantém-se como tabela para consistência
 * com o formato padrão de reports e para preparar orçamentos futuros
 * multi-área (agregados entre uploads).
 */
class BudgetByAreaSheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected BudgetUpload $budget,
        protected array $consumption,
    ) {}

    public function title(): string
    {
        return 'Por Área';
    }

    public function headings(): array
    {
        return ['Área', 'Itens', 'Previsto (R$)', 'Comprometido (R$)', 'Realizado (R$)', 'Disponível (R$)', 'Utilização (%)'];
    }

    public function array(): array
    {
        $totals = $this->consumption['totals'] ?? [];
        $areaName = $this->budget->areaDepartment?->name ?? '(sem área)';

        return [[
            $areaName,
            (int) $this->budget->items_count,
            (float) ($totals['forecast'] ?? 0),
            (float) ($totals['committed'] ?? 0),
            (float) ($totals['realized'] ?? 0),
            (float) ($totals['available'] ?? 0),
            (float) ($totals['utilization_pct'] ?? 0),
        ]];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4338CA']],
        ]);
        $sheet->getStyle('C2:F2')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('G2')->getNumberFormat()->setFormatCode('0.00');

        return [];
    }
}

/**
 * Sheet 5 — Por Mês (1..12) × Previsto/Comprometido/Realizado.
 */
class BudgetByMonthSheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    protected const MONTH_NAMES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function __construct(protected array $consumption) {}

    public function title(): string
    {
        return 'Por Mês';
    }

    public function headings(): array
    {
        return ['Mês', 'Previsto (R$)', 'Comprometido (R$)', 'Realizado (R$)'];
    }

    public function array(): array
    {
        $byMonth = $this->consumption['by_month'] ?? [];

        return collect($byMonth)
            ->map(fn ($m) => [
                self::MONTH_NAMES[$m['month']] ?? $m['month'],
                (float) ($m['forecast'] ?? 0),
                (float) ($m['committed'] ?? 0),
                (float) ($m['realized'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4338CA']],
        ]);
        $sheet->getStyle('B2:D13')->getNumberFormat()->setFormatCode('#,##0.00');

        return [];
    }
}

/**
 * Sheet 6 — Detalhe por item (cada linha do budget_items).
 */
class BudgetItemsSheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(protected array $items) {}

    public function title(): string
    {
        return 'Detalhe por Item';
    }

    public function headings(): array
    {
        return [
            'Conta Contábil',
            'Conta Gerencial',
            'Centro de Custo',
            'Loja',
            'Fornecedor',
            'Previsto (R$)',
            'Comprometido (R$)',
            'Realizado (R$)',
            'Disponível (R$)',
            'Utilização (%)',
        ];
    }

    public function array(): array
    {
        return collect($this->items)
            ->map(fn ($i) => [
                $i['accounting_class']['name'] ?? '—',
                $i['management_class']['name'] ?? '—',
                $i['cost_center']['name'] ?? '—',
                $i['store']['name'] ?? '—',
                $i['supplier'] ?? '',
                (float) ($i['forecast'] ?? 0),
                (float) ($i['committed'] ?? 0),
                (float) ($i['realized'] ?? 0),
                (float) ($i['available'] ?? 0),
                (float) ($i['utilization_pct'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4338CA']],
        ]);
        $maxRow = max(2, $sheet->getHighestDataRow());
        $sheet->getStyle("F2:I{$maxRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("J2:J{$maxRow}")->getNumberFormat()->setFormatCode('0.00');

        return [];
    }
}
