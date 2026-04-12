<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case SUPPORT = 'support';
    case USER = 'user';
    case DRIVER = 'drivers';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::ADMIN => 'Administrador',
            self::SUPPORT => 'Suporte',
            self::USER => 'Usuário',
            self::DRIVER => 'Motorista',
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
                // Movimentação de Pessoal
                Permission::VIEW_PERSONNEL_MOVEMENTS->value,
                Permission::CREATE_PERSONNEL_MOVEMENTS->value,
                Permission::EDIT_PERSONNEL_MOVEMENTS->value,
                Permission::DELETE_PERSONNEL_MOVEMENTS->value,
                // Treinamentos (todas)
                Permission::VIEW_TRAININGS->value,
                Permission::CREATE_TRAININGS->value,
                Permission::EDIT_TRAININGS->value,
                Permission::DELETE_TRAININGS->value,
                Permission::MANAGE_TRAINING_ATTENDANCE->value,
                Permission::MANAGE_TRAINING_CONTENT->value,
                Permission::VIEW_TRAINING_COURSES->value,
                Permission::CREATE_TRAINING_COURSES->value,
                Permission::EDIT_TRAINING_COURSES->value,
                Permission::DELETE_TRAINING_COURSES->value,
                Permission::MANAGE_TRAINING_QUIZZES->value,
                // Avaliacao de experiencia (todas)
                Permission::VIEW_EXPERIENCE_TRACKER->value,
                Permission::MANAGE_EXPERIENCE_TRACKER->value,
                Permission::FILL_EXPERIENCE_EVALUATION->value,

                // Entregas e rotas (todas)
                Permission::VIEW_DELIVERIES->value,
                Permission::CREATE_DELIVERIES->value,
                Permission::EDIT_DELIVERIES->value,
                Permission::DELETE_DELIVERIES->value,
                Permission::MANAGE_ROUTES->value,
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
                // Movimentação de Pessoal
                Permission::VIEW_PERSONNEL_MOVEMENTS->value,
                Permission::CREATE_PERSONNEL_MOVEMENTS->value,
                Permission::EDIT_PERSONNEL_MOVEMENTS->value,
                Permission::DELETE_PERSONNEL_MOVEMENTS->value,
                // Treinamentos (todas)
                Permission::VIEW_TRAININGS->value,
                Permission::CREATE_TRAININGS->value,
                Permission::EDIT_TRAININGS->value,
                Permission::DELETE_TRAININGS->value,
                Permission::MANAGE_TRAINING_ATTENDANCE->value,
                Permission::MANAGE_TRAINING_CONTENT->value,
                Permission::VIEW_TRAINING_COURSES->value,
                Permission::CREATE_TRAINING_COURSES->value,
                Permission::EDIT_TRAINING_COURSES->value,
                Permission::DELETE_TRAINING_COURSES->value,
                Permission::MANAGE_TRAINING_QUIZZES->value,
                // Avaliacao de experiencia (todas)
                Permission::VIEW_EXPERIENCE_TRACKER->value,
                Permission::MANAGE_EXPERIENCE_TRACKER->value,
                Permission::FILL_EXPERIENCE_EVALUATION->value,

                // Entregas e rotas (todas)
                Permission::VIEW_DELIVERIES->value,
                Permission::CREATE_DELIVERIES->value,
                Permission::EDIT_DELIVERIES->value,
                Permission::DELETE_DELIVERIES->value,
                Permission::MANAGE_ROUTES->value,
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
                // Movimentação de Pessoal (view, create, edit)
                Permission::VIEW_PERSONNEL_MOVEMENTS->value,
                Permission::CREATE_PERSONNEL_MOVEMENTS->value,
                Permission::EDIT_PERSONNEL_MOVEMENTS->value,
                // Treinamentos (view + presença)
                Permission::VIEW_TRAININGS->value,
                Permission::MANAGE_TRAINING_ATTENDANCE->value,
                Permission::VIEW_TRAINING_COURSES->value,
                // Avaliacao de experiencia (view + fill)
                Permission::VIEW_EXPERIENCE_TRACKER->value,
                Permission::FILL_EXPERIENCE_EVALUATION->value,
                // Entregas (view + gerenciar rotas)
                Permission::VIEW_DELIVERIES->value,
                Permission::MANAGE_ROUTES->value,
            ],
            self::USER => [
                // Apenas próprio perfil
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
                // Auditoria de estoque (view + count)
                Permission::VIEW_STOCK_AUDITS->value,
                Permission::COUNT_STOCK_AUDITS->value,
                // Treinamentos (view)
                Permission::VIEW_TRAININGS->value,
                Permission::VIEW_TRAINING_COURSES->value,
                // Entregas (view)
                Permission::VIEW_DELIVERIES->value,
            ],
            self::DRIVER => [
                // Perfil próprio
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
                // Apenas gerenciar rotas (painel do motorista + minhas entregas)
                Permission::MANAGE_ROUTES->value,
            ],
        };
    }

    public function hasPermissionTo(Permission|string $permission): bool
    {
        $permissionValue = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($permissionValue, $this->permissions());
    }

    public function canManageRole(Role|string $targetRole): bool
    {
        $targetValue = $targetRole instanceof Role ? $targetRole->value : (is_object($targetRole) ? $targetRole->value : $targetRole);

        return match ($this) {
            self::SUPER_ADMIN => true,
            self::ADMIN => $targetValue !== self::SUPER_ADMIN->value,
            default => false,
        };
    }

    public function canEditUser(\App\Models\User $currentUser, \App\Models\User $targetUser): bool
    {
        if ($this === self::SUPER_ADMIN) {
            return true;
        }

        if ($this === self::ADMIN) {
            return $targetUser->role?->value !== self::SUPER_ADMIN->value;
        }

        return $currentUser->id === $targetUser->id;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }
}
