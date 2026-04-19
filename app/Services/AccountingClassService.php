<?php

namespace App\Services;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Models\AccountingClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD do Plano de Contas Contábil com:
 *  - dedup por code (enforce via service; unique no banco é salvaguarda)
 *  - validação de ciclo em parent_id
 *  - consistência folha/grupo: conta com filhas não pode ter
 *    accepts_entries=true (grupo sintético deve ser agregador)
 *  - delete bloqueado quando há filhas ativas
 *  - natureza contábil coerente com o grupo DRE (warning — não bloqueia)
 */
class AccountingClassService
{
    /**
     * @throws ValidationException
     */
    public function create(array $data, User $actor): AccountingClass
    {
        $this->ensureNoDuplicateCode($data['code']);

        if (! empty($data['parent_id'])) {
            $this->ensureParentIsValid((int) $data['parent_id']);
        }

        $nature = AccountingNature::from($data['nature']);
        $dreGroup = DreGroup::from($data['dre_group']);
        $acceptsEntries = array_key_exists('accepts_entries', $data)
            ? (bool) $data['accepts_entries']
            : true;

        return DB::transaction(function () use ($data, $actor, $nature, $dreGroup, $acceptsEntries) {
            return AccountingClass::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'nature' => $nature->value,
                'dre_group' => $dreGroup->value,
                'accepts_entries' => $acceptsEntries,
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
    public function update(AccountingClass $class, array $data, User $actor): AccountingClass
    {
        if ($class->isDeleted()) {
            throw ValidationException::withMessages([
                'id' => 'Conta contábil excluída não pode ser editada.',
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

        // Se está tentando marcar como folha mas tem filhas ativas, bloqueia
        if (array_key_exists('accepts_entries', $data) && (bool) $data['accepts_entries']) {
            $hasActiveChildren = AccountingClass::query()
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
                'nature' => $data['nature'] ?? $class->nature->value,
                'dre_group' => $data['dre_group'] ?? $class->dre_group->value,
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
     * Soft delete. Bloqueia se houver filhas ativas.
     *
     * @throws ValidationException
     */
    public function delete(AccountingClass $class, string $reason, User $actor): void
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

        $childrenCount = AccountingClass::query()
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
        $query = AccountingClass::query()
            ->where('code', $code)
            ->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => "Já existe uma conta ativa com o código '{$code}'.",
            ]);
        }
    }

    /**
     * Valida parent_id: existe, está ativo, não cria ciclo. Também rejeita
     * parent que seja folha analítica — agrupadores devem ser sintéticos.
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
                'parent_id' => 'Uma conta não pode ser pai de si mesma.',
            ]);
        }

        $parent = AccountingClass::query()
            ->whereNull('deleted_at')
            ->find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'Conta pai não encontrada ou excluída.',
            ]);
        }

        if ($parent->accepts_entries) {
            throw ValidationException::withMessages([
                'parent_id' => "A conta '{$parent->code}' é folha analítica (aceita lançamentos). Agrupadores devem ser sintéticos (accepts_entries=false).",
            ]);
        }

        if ($selfId !== null) {
            $ancestors = $parent->ancestorsIds();
            $ancestors[] = $parent->id;
            if (in_array($selfId, $ancestors, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Este vínculo criaria um ciclo na hierarquia (o pai selecionado é descendente desta conta).',
                ]);
            }
        }
    }
}
