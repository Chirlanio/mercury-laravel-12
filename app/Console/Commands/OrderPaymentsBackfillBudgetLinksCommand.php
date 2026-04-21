<?php

namespace App\Console\Commands;

use App\Models\OrderPayment;
use App\Models\Tenant;
use App\Services\OrderPaymentBudgetResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill de order_payments.budget_item_id — C3b do roadmap de integração
 * OP ↔ Budget.
 *
 * OPs criadas antes do commit `c3478fb` (integração) ficaram sem budget_item_id.
 * Este comando preenche automaticamente as OPs que já têm CC+AC+data
 * preenchidos — apenas aplica a lógica do OrderPaymentBudgetResolver em
 * bulk. OPs sem CC ou AC continuam sem vínculo (precisam classificação
 * manual via EditModal).
 *
 * Dry-run por default. Use --apply para gravar.
 *
 * Itera tenants via stancl/tenancy. Pode ser rodado em CI ou manualmente.
 */
class OrderPaymentsBackfillBudgetLinksCommand extends Command
{
    protected $signature = 'order-payments:backfill-budget-links
                            {--apply : Grava as alterações (default: dry-run)}
                            {--tenant= : Roda apenas num tenant específico}';

    protected $description = 'Preenche budget_item_id em OPs antigas sem vínculo (quando já têm CC+AC+data).';

    public function handle(OrderPaymentBudgetResolver $resolver): int
    {
        $apply = (bool) $this->option('apply');
        $tenantId = $this->option('tenant');

        $this->info($apply ? '🔵 APLICANDO backfill' : '🔍 DRY-RUN (use --apply para gravar)');
        $this->newLine();

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandLinked = 0;
        $grandMissing = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($resolver, $apply, &$grandLinked, &$grandMissing) {
                    [$linked, $missing] = $this->scanTenant($resolver, $apply);
                    $grandLinked += $linked;
                    $grandMissing += $missing;
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info(sprintf(
            "Total: %d OPs vinculadas, %d sem CC/AC (precisam classificação manual).",
            $grandLinked,
            $grandMissing
        ));

        if (! $apply) {
            $this->comment('Dry-run — nada foi gravado. Use --apply para persistir.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}  [linked, missing_cc_ac]
     */
    protected function scanTenant(OrderPaymentBudgetResolver $resolver, bool $apply): array
    {
        if (! Schema::hasTable('order_payments') || ! Schema::hasColumn('order_payments', 'budget_item_id')) {
            $this->line('  (tabela ou coluna ausente neste tenant — skip)');

            return [0, 0];
        }

        $query = OrderPayment::query()
            ->whereNull('budget_item_id')
            ->whereNull('deleted_at');

        $total = $query->count();
        if ($total === 0) {
            $this->line('  ✓ Sem OPs para backfill.');

            return [0, 0];
        }

        $this->line("  {$total} OPs sem budget_item_id");

        $linked = 0;
        $missing = 0;
        $noDate = 0;
        $noBudget = 0;

        $query->chunk(200, function ($ops) use ($resolver, $apply, &$linked, &$missing, &$noDate, &$noBudget) {
            foreach ($ops as $op) {
                // Sem CC ou AC → manual
                if (! $op->cost_center_id || ! $op->accounting_class_id) {
                    $missing++;

                    continue;
                }

                // Resolve via service (mesma lógica do controller)
                $budgetItemId = $resolver->resolveBudgetItemId([
                    'cost_center_id' => $op->cost_center_id,
                    'accounting_class_id' => $op->accounting_class_id,
                    'competence_date' => $op->competence_date,
                    'date_payment' => $op->date_payment,
                ]);

                if (! $budgetItemId) {
                    // CC/AC presentes mas sem budget ativo no ano
                    if (! ($op->competence_date || $op->date_payment)) {
                        $noDate++;
                    } else {
                        $noBudget++;
                    }

                    continue;
                }

                if ($apply) {
                    $op->forceFill(['budget_item_id' => $budgetItemId])->saveQuietly();
                }
                $linked++;
            }
        });

        $this->line("  → {$linked} OPs vinculadas");
        if ($missing > 0) {
            $this->line("  → {$missing} sem CC ou AC (classificação manual necessária)");
        }
        if ($noDate > 0) {
            $this->line("  → {$noDate} sem competence_date nem date_payment");
        }
        if ($noBudget > 0) {
            $this->line("  → {$noBudget} com CC/AC mas sem budget ativo no ano");
        }

        return [$linked, $missing];
    }
}
