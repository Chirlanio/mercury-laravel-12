<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — destravador pré-prompt 6 (parte do prompt 3 do playbook).
 *
 * Soft-deleta os CCs legados (external_source=null, tipicamente codes
 * "421".."457") que na prática eram `stores.code` disfarçados de
 * cost_center antes do import oficial do grupo 8 do Excel.
 *
 * SEGURANÇA: antes de soft-deletar, verifica se há budget_items,
 * management_classes ou order_payments ativos apontando para eles.
 * Se houver, aborta com mensagem PT pedindo para remapear os dados
 * antes. Use `php artisan tenants:run dre:check-legacy-cc-refs`
 * para listar as referências.
 *
 * Idempotente: rodar 2x é seguro. Se não houver legados (ambiente
 * novo ou já limpo), no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacyIds = DB::table('cost_centers')
            ->whereNull('deleted_at')
            ->whereNull('external_source')
            ->pluck('id');

        if ($legacyIds->isEmpty()) {
            return;
        }

        // Separa legados que podem ser deletados dos que têm refs ativas.
        [$deletableIds, $blockedIds] = $this->splitByReferences($legacyIds->all());

        if (! empty($deletableIds)) {
            DB::table('cost_centers')
                ->whereIn('id', $deletableIds)
                ->update([
                    'deleted_at' => now(),
                    'deleted_by_user_id' => null,
                    'deleted_reason' => 'Migração DRE — CC legado (era stores.code disfarçado). Substituído pelos CCs do grupo 8 do plano de contas.',
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }

        if (! empty($blockedIds)) {
            // CCs com refs ativas ficam intactos. Para sinalizar que já foram
            // analisados (e evitar que a migration re-processe eternamente em
            // re-runs), marcamos external_source='LEGACY_UNMIGRATED'. Quando o
            // time financeiro migrar as FKs, basta rodar:
            //   UPDATE cost_centers SET external_source=NULL
            //   WHERE external_source='LEGACY_UNMIGRATED'
            // e rerodar a migration para deletar.
            DB::table('cost_centers')
                ->whereIn('id', $blockedIds)
                ->update([
                    'external_source' => 'LEGACY_UNMIGRATED',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Restaura todos os CCs que foram soft-deletados com a mensagem exata desta
        // migration. Útil em rollback local; seguro em produção porque o reason
        // é específico.
        DB::table('cost_centers')
            ->where('deleted_reason', 'Migração DRE — CC legado (era stores.code disfarçado). Substituído pelos CCs do grupo 8 do plano de contas.')
            ->update([
                'deleted_at' => null,
                'deleted_by_user_id' => null,
                'deleted_reason' => null,
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Separa os ids em duas listas: (a) os que podem ser soft-deletados com
     * segurança (nenhuma FK ativa apontando) e (b) os bloqueados.
     *
     * @return array{0: int[], 1: int[]}  [deletable, blocked]
     */
    private function splitByReferences(array $legacyIds): array
    {
        $blocked = [];

        foreach (['budget_items', 'management_classes', 'order_payments'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'cost_center_id')) {
                continue;
            }

            $query = DB::table($table)
                ->whereIn('cost_center_id', $legacyIds)
                ->distinct();

            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $referenced = $query->pluck('cost_center_id')->all();
            $blocked = array_unique(array_merge($blocked, $referenced));
        }

        $deletable = array_values(array_diff($legacyIds, $blocked));
        $blocked = array_values($blocked);

        return [$deletable, $blocked];
    }
};
