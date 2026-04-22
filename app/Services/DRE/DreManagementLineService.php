<?php

namespace App\Services\DRE;

use App\Models\DreManagementLine;
use App\Models\DreMapping;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD das linhas da DRE gerencial (plano executivo).
 *
 * Regras principais:
 *   - sort_order pode ter duas linhas iguais (analítica + subtotal no
 *     mesmo nível, ex: Headcount/EBITDA na ordem 13). Duas subtotais no
 *     mesmo sort_order são proibidas — gera ambiguidade.
 *   - Exclusão bloqueada quando existe dre_mapping vigente apontando
 *     para a linha. Use deactivation (is_active=false) como alternativa.
 *
 * O parâmetro `$version` aqui é um **filtro por data de vigência**
 * (Y-m-d) e não uma coluna — o plano gerencial não é versionado em
 * paralelo (ver `dre-arquitetura.md §10`). Hoje o filter não muda o
 * resultado porque `dre_management_lines` só tem `is_active`; fica
 * reservado para evolução futura.
 */
class DreManagementLineService
{
    /**
     * Lista ordenada por sort_order ASC + is_subtotal ASC (analíticas
     * aparecem antes do subtotal com mesmo sort_order).
     *
     * @return Collection<int, DreManagementLine>
     */
    public function list(?string $version = null): Collection
    {
        // $version reservado para quando houver versionamento real.
        // Hoje retorna todas as linhas não soft-deleted.
        return DreManagementLine::query()
            ->notDeleted()
            ->orderBy('sort_order')
            ->orderBy('is_subtotal')
            ->get();
    }

    public function create(array $data): DreManagementLine
    {
        $this->guardSubtotalConflict($data['sort_order'], (bool) ($data['is_subtotal'] ?? false));

        return DB::transaction(function () use ($data) {
            return DreManagementLine::create($this->sanitize($data));
        });
    }

    public function update(DreManagementLine $line, array $data): DreManagementLine
    {
        if (array_key_exists('sort_order', $data) || array_key_exists('is_subtotal', $data)) {
            $sortOrder = $data['sort_order'] ?? $line->sort_order;
            $isSubtotal = array_key_exists('is_subtotal', $data)
                ? (bool) $data['is_subtotal']
                : (bool) $line->is_subtotal;
            $this->guardSubtotalConflict($sortOrder, $isSubtotal, excludingId: $line->id);
        }

        return DB::transaction(function () use ($line, $data) {
            $line->fill($this->sanitize($data));
            $line->save();

            return $line->fresh();
        });
    }

    /**
     * Soft delete manual — mesmo padrão dos outros models do projeto.
     * Bloqueia quando há `dre_mappings` vigentes apontando pra linha.
     *
     * @throws ValidationException
     */
    public function delete(DreManagementLine $line, ?int $deletedByUserId = null, ?string $reason = null): void
    {
        $activeMappings = DreMapping::query()
            ->whereNull('deleted_at')
            ->where('dre_management_line_id', $line->id)
            ->count();

        if ($activeMappings > 0) {
            throw ValidationException::withMessages([
                'dre_management_line' => sprintf(
                    'Não é possível excluir a linha "%s": %d mapeamento(s) vigente(s) apontam para ela. Remova ou expire os mapeamentos antes.',
                    $line->level_1,
                    $activeMappings
                ),
            ]);
        }

        DB::transaction(function () use ($line, $deletedByUserId, $reason) {
            $line->forceFill([
                'deleted_at' => now(),
                'deleted_by_user_id' => $deletedByUserId,
                'deleted_reason' => $reason,
                'is_active' => false,
            ])->save();
        });
    }

    /**
     * Recalcula `sort_order` em lote conforme ordem dos ids fornecidos.
     * Usa incrementos de 1 (não 10) já que subtotais compartilham ordem —
     * a interface externa expõe uma sequência linear de linhas.
     *
     * @param  array<int, int>  $orderedIds  IDs na nova ordem desejada.
     */
    public function reorder(array $orderedIds): void
    {
        $existing = DreManagementLine::whereIn('id', $orderedIds)
            ->notDeleted()
            ->get()
            ->keyBy('id');

        if ($existing->count() !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ids' => 'Um ou mais ids informados não existem ou foram excluídos.',
            ]);
        }

        DB::transaction(function () use ($orderedIds, $existing) {
            foreach (array_values($orderedIds) as $index => $id) {
                $line = $existing->get($id);
                $line->sort_order = $index + 1;
                $line->save();
            }
        });
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    /**
     * Rejeita 2 subtotais no mesmo sort_order. Duas analíticas, ou 1
     * analítica + 1 subtotal compartilhando sort_order, é permitido
     * (padrão Headcount/EBITDA em 13).
     *
     * @throws ValidationException
     */
    private function guardSubtotalConflict(int $sortOrder, bool $isSubtotal, ?int $excludingId = null): void
    {
        if (! $isSubtotal) {
            return;
        }

        $query = DreManagementLine::query()
            ->notDeleted()
            ->where('sort_order', $sortOrder)
            ->where('is_subtotal', true);

        if ($excludingId !== null) {
            $query->where('id', '!=', $excludingId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'sort_order' => "Já existe um subtotal em sort_order={$sortOrder}. Dois subtotais na mesma ordem geram ambiguidade na matriz.",
            ]);
        }
    }

    /**
     * Remove chaves que não devem ser atribuídas via mass-assignment
     * (audit, timestamps etc.) e normaliza.
     */
    private function sanitize(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'code',
            'sort_order',
            'is_subtotal',
            'accumulate_until_sort_order',
            'level_1',
            'level_2',
            'level_3',
            'level_4',
            'nature',
            'is_active',
            'notes',
            'created_by_user_id',
            'updated_by_user_id',
        ]));
    }
}
