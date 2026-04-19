<?php

namespace App\Services;

use App\Models\CostCenter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de centros de custo com:
 *  - dedup por code (enforce via service; unique no banco é salvaguarda)
 *  - validação de ciclo em parent_id (CC não pode ser descendente de si mesmo)
 *  - soft delete manual (padrão Reversal/Return/PurchaseOrder)
 *  - stamps de auditoria por usuário
 *
 * Não faz state machine — CostCenter tem apenas is_active toggle.
 */
class CostCenterService
{
    /**
     * Cria um novo centro de custo.
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor): CostCenter
    {
        $this->ensureNoDuplicateCode($data['code']);

        if (! empty($data['parent_id'])) {
            $this->ensureParentIsValid((int) $data['parent_id']);
        }

        return DB::transaction(function () use ($data, $actor) {
            return CostCenter::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'default_accounting_class_id' => $data['default_accounting_class_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }

    /**
     * Atualiza um centro de custo existente.
     *
     * @throws ValidationException
     */
    public function update(CostCenter $costCenter, array $data, User $actor): CostCenter
    {
        if ($costCenter->isDeleted()) {
            throw ValidationException::withMessages([
                'id' => 'Centro de custo excluído não pode ser editado.',
            ]);
        }

        if (array_key_exists('code', $data) && $data['code'] !== $costCenter->code) {
            $this->ensureNoDuplicateCode($data['code'], $costCenter->id);
        }

        if (array_key_exists('parent_id', $data)) {
            $newParentId = $data['parent_id'] !== null ? (int) $data['parent_id'] : null;
            $this->ensureParentIsValid($newParentId, $costCenter->id);
        }

        return DB::transaction(function () use ($costCenter, $data, $actor) {
            $costCenter->fill([
                'code' => $data['code'] ?? $costCenter->code,
                'name' => $data['name'] ?? $costCenter->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $costCenter->description,
                'area_id' => array_key_exists('area_id', $data) ? $data['area_id'] : $costCenter->area_id,
                'parent_id' => array_key_exists('parent_id', $data) ? $data['parent_id'] : $costCenter->parent_id,
                'default_accounting_class_id' => array_key_exists('default_accounting_class_id', $data)
                    ? $data['default_accounting_class_id']
                    : $costCenter->default_accounting_class_id,
                'manager_id' => array_key_exists('manager_id', $data) ? $data['manager_id'] : $costCenter->manager_id,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $costCenter->is_active,
                'updated_by_user_id' => $actor->id,
            ]);

            $costCenter->save();

            return $costCenter->fresh();
        });
    }

    /**
     * Soft delete com motivo obrigatório. Bloqueia se houver filhos ativos.
     *
     * @throws ValidationException
     */
    public function delete(CostCenter $costCenter, string $reason, User $actor): void
    {
        if ($costCenter->isDeleted()) {
            throw ValidationException::withMessages([
                'id' => 'Centro de custo já excluído.',
            ]);
        }

        $trimmed = trim($reason);
        if (strlen($trimmed) < 3) {
            throw ValidationException::withMessages([
                'deleted_reason' => 'Informe um motivo com ao menos 3 caracteres.',
            ]);
        }

        $childrenCount = CostCenter::query()
            ->where('parent_id', $costCenter->id)
            ->whereNull('deleted_at')
            ->count();

        if ($childrenCount > 0) {
            throw ValidationException::withMessages([
                'id' => "Este centro de custo tem {$childrenCount} filho(s) ativo(s). Exclua ou reatribua os filhos antes.",
            ]);
        }

        DB::transaction(function () use ($costCenter, $trimmed, $actor) {
            $costCenter->forceFill([
                'deleted_at' => now(),
                'deleted_by_user_id' => $actor->id,
                'deleted_reason' => $trimmed,
            ])->save();
        });
    }

    /**
     * Garante que não existe outro CC ativo com o mesmo code.
     *
     * @throws ValidationException
     */
    protected function ensureNoDuplicateCode(string $code, ?int $ignoreId = null): void
    {
        $query = CostCenter::query()
            ->where('code', $code)
            ->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => "Já existe um centro de custo ativo com o código '{$code}'.",
            ]);
        }
    }

    /**
     * Valida parent_id: existe, está ativo, e não cria ciclo com selfId.
     *
     * @throws ValidationException
     */
    protected function ensureParentIsValid(?int $parentId, ?int $selfId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Um centro de custo não pode ser pai de si mesmo.',
            ]);
        }

        $parent = CostCenter::query()
            ->whereNull('deleted_at')
            ->find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'Centro de custo pai não encontrado ou excluído.',
            ]);
        }

        // Ciclo: selfId está entre os ancestrais do novo parent
        if ($selfId !== null) {
            $ancestors = $parent->ancestorsIds();
            $ancestors[] = $parent->id;
            if (in_array($selfId, $ancestors, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Este vínculo criaria um ciclo na hierarquia (o CC selecionado é descendente deste).',
                ]);
            }
        }
    }
}
