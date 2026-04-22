<?php

namespace App\Console\Commands;

use App\Models\CostCenter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lista referências ativas para CCs legados (os 24 criados antes do importer,
 * tipicamente codes "421".."457" sem pontos, sem external_source).
 *
 * Saída em PT-BR. Zero dependência de lugar — resolve com Query Builder para
 * evitar problemas caso as tabelas de budget/management_classes ainda não
 * estejam presentes em algum tenant novo.
 *
 * Referência: `docs/dre-playbook.md` Prompt 3 — usar antes da migration
 * `soft_delete_legacy_cost_centers` para saber se há FKs a limpar antes.
 */
class DreCheckLegacyCostCentersCommand extends Command
{
    protected $signature = 'dre:check-legacy-cc-refs';

    protected $description = 'Lista referências ativas para Centros de Custo legados (sem external_source).';

    public function handle(): int
    {
        $legacyIds = CostCenter::query()
            ->whereNull('deleted_at')
            ->whereNull('external_source')
            ->pluck('id');

        if ($legacyIds->isEmpty()) {
            $this->info('Nenhum CC legado encontrado (external_source IS NULL).');

            return self::SUCCESS;
        }

        $this->line("Encontrados {$legacyIds->count()} CCs legados (external_source=null).");
        $this->newLine();

        $foundRefs = false;

        if (\Schema::hasTable('budget_items')) {
            $q = DB::table('budget_items')->whereIn('cost_center_id', $legacyIds);
            if (\Schema::hasColumn('budget_items', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $budgetRefs = $q->count();

            if ($budgetRefs > 0) {
                $this->warn("budget_items apontando para CCs legados: {$budgetRefs}");
                $this->showBudgetItemSample($legacyIds);
                $foundRefs = true;
            }
        }

        if (\Schema::hasTable('management_classes')) {
            $q = DB::table('management_classes')->whereIn('cost_center_id', $legacyIds);
            if (\Schema::hasColumn('management_classes', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $mgmtRefs = $q->count();

            if ($mgmtRefs > 0) {
                $this->warn("management_classes apontando para CCs legados: {$mgmtRefs}");
                $this->showManagementClassSample($legacyIds);
                $foundRefs = true;
            }
        }

        if (\Schema::hasTable('order_payments')) {
            $q = DB::table('order_payments')->whereIn('cost_center_id', $legacyIds);
            if (\Schema::hasColumn('order_payments', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $opRefs = $q->count();

            if ($opRefs > 0) {
                $this->warn("order_payments apontando para CCs legados: {$opRefs}");
                $foundRefs = true;
            }
        }

        $this->newLine();

        if ($foundRefs) {
            $this->error('Existem referências ativas. Migre os dados antes de rodar a migration de limpeza.');

            return self::FAILURE;
        }

        $this->info('Nenhuma referência ativa. Pode rodar a migration de limpeza com segurança.');

        return self::SUCCESS;
    }

    private function showBudgetItemSample($legacyIds): void
    {
        $q = DB::table('budget_items as bi')
            ->join('cost_centers as cc', 'bi.cost_center_id', '=', 'cc.id')
            ->whereIn('bi.cost_center_id', $legacyIds)
            ->select('bi.id as budget_item_id', 'cc.code as cc_code', 'cc.name as cc_name')
            ->limit(5);
        if (\Schema::hasColumn('budget_items', 'deleted_at')) {
            $q->whereNull('bi.deleted_at');
        }
        $sample = $q->get();

        $this->table(
            ['budget_item_id', 'cc_code', 'cc_name'],
            $sample->map(fn ($r) => [(string) $r->budget_item_id, $r->cc_code, $r->cc_name])->toArray()
        );
    }

    private function showManagementClassSample($legacyIds): void
    {
        $q = DB::table('management_classes as mc')
            ->join('cost_centers as cc', 'mc.cost_center_id', '=', 'cc.id')
            ->whereIn('mc.cost_center_id', $legacyIds)
            ->select('mc.id as mgmt_id', 'mc.code as mgmt_code', 'cc.code as cc_code')
            ->limit(5);
        if (\Schema::hasColumn('management_classes', 'deleted_at')) {
            $q->whereNull('mc.deleted_at');
        }
        $sample = $q->get();

        $this->table(
            ['mgmt_id', 'mgmt_code', 'cc_code'],
            $sample->map(fn ($r) => [(string) $r->mgmt_id, $r->mgmt_code, $r->cc_code])->toArray()
        );
    }
}
