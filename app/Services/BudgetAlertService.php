<?php

namespace App\Services;

use App\Models\BudgetUpload;
use Illuminate\Support\Collection;

/**
 * Varre budgets ativos do ano corrente e detecta items/CCs em status
 * warning (≥ 70%) ou exceeded (≥ 100%). Retorna estrutura agregada
 * pronta para virar payload de notification.
 *
 * Thresholds são os mesmos do BudgetConsumptionService (ok/warning/
 * exceeded) — consistência visual entre dashboard e alertas.
 */
class BudgetAlertService
{
    public function __construct(
        protected BudgetConsumptionService $consumption,
    ) {}

    /**
     * Varre todos os budgets ativos do ano e agrega alertas. Se $year
     * não for informado, usa o ano corrente.
     *
     * @return array{
     *   year: int,
     *   alerts: array<int, array{
     *     budget_id: int, scope_label: string, version_label: string,
     *     total_forecast: float, total_realized: float, total_pct: float,
     *     status: string,
     *     warning_ccs: array, exceeded_ccs: array,
     *     warning_items: int, exceeded_items: int,
     *   }>,
     *   summary: array{
     *     scanned_budgets: int,
     *     warning_count: int,
     *     exceeded_count: int,
     *   },
     * }
     */
    public function scanAlerts(?int $year = null): array
    {
        $year = $year ?? (int) now()->year;

        $budgets = BudgetUpload::query()
            ->where('year', $year)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('scope_label')
            ->get();

        $alerts = [];
        $warningCount = 0;
        $exceededCount = 0;

        foreach ($budgets as $budget) {
            $consumption = $this->consumption->getConsumption($budget);

            $warningCcs = collect($consumption['by_cost_center'] ?? [])
                ->filter(fn ($cc) => $cc['status'] === 'warning')
                ->values()
                ->all();

            $exceededCcs = collect($consumption['by_cost_center'] ?? [])
                ->filter(fn ($cc) => $cc['status'] === 'exceeded')
                ->values()
                ->all();

            $warningItems = collect($consumption['by_item'] ?? [])
                ->filter(fn ($i) => $i['status'] === 'warning')
                ->count();

            $exceededItems = collect($consumption['by_item'] ?? [])
                ->filter(fn ($i) => $i['status'] === 'exceeded')
                ->count();

            // Só gera alerta se houver ao menos 1 CC ou item em warning/exceeded
            if (empty($warningCcs) && empty($exceededCcs) && $warningItems === 0 && $exceededItems === 0) {
                continue;
            }

            $alerts[] = [
                'budget_id' => $budget->id,
                'scope_label' => $budget->scope_label,
                'version_label' => $budget->version_label,
                'total_forecast' => (float) ($consumption['totals']['forecast'] ?? 0),
                'total_realized' => (float) ($consumption['totals']['realized'] ?? 0),
                'total_pct' => (float) ($consumption['totals']['utilization_pct'] ?? 0),
                'status' => ! empty($exceededCcs) ? 'exceeded' : 'warning',
                'warning_ccs' => $warningCcs,
                'exceeded_ccs' => $exceededCcs,
                'warning_items' => $warningItems,
                'exceeded_items' => $exceededItems,
            ];

            if (! empty($exceededCcs)) {
                $exceededCount++;
            } else {
                $warningCount++;
            }
        }

        return [
            'year' => $year,
            'alerts' => $alerts,
            'summary' => [
                'scanned_budgets' => $budgets->count(),
                'warning_count' => $warningCount,
                'exceeded_count' => $exceededCount,
            ],
        ];
    }

    /**
     * Retorna `true` se há ao menos 1 alerta para notificar.
     */
    public function hasAlerts(array $scan): bool
    {
        return ! empty($scan['alerts']);
    }
}
