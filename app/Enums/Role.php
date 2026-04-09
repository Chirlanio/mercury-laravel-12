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
        return match ($this) {
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
        return match ($this) {
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
                Permission::VIEW_PRODUCTS->value,
                Permission::EDIT_PRODUCTS->value,
                Permission::SYNC_PRODUCTS->value,
                Permission::VIEW_USER_SESSIONS->value,
                Permission::MANAGE_USER_SESSIONS->value,
                Permission::VIEW_TRANSFERS->value,
                Permission::CREATE_TRANSFERS->value,
                Permission::EDIT_TRANSFERS->value,
                Permission::DELETE_TRANSFERS->value,
                Permission::VIEW_ADJUSTMENTS->value,
                Permission::CREATE_ADJUSTMENTS->value,
                Permission::EDIT_ADJUSTMENTS->value,
                Permission::DELETE_ADJUSTMENTS->value,
                Permission::VIEW_ORDER_PAYMENTS->value,
                Permission::CREATE_ORDER_PAYMENTS->value,
                Permission::EDIT_ORDER_PAYMENTS->value,
                Permission::DELETE_ORDER_PAYMENTS->value,
                Permission::VIEW_SUPPLIERS->value,
                Permission::CREATE_SUPPLIERS->value,
                Permission::EDIT_SUPPLIERS->value,
                Permission::DELETE_SUPPLIERS->value,
                Permission::VIEW_CHECKLISTS->value,
                Permission::CREATE_CHECKLISTS->value,
                Permission::EDIT_CHECKLISTS->value,
                Permission::DELETE_CHECKLISTS->value,
                Permission::VIEW_MEDICAL_CERTIFICATES->value,
                Permission::CREATE_MEDICAL_CERTIFICATES->value,
                Permission::EDIT_MEDICAL_CERTIFICATES->value,
                Permission::DELETE_MEDICAL_CERTIFICATES->value,
                Permission::VIEW_ABSENCES->value,
                Permission::CREATE_ABSENCES->value,
                Permission::EDIT_ABSENCES->value,
                Permission::DELETE_ABSENCES->value,
                Permission::VIEW_OVERTIME->value,
                Permission::CREATE_OVERTIME->value,
                Permission::EDIT_OVERTIME->value,
                Permission::DELETE_OVERTIME->value,
                Permission::VIEW_STORE_GOALS->value,
                Permission::CREATE_STORE_GOALS->value,
                Permission::EDIT_STORE_GOALS->value,
                Permission::DELETE_STORE_GOALS->value,
                Permission::VIEW_MOVEMENTS->value,
                Permission::SYNC_MOVEMENTS->value,
                // Férias (todas)
                Permission::VIEW_VACATIONS->value,
                Permission::CREATE_VACATIONS->value,
                Permission::EDIT_VACATIONS->value,
                Permission::DELETE_VACATIONS->value,
                Permission::APPROVE_VACATIONS_MANAGER->value,
                Permission::APPROVE_VACATIONS_RH->value,
                Permission::MANAGE_HOLIDAYS->value,
                // Auditoria de estoque (todas)
                Permission::VIEW_STOCK_AUDITS->value,
                Permission::CREATE_STOCK_AUDITS->value,
                Permission::EDIT_STOCK_AUDITS->value,
                Permission::DELETE_STOCK_AUDITS->value,
                Permission::AUTHORIZE_STOCK_AUDITS->value,
                Permission::COUNT_STOCK_AUDITS->value,
                Permission::RECONCILE_STOCK_AUDITS->value,
                Permission::MANAGE_STOCK_AUDIT_CONFIG->value,
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
                Permission::VIEW_PRODUCTS->value,
                Permission::EDIT_PRODUCTS->value,
                Permission::SYNC_PRODUCTS->value,
                Permission::VIEW_USER_SESSIONS->value,
                Permission::VIEW_TRANSFERS->value,
                Permission::CREATE_TRANSFERS->value,
                Permission::EDIT_TRANSFERS->value,
                Permission::DELETE_TRANSFERS->value,
                Permission::VIEW_ADJUSTMENTS->value,
                Permission::CREATE_ADJUSTMENTS->value,
                Permission::EDIT_ADJUSTMENTS->value,
                Permission::DELETE_ADJUSTMENTS->value,
                Permission::VIEW_ORDER_PAYMENTS->value,
                Permission::CREATE_ORDER_PAYMENTS->value,
                Permission::EDIT_ORDER_PAYMENTS->value,
                Permission::DELETE_ORDER_PAYMENTS->value,
                Permission::VIEW_SUPPLIERS->value,
                Permission::CREATE_SUPPLIERS->value,
                Permission::EDIT_SUPPLIERS->value,
                Permission::DELETE_SUPPLIERS->value,
                Permission::VIEW_CHECKLISTS->value,
                Permission::CREATE_CHECKLISTS->value,
                Permission::EDIT_CHECKLISTS->value,
                Permission::DELETE_CHECKLISTS->value,
                Permission::VIEW_MEDICAL_CERTIFICATES->value,
                Permission::CREATE_MEDICAL_CERTIFICATES->value,
                Permission::EDIT_MEDICAL_CERTIFICATES->value,
                Permission::DELETE_MEDICAL_CERTIFICATES->value,
                Permission::VIEW_ABSENCES->value,
                Permission::CREATE_ABSENCES->value,
                Permission::EDIT_ABSENCES->value,
                Permission::DELETE_ABSENCES->value,
                Permission::VIEW_OVERTIME->value,
                Permission::CREATE_OVERTIME->value,
                Permission::EDIT_OVERTIME->value,
                Permission::DELETE_OVERTIME->value,
                Permission::VIEW_STORE_GOALS->value,
                Permission::CREATE_STORE_GOALS->value,
                Permission::EDIT_STORE_GOALS->value,
                Permission::DELETE_STORE_GOALS->value,
                Permission::VIEW_MOVEMENTS->value,
                Permission::SYNC_MOVEMENTS->value,
                // Férias (CRUD + aprovação RH)
                Permission::VIEW_VACATIONS->value,
                Permission::CREATE_VACATIONS->value,
                Permission::EDIT_VACATIONS->value,
                Permission::DELETE_VACATIONS->value,
                Permission::APPROVE_VACATIONS_MANAGER->value,
                Permission::APPROVE_VACATIONS_RH->value,
                Permission::MANAGE_HOLIDAYS->value,
                // Auditoria de estoque (todas)
                Permission::VIEW_STOCK_AUDITS->value,
                Permission::CREATE_STOCK_AUDITS->value,
                Permission::EDIT_STOCK_AUDITS->value,
                Permission::DELETE_STOCK_AUDITS->value,
                Permission::AUTHORIZE_STOCK_AUDITS->value,
                Permission::COUNT_STOCK_AUDITS->value,
                Permission::RECONCILE_STOCK_AUDITS->value,
                Permission::MANAGE_STOCK_AUDIT_CONFIG->value,
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
                Permission::VIEW_PRODUCTS->value,
                Permission::VIEW_USER_SESSIONS->value,
                Permission::VIEW_TRANSFERS->value,
                Permission::VIEW_ADJUSTMENTS->value,
                Permission::VIEW_ORDER_PAYMENTS->value,
                Permission::VIEW_SUPPLIERS->value,
                Permission::VIEW_CHECKLISTS->value,
                Permission::VIEW_MEDICAL_CERTIFICATES->value,
                Permission::VIEW_ABSENCES->value,
                Permission::VIEW_OVERTIME->value,
                Permission::VIEW_STORE_GOALS->value,
                Permission::VIEW_MOVEMENTS->value,
                // Férias (visualização + aprovação gestor)
                Permission::VIEW_VACATIONS->value,
                Permission::CREATE_VACATIONS->value,
                Permission::EDIT_VACATIONS->value,
                Permission::APPROVE_VACATIONS_MANAGER->value,
                // Auditoria de estoque (view, create, edit, count, reconcile)
                Permission::VIEW_STOCK_AUDITS->value,
                Permission::CREATE_STOCK_AUDITS->value,
                Permission::EDIT_STOCK_AUDITS->value,
                Permission::COUNT_STOCK_AUDITS->value,
                Permission::RECONCILE_STOCK_AUDITS->value,
            ],
            self::USER => [
                // Apenas próprio perfil
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
                // Auditoria de estoque (view + count)
                Permission::VIEW_STOCK_AUDITS->value,
                Permission::COUNT_STOCK_AUDITS->value,
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
        return match ($this) {
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
