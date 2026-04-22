<?php

namespace App\Services\DRE;

use App\Models\DrePeriodClosing;
use App\Models\DrePeriodClosingSnapshot;
use App\Models\Store;
use App\Services\DRE\Contracts\ClosedPeriodReader;
use Illuminate\Support\Facades\DB;

/**
 * Leitor real de períodos fechados.
 *
 * Para cada `filter` do `DreMatrixService`, devolve os `year_months` que
 * caem sob algum `DrePeriodClosing` ativo (não reaberto) e expõe os
 * snapshots correspondentes no escopo do filtro.
 *
 * Determinação do escopo a partir do filtro:
 *   - scope='general' ou ausente       → SCOPE_GENERAL, scope_id=NULL.
 *   - scope='network' com network_ids  → SCOPE_NETWORK, 1 linha por network_id.
 *   - scope='store' com store_ids       → SCOPE_STORE, 1 linha por store_id.
 *
 * Quando o filtro seleciona múltiplos escopos (ex: 3 stores), os snapshots
 * são somados — isso replica a mesma semântica do cálculo live.
 *
 * Snapshot NÃO armazena `previous_year`. Para evitar divergência entre
 * live e fechado, mantemos `previous_year` sempre 0 neste leitor; a UI
 * só mostra comparativo onde o período atual está aberto.
 */
class DrePeriodSnapshotReader implements ClosedPeriodReader
{
    public function closedYearMonths(array $filter): array
    {
        $lastClosed = DrePeriodClosing::lastClosedUpTo();
        if ($lastClosed === null) {
            return [];
        }

        $from = substr((string) ($filter['start_date'] ?? ''), 0, 10);
        $to = substr((string) ($filter['end_date'] ?? ''), 0, 10);
        if ($from === '' || $to === '') {
            return [];
        }

        // Meses fechados são aqueles cujo último dia <= lastClosed.
        $fromDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $toDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        $closedDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $lastClosed);
        if (! $fromDt || ! $toDt || ! $closedDt) {
            return [];
        }

        $result = [];
        $cursor = $fromDt->modify('first day of this month');
        while ($cursor <= $toDt) {
            $endOfMonth = $cursor->modify('last day of this month');
            if ($endOfMonth <= $closedDt) {
                $result[] = $cursor->format('Y-m');
            }
            $cursor = $cursor->modify('first day of next month');
        }

        return $result;
    }

    public function readSnapshot(array $filter): array
    {
        $yearMonths = $this->closedYearMonths($filter);
        if ($yearMonths === []) {
            return [];
        }

        [$scope, $scopeIds] = $this->resolveScope($filter);

        $q = DrePeriodClosingSnapshot::query()
            ->select([
                'year_month',
                'dre_management_line_id',
                DB::raw('SUM(actual_amount) as actual_amount'),
                DB::raw('SUM(budget_amount) as budget_amount'),
            ])
            ->whereIn('year_month', $yearMonths)
            ->where('scope', $scope)
            ->groupBy('year_month', 'dre_management_line_id');

        if ($scope !== DrePeriodClosingSnapshot::SCOPE_GENERAL) {
            if ($scopeIds === []) {
                return [];
            }
            $q->whereIn('scope_id', $scopeIds);
        }

        // Apenas fechamentos ativos — reabertos não são fonte de verdade.
        $activeClosingIds = DrePeriodClosing::query()
            ->whereNull('reopened_at')
            ->pluck('id')
            ->all();

        if ($activeClosingIds === []) {
            return [];
        }

        $q->whereIn('dre_period_closing_id', $activeClosingIds);

        $out = [];
        foreach ($q->get() as $row) {
            $ym = (string) $row->year_month;
            $lineId = (int) $row->dre_management_line_id;

            $out[$ym] ??= [];
            $out[$ym][$lineId] = [
                'actual' => (float) $row->actual_amount,
                'budget' => (float) $row->budget_amount,
                'previous_year' => 0.0,
            ];
        }

        return $out;
    }

    /**
     * Deriva escopo + ids do filtro da matriz.
     *
     * @return array{0:string,1:array<int,int>}
     */
    private function resolveScope(array $filter): array
    {
        $scope = strtolower((string) ($filter['scope'] ?? 'general'));

        if ($scope === 'network') {
            $networkIds = array_map('intval', $filter['network_ids'] ?? []);
            if ($networkIds === []) {
                return [DrePeriodClosingSnapshot::SCOPE_NETWORK, []];
            }

            // Snapshot armazena 1 linha por network_id.
            return [DrePeriodClosingSnapshot::SCOPE_NETWORK, $networkIds];
        }

        if ($scope === 'store') {
            $storeIds = array_map('intval', $filter['store_ids'] ?? []);

            return [DrePeriodClosingSnapshot::SCOPE_STORE, $storeIds];
        }

        return [DrePeriodClosingSnapshot::SCOPE_GENERAL, []];
    }
}
