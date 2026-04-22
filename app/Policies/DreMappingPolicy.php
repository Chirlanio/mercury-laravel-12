<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DreMapping;
use App\Models\User;

class DreMappingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_DRE->value);
    }

    public function view(User $user, DreMapping $mapping): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_DRE->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value);
    }

    public function update(User $user, DreMapping $mapping): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value);
    }

    public function delete(User $user, DreMapping $mapping): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value);
    }

    public function bulkAssign(User $user): bool
    {
        return $user->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value);
    }
}
