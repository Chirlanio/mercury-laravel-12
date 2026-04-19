<?php

namespace App\Services;

use App\Models\AccountingClass;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD do Plano de Contas Gerencial. Segue o mesmo padrão de
 * AccountingClassService (dedup, cycle, leaf-cannot-be-parent, delete
 * com motivo), mais validações específicas dos vínculos opcionais:
 *  - accounting_class_id só pode apontar para folha analítica
 *    (agrupador contábil não recebe lançamento direto)
 *  - cost_center_id só pode apontar para CC ativo e não deletado
 */
class ManagementClassService
{
    /**
     * @throws ValidationException
     */
    public function create(array $data, User $actor): ManagementClass
    {
        $this->ensureNoDuplicateCode($data['code']);

        if (! empty($data['parent_id'])) {
            $this->ensureParentIsValid((int) $data['parent_id']);
        }

        if (! empty($data['accounting_class_id'])) {
            $this->ensureAccountingClassIsLeaf((int) $data['accounting_class_id']);
        }

        if (! empty($data['cost_center_id'])) {
            $this->ensureCostCenterExists((int) $data['cost_center_id']);
        }

        return DB::transaction(function () use ($data, $actor) {
            return ManagementClass::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'accounting_class_id' => $data['accounting_class_id'] ?? null,
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'accepts_entries' => array_key_exists('accepts_entries', $data) ? (bool) $data['accepts_entries'] : true,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function update(ManagementClass $class, array $data, User $actor): ManagementClass
    {
        if ($class->isDeleted()) {
            throw ValidationException::withMessages([
                'id' => 'Conta gerencial excluída não pode ser editada.',
            ]);
        }

        if (array_key_exists('code', $data) && $data['code'] !== $class->code) {
            $this->ensureNoDuplicateCode($data['code'], $class->id);
        }

        if (array_key_exists('parent_id', $data)) {
            $newParentId = $data['parent_id'] !== null && $data['parent_id'] !== ''
                ? (int) $data['parent_id']
                : null;
            $this->ensureParentIsValid($newParentId, $class->id);
        }

        if (array_key_exists('accounting_class_id', $data) && $data['accounting_class_id']) {
            $this->ensureAccountingClassIsLeaf((int) $data['accounting_class_id']);
        }

        if (array_key_exists('cost_center_id', $data) && $data['cost_center_id']) {
            $this->ensureCostCenterExists((int) $data['cost_center_id']);
        }

        if (array_key_exists('accepts_entries', $data) && (bool) $data['accepts_entries']) {
            $hasActiveChildren = ManagementClass::query()
                ->where('parent_id', $class->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasActiveChildren) {
                throw ValidationException::withMessages([
                    'accepts_entries' => 'Esta conta tem filhas ativas — não pode virar folha analítica. Exclua ou reatribua as filhas primeiro.',
                ]);
            }
        }

        return DB::transaction(function () use ($class, $data, $actor) {
            $class->fill([
                'code' => $data['code'] ?? $class->code,
                'name' => $data['name'] ?? $class->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $class->description,
                'parent_id' => array_key_exists('parent_id', $data) ? ($data['parent_id'] ?: null) : $class->parent_id,
                'accounting_class_id' => array_key_exists('accounting_class_id', $data)
                    ? ($data['accounting_class_id'] ?: null)
                    : $class->accounting_class_id,
                'cost_center_id' => array_key_exists('cost_center_id', $data)
                    ? ($data['cost_center_id'] ?: null)
                    : $class->cost_center_id,
                'accepts_entries' => array_key_exists('accepts_entries', $data)
                    ? (bool) $data['accepts_entries']
                    : $class->accepts_entries,
                'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $class->sort_order,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $class->is_active,
                'updated_by_user_id' => $actor->id,
            ]);

            $class->save();

            return $class->fresh();
        });
    }

    /**
     * @throws ValidationException
     */
    public function delete(ManagementClass $class, string $reason, User $actor): void
    {
        if ($class->isDeleted()) {
            throw ValidationException::withMessages([
                'id' => 'Conta já excluída.',
            ]);
        }

        $trimmed = trim($reason);
        if (strlen($trimmed) < 3) {
            throw ValidationException::withMessages([
                'deleted_reason' => 'Informe um motivo com ao menos 3 caracteres.',
            ]);
        }

        $childrenCount = ManagementClass::query()
            ->where('parent_id', $class->id)
            ->whereNull('deleted_at')
            ->count();

        if ($childrenCount > 0) {
            throw ValidationException::withMessages([
                'id' => "Esta conta tem {$childrenCount} filha(s) ativa(s). Exclua ou reatribua antes.",
            ]);
        }

        DB::transaction(function () use ($class, $trimmed, $actor) {
            $class->forceFill([
                'deleted_at' => now(),
                'deleted_by_user_id' => $actor->id,
                'deleted_reason' => $trimmed,
            ])->save();
        });
    }

    /**
     * @throws ValidationException
     */
    protected function ensureNoDuplicateCode(string $code, ?int $ignoreId = null): void
    {
        $query = ManagementClass::query()
            ->where('code', $code)
            ->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => "Já existe uma conta gerencial ativa com o código '{$code}'.",
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function ensureParentIsValid(?int $parentId, ?int $selfId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Uma conta não pode ser pai de si mesma.',
            ]);
        }

        $parent = ManagementClass::query()
            ->whereNull('deleted_at')
            ->find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'Conta pai não encontrada ou excluída.',
            ]);
        }

        if ($parent->accepts_entries) {
            throw ValidationException::withMessages([
                'parent_id' => "A conta '{$parent->code}' é folha analítica. Agrupadores devem ser sintéticos.",
            ]);
        }

        if ($selfId !== null) {
            $ancestors = $parent->ancestorsIds();
            $ancestors[] = $parent->id;
            if (in_array($selfId, $ancestors, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Este vínculo criaria um ciclo na hierarquia.',
                ]);
            }
        }
    }

    /**
     * AccountingClass vinculada deve ser folha analítica — não faz
     * sentido mapear gerencial para grupo contábil (grupo não recebe
     * lançamento direto).
     *
     * @throws ValidationException
     */
    protected function ensureAccountingClassIsLeaf(int $accountingClassId): void
    {
        $ac = AccountingClass::query()
            ->whereNull('deleted_at')
            ->find($accountingClassId);

        if (! $ac) {
            throw ValidationException::withMessages([
                'accounting_class_id' => 'Conta contábil vinculada não encontrada ou excluída.',
            ]);
        }

        if (! $ac->accepts_entries) {
            throw ValidationException::withMessages([
                'accounting_class_id' => "A conta contábil '{$ac->code}' é um agrupador sintético — vincule a uma folha analítica.",
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function ensureCostCenterExists(int $costCenterId): void
    {
        $cc = CostCenter::query()
            ->whereNull('deleted_at')
            ->find($costCenterId);

        if (! $cc) {
            throw ValidationException::withMessages([
                'cost_center_id' => 'Centro de custo vinculado não encontrado ou excluído.',
            ]);
        }
    }
}
