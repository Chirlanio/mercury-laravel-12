<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\ManagementClass;

/**
 * Resolvedores compartilhados entre OrderPaymentController (create/update
 * de OP) e o comando de backfill (order-payments:backfill-budget-links).
 *
 * Dois resolves principais:
 *   - cost_center_id:   deriva de management_class_id quando a UI usou
 *                       a cascata Área→Gerencial (o CC é faceta da MC).
 *   - budget_item_id:   trio (CC, AC, ano) → BudgetItem em upload ativo.
 *
 * Antes estava inline no OrderPaymentController. Extraído para que o
 * command de backfill (C3b do roadmap) pudesse reusar a mesma lógica
 * sem duplicação — qualquer ajuste na regra vale para os dois fluxos.
 */
class OrderPaymentBudgetResolver
{
    /**
     * Resolve cost_center_id pela cascata Área → Gerencial → CC. Se o
     * payload traz management_class_id, o CC vem dela (MC é autoritária).
     * Caso contrário, mantém o cost_center_id explícito que veio.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function resolveCostCenterId(array $attrs): ?int
    {
        $mcId = $attrs['management_class_id'] ?? null;
        $ccExplicit = $attrs['cost_center_id'] ?? null;

        if ($mcId) {
            $mcCc = ManagementClass::whereKey($mcId)->value('cost_center_id');
            if ($mcCc) {
                return $mcCc;
            }
        }

        return $ccExplicit;
    }

    /**
     * Resolve budget_item_id a partir de (cost_center_id, accounting_class_id,
     * ano da competência/pagamento). Retorna null se falta algum dos códigos
     * ou se não há budget ativo no ano.
     *
     * O ano vem de competence_date quando informado; fallback para
     * date_payment. Alinha o regime contábil — OP sem competência explícita
     * cai no orçamento do ano do caixa.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function resolveBudgetItemId(array $attrs): ?int
    {
        $ccId = $attrs['cost_center_id'] ?? null;
        $acId = $attrs['accounting_class_id'] ?? null;

        if (! $ccId || ! $acId) {
            return null;
        }

        $dateSource = $attrs['competence_date'] ?? $attrs['date_payment'] ?? null;
        if (! $dateSource) {
            return null;
        }
        $year = (int) date('Y', is_string($dateSource) ? strtotime($dateSource) : $dateSource->timestamp);

        return BudgetItem::query()
            ->where('cost_center_id', $ccId)
            ->where('accounting_class_id', $acId)
            ->whereHas('upload', fn ($q) => $q->where('year', $year)->where('is_active', true))
            ->value('id');
    }
}
