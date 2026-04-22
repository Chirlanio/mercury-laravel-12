<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DreManagementLine;
use App\Models\User;

/**
 * Policy para `DreManagementLine`.
 *
 * O projeto usa predominantemente middleware `permission:` para gate
 * de rotas — Policies são uma camada complementar pedida no prompt #5
 * para permitir autorize() dentro dos controllers (útil em testes que
 * chamam Controllers sem HTTP stack).
 *
 * Regras:
 *   - viewAny/view: VIEW_DRE (matriz e fila de pendências incluem o
 *     conhecimento da estrutura gerencial).
 *   - create/update/delete/reorder: MANAGE_DRE_STRUCTURE (admin financeiro).
 */
class DreManagementLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_DRE->value);
    }

    public function view(User $user, DreManagementLine $line): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_DRE->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_DRE_STRUCTURE->value);
    }

    public function update(User $user, DreManagementLine $line): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_DRE_STRUCTURE->value);
    }

    public function delete(User $user, DreManagementLine $line): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_DRE_STRUCTURE->value);
    }

    public function reorder(User $user): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_DRE_STRUCTURE->value);
    }
}
