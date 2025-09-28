<?php

namespace App\Helpers;

use App\Enums\Role;
use App\Models\User;

class PermissionHelper
{
    public static function canManageUsers(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public static function canEditUser(User $currentUser, User $targetUser): bool
    {
        // Super admin pode editar qualquer um, exceto alterar o próprio role
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    public static function canDeleteUser(User $currentUser, User $targetUser): bool
    {
        // Super admin pode deletar qualquer um, exceto a si mesmo
        if ($currentUser->isSuperAdmin() && $currentUser->id !== $targetUser->id) {
            return true;
        }

        return false;
    }

    public static function canChangeRole(User $currentUser, User $targetUser, Role $newRole): bool
    {
        // Super admin não pode alterar o próprio role
        if ($currentUser->id === $targetUser->id) {
            return false;
        }

        // Apenas super admin pode alterar roles
        if ($currentUser->isSuperAdmin()) {
            // Super admin pode definir qualquer role
            return true;
        }

        return false;
    }

    public static function getAccessibleRoles(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Role::options();
        }

        if ($user->isAdmin()) {
            return collect(Role::options())
                ->except(['super_admin'])
                ->toArray();
        }

        return [];
    }

    public static function canAccessAdminPanel(User $user): bool
    {
        return $user->hasPermission(Role::ADMIN);
    }

    public static function canAccessSuperAdminPanel(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public static function getUserPermissions(User $user): array
    {
        $permissions = [];

        if (self::canManageUsers($user)) {
            $permissions[] = 'manage_users';
        }

        if (self::canAccessAdminPanel($user)) {
            $permissions[] = 'access_admin_panel';
        }

        if (self::canAccessSuperAdminPanel($user)) {
            $permissions[] = 'access_super_admin_panel';
        }

        return $permissions;
    }
}