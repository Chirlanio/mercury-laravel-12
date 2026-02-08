<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case SUPPORT = 'support';
    case USER = 'user';

    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::ADMIN => 'Administrador',
            self::SUPPORT => 'Suporte',
            self::USER => 'Usuário',
        };
    }

    public function hasPermission(Role $requiredRole): bool
    {
        $hierarchy = [
            self::USER->value => 1,
            self::SUPPORT->value => 2,
            self::ADMIN->value => 3,
            self::SUPER_ADMIN->value => 4,
        ];

        return $hierarchy[$this->value] >= $hierarchy[$requiredRole->value];
    }

    public function permissions(): array
    {
        return match($this) {
            self::SUPER_ADMIN => [
                // Todas as permissões
                Permission::VIEW_USERS->value,
                Permission::CREATE_USERS->value,
                Permission::EDIT_USERS->value,
                Permission::DELETE_USERS->value,
                Permission::MANAGE_USER_ROLES->value,
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::VIEW_ANY_PROFILE->value,
                Permission::EDIT_ANY_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
                Permission::ACCESS_ADMIN_PANEL->value,
                Permission::ACCESS_SUPPORT_PANEL->value,
                Permission::MANAGE_SETTINGS->value,
                Permission::VIEW_LOGS->value,
                Permission::MANAGE_SYSTEM->value,
                Permission::VIEW_ACTIVITY_LOGS->value,
                Permission::EXPORT_ACTIVITY_LOGS->value,
                Permission::MANAGE_SYSTEM_SETTINGS->value,
                Permission::VIEW_SALES->value,
                Permission::CREATE_SALES->value,
                Permission::EDIT_SALES->value,
                Permission::DELETE_SALES->value,
            ],
            self::ADMIN => [
                // Gerenciamento limitado de usuários
                Permission::VIEW_USERS->value,
                Permission::CREATE_USERS->value,
                Permission::EDIT_USERS->value,
                Permission::DELETE_USERS->value,
                // Não pode gerenciar roles de super admin
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::VIEW_ANY_PROFILE->value,
                Permission::EDIT_ANY_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
                Permission::ACCESS_ADMIN_PANEL->value,
                Permission::ACCESS_SUPPORT_PANEL->value,
                Permission::MANAGE_SETTINGS->value,
                Permission::VIEW_LOGS->value,
                Permission::VIEW_ACTIVITY_LOGS->value,
                Permission::EXPORT_ACTIVITY_LOGS->value,
                Permission::VIEW_SALES->value,
                Permission::CREATE_SALES->value,
                Permission::EDIT_SALES->value,
                Permission::DELETE_SALES->value,
            ],
            self::SUPPORT => [
                // Apenas visualização de usuários
                Permission::VIEW_USERS->value,
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::VIEW_ANY_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
                Permission::ACCESS_SUPPORT_PANEL->value,
                Permission::VIEW_LOGS->value,
                Permission::VIEW_ACTIVITY_LOGS->value,
                Permission::VIEW_SALES->value,
            ],
            self::USER => [
                // Apenas próprio perfil
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
            ],
        };
    }

    public function hasPermissionTo(Permission|string $permission): bool
    {
        $permissionValue = $permission instanceof Permission ? $permission->value : $permission;
        return in_array($permissionValue, $this->permissions());
    }

    public function canManageRole(Role $targetRole): bool
    {
        return match($this) {
            self::SUPER_ADMIN => true, // Pode gerenciar todos
            self::ADMIN => $targetRole !== self::SUPER_ADMIN, // Não pode gerenciar super admin
            self::SUPPORT, self::USER => false, // Não pode gerenciar roles
        };
    }

    public function canEditUser(\App\Models\User $currentUser, \App\Models\User $targetUser): bool
    {
        // Super admin pode editar todos
        if ($this === self::SUPER_ADMIN) {
            return true;
        }

        // Admin pode editar todos exceto super admins
        if ($this === self::ADMIN) {
            return $targetUser->role !== self::SUPER_ADMIN;
        }

        // Support e User só podem editar a si mesmos
        return $currentUser->id === $targetUser->id;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }
}