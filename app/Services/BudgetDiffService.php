<?php

namespace App\Services;

use App\Models\BudgetUpload;
use Illuminate\Validation\ValidationException;

/**
 * Comparação entre 2 versões de BudgetUpload do mesmo (year, scope_label).
 *
 * Identidade lógica de uma linha: (accounting_class_id, management_class_id,
 * cost_center_id, store_id). Supplier não entra na chave porque é texto
 * livre — uma linha pode trocar de fornecedor entre versões e continuar
 * sendo "a mesma" linha orçamentária conceitualmente.
 *
 * Saída estruturada para alimentar a página Compare.jsx.
 */
class BudgetDiffService
{
    /**
     * @return array{
     *   v1: array, v2: array,
     *   added: array, removed: array, changed: array, unchanged_count: int,
     *   totals: array,
     *   by_month: array,
     * }
     */
    public function diff(BudgetUpload $v1, BudgetUpload $v2): array
    {
        if ($v1->id === $v2->id) {
            throw ValidationException::withMessages([
                'versions' => 'Escolha duas versões diferentes para comparar.',
            ]);
        }

        if ($v1->year !== $v2->year) {
            throw ValidationException::withMessages([
                'versions' => 'Só é possível comparar versões do mesmo ano.',
            ]);
        }

        if (trim((string) $v1->scope_label) !== trim((string) $v2->scope_label)) {
            throw ValidationException::withMessages([
                'versions' => 'Só é possível comparar versões do mesmo escopo.',
            ]);
        }

        $v1->loadMissing([
            'items.accountingClass:id,code,name',
            'items.managementClass:id,code,name',
            'items.costCenter:id,code,name',
            'items.store:id,code,name',
        ]);
        $v2->loadMissing([
            'items.accountingClass:id,code,name',
            'items.managementClass:id,code,name',
            'items.costCenter:id,code,name',
            'items.store:id,code,name',
        ]);

        $v1Index = $this->indexByKey($v1->items);
        $v2Index = $this->indexByKey($v2->items);

        $keys = collect($v1Index)->keys()->merge(collect($v2Index)->keys())->unique()->values();

        $added = [];
        $removed = [];
        $changed = [];
        $unchangedCount = 0;

        foreach ($keys as $key) {
            $a = $v1Index[$key] ?? null;
            $b = $v2Index[$key] ?? null;

            if (! $a && $b) {
                $added[] = $this->formatItem($b, mode: 'added');
            } elseif ($a && ! $b) {
                $removed[] = $this->formatItem($a, mode: 'removed');
            } else {
                $diffRow = $this->compareItems($a, $b);
                if ($diffRow['has_changes']) {
                    $changed[] = $diffRow;
                } else {
                    $unchangedCount++;
                }
            }
        }

        // Totais globais
        $v1Total = (float) $v1->total_year;
        $v2Total = (float) $v2->total_year;
        $delta = $v2Total - $v1Total;
        $deltaPct = $v1Total > 0 ? round(($delta / $v1Total) * 100, 2) : 0.0;

        // Delta por mês (soma de todos os items)
        $byMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $col = 'month_'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'_value';
            $v1Sum = $v1->items->sum(fn ($i) => (float) $i->{$col});
            $v2Sum = $v2->items->sum(fn ($i) => (float) $i->{$col});
            $byMonth[] = [
                'month' => $m,
                'v1' => round($v1Sum, 2),
                'v2' => round($v2Sum, 2),
                'delta' => round($v2Sum - $v1Sum, 2),
            ];
        }

        return [
            'v1' => $this->formatVersion($v1),
            'v2' => $this->formatVersion($v2),
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged_count' => $unchangedCount,
            'totals' => [
                'v1' => round($v1Total, 2),
                'v2' => round($v2Total, 2),
                'delta' => round($delta, 2),
                'delta_pct' => $deltaPct,
                'items_v1' => $v1->items->count(),
                'items_v2' => $v2->items->count(),
                'added_count' => count($added),
                'removed_count' => count($removed),
                'changed_count' => count($changed),
            ],
            'by_month' => $byMonth,
        ];
    }

    /**
     * Chave lógica: (AC, MC, CC, store) — supplier não entra.
     */
    protected function indexByKey($items): array
    {
        $out = [];
        foreach ($items as $item) {
            $key = sprintf(
                '%d|%d|%d|%s',
                (int) $item->accounting_class_id,
                (int) $item->management_class_id,
                (int) $item->cost_center_id,
                $item->store_id ?? 'null'
            );
            $out[$key] = $item;
        }

        return $out;
    }

    protected function formatItem($item, string $mode): array
    {
        return [
            'id' => $item->id,
            'mode' => $mode,
            'accounting_class' => $item->accountingClass
                ? ['code' => $item->accountingClass->code, 'name' => $item->accountingClass->name]
                : null,
            'management_class' => $item->managementClass
                ? ['code' => $item->managementClass->code, 'name' => $item->managementClass->name]
                : null,
            'cost_center' => $item->costCenter
                ? ['code' => $item->costCenter->code, 'name' => $item->costCenter->name]
                : null,
            'store' => $item->store
                ? ['code' => $item->store->code, 'name' => $item->store->name]
                : null,
            'supplier' => $item->supplier,
            'year_total' => (float) $item->year_total,
        ];
    }

    /**
     * Compara 2 items com a mesma chave lógica. Identifica deltas por mês
     * + mudança de supplier/description.
     */
    protected function compareItems($a, $b): array
    {
        $monthDeltas = [];
        $hasMonthChanges = false;
        for ($m = 1; $m <= 12; $m++) {
            $col = 'month_'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'_value';
            $vA = (float) $a->{$col};
            $vB = (float) $b->{$col};
            $delta = $vB - $vA;
            if (abs($delta) > 0.005) {
                $hasMonthChanges = true;
            }
            $monthDeltas[] = [
                'month' => $m,
                'v1' => round($vA, 2),
                'v2' => round($vB, 2),
                'delta' => round($delta, 2),
            ];
        }

        $supplierChanged = trim((string) $a->supplier) !== trim((string) $b->supplier);
        $justificationChanged = trim((string) $a->justification) !== trim((string) $b->justification);
        $hasChanges = $hasMonthChanges || $supplierChanged || $justificationChanged;

        $totalA = (float) $a->year_total;
        $totalB = (float) $b->year_total;

        return [
            'id_v1' => $a->id,
            'id_v2' => $b->id,
            'mode' => 'changed',
            'has_changes' => $hasChanges,
            'accounting_class' => $a->accountingClass
                ? ['code' => $a->accountingClass->code, 'name' => $a->accountingClass->name]
                : null,
            'management_class' => $a->managementClass
                ? ['code' => $a->managementClass->code, 'name' => $a->managementClass->name]
                : null,
            'cost_center' => $a->costCenter
                ? ['code' => $a->costCenter->code, 'name' => $a->costCenter->name]
                : null,
            'store' => $a->store
                ? ['code' => $a->store->code, 'name' => $a->store->name]
                : null,
            'supplier_v1' => $a->supplier,
            'supplier_v2' => $b->supplier,
            'supplier_changed' => $supplierChanged,
            'justification_changed' => $justificationChanged,
            'months' => $monthDeltas,
            'year_total_v1' => round($totalA, 2),
            'year_total_v2' => round($totalB, 2),
            'year_total_delta' => round($totalB - $totalA, 2),
            'year_total_delta_pct' => $totalA > 0 ? round((($totalB - $totalA) / $totalA) * 100, 2) : 0.0,
        ];
    }

    protected function formatVersion(BudgetUpload $u): array
    {
        return [
            'id' => $u->id,
            'year' => $u->year,
            'scope_label' => $u->scope_label,
            'version_label' => $u->version_label,
            'major_version' => $u->major_version,
            'minor_version' => $u->minor_version,
            'upload_type' => $u->upload_type instanceof \BackedEnum ? $u->upload_type->value : (string) $u->upload_type,
            'is_active' => (bool) $u->is_active,
            'total_year' => (float) $u->total_year,
            'items_count' => (int) $u->items_count,
            'created_at' => $u->created_at?->format('d/m/Y H:i'),
        ];
    }
}
