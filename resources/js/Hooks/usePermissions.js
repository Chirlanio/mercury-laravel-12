import { usePage } from '@inertiajs/react';

/**
 * Permission slug constants for reference.
 * Actual permission checking reads from backend-provided props (CentralRoleResolver).
 */
export const PERMISSIONS = {
    // Gestão de usuários
    VIEW_USERS: 'users.view',
    CREATE_USERS: 'users.create',
    EDIT_USERS: 'users.edit',
    DELETE_USERS: 'users.delete',
    MANAGE_USER_ROLES: 'users.manage_roles',

    // Gestão de perfil
    VIEW_OWN_PROFILE: 'profile.view_own',
    EDIT_OWN_PROFILE: 'profile.edit_own',
    VIEW_ANY_PROFILE: 'profile.view_any',
    EDIT_ANY_PROFILE: 'profile.edit_any',

    // Acesso ao sistema
    ACCESS_DASHBOARD: 'dashboard.access',
    ACCESS_ADMIN_PANEL: 'admin.access',
    ACCESS_SUPPORT_PANEL: 'support.access',

    // Configurações do sistema
    MANAGE_SETTINGS: 'settings.manage',
    VIEW_LOGS: 'logs.view',
    MANAGE_SYSTEM: 'system.manage',

    // Logs de atividade
    VIEW_ACTIVITY_LOGS: 'activity_logs.view',
    EXPORT_ACTIVITY_LOGS: 'activity_logs.export',
    MANAGE_SYSTEM_SETTINGS: 'system_settings.manage',

    // Gestão comercial
    VIEW_SALES: 'sales.view',
    CREATE_SALES: 'sales.create',
    EDIT_SALES: 'sales.edit',
    DELETE_SALES: 'sales.delete',

    // Gestão de produtos
    VIEW_PRODUCTS: 'products.view',
    EDIT_PRODUCTS: 'products.edit',
    SYNC_PRODUCTS: 'products.sync',

    // Usuários online
    VIEW_USER_SESSIONS: 'user_sessions.view',
    MANAGE_USER_SESSIONS: 'user_sessions.manage',

    // Transferências
    VIEW_TRANSFERS: 'transfers.view',
    CREATE_TRANSFERS: 'transfers.create',
    EDIT_TRANSFERS: 'transfers.edit',
    DELETE_TRANSFERS: 'transfers.delete',

    // Ajustes de estoque
    VIEW_ADJUSTMENTS: 'adjustments.view',
    CREATE_ADJUSTMENTS: 'adjustments.create',
    EDIT_ADJUSTMENTS: 'adjustments.edit',
    DELETE_ADJUSTMENTS: 'adjustments.delete',

    // Fornecedores
    VIEW_SUPPLIERS: 'suppliers.view',
    CREATE_SUPPLIERS: 'suppliers.create',
    EDIT_SUPPLIERS: 'suppliers.edit',
    DELETE_SUPPLIERS: 'suppliers.delete',

    // Ordens de pagamento
    VIEW_ORDER_PAYMENTS: 'order_payments.view',
    CREATE_ORDER_PAYMENTS: 'order_payments.create',
    EDIT_ORDER_PAYMENTS: 'order_payments.edit',
    DELETE_ORDER_PAYMENTS: 'order_payments.delete',

    // Checklists de qualidade
    VIEW_CHECKLISTS: 'checklists.view',
    CREATE_CHECKLISTS: 'checklists.create',
    EDIT_CHECKLISTS: 'checklists.edit',
    DELETE_CHECKLISTS: 'checklists.delete',

    // Atestados medicos
    VIEW_MEDICAL_CERTIFICATES: 'medical_certificates.view',
    CREATE_MEDICAL_CERTIFICATES: 'medical_certificates.create',
    EDIT_MEDICAL_CERTIFICATES: 'medical_certificates.edit',
    DELETE_MEDICAL_CERTIFICATES: 'medical_certificates.delete',

    // Controle de faltas
    VIEW_ABSENCES: 'absences.view',
    CREATE_ABSENCES: 'absences.create',
    EDIT_ABSENCES: 'absences.edit',
    DELETE_ABSENCES: 'absences.delete',

    // Controle de horas extras
    VIEW_OVERTIME: 'overtime.view',
    CREATE_OVERTIME: 'overtime.create',
    EDIT_OVERTIME: 'overtime.edit',
    DELETE_OVERTIME: 'overtime.delete',

    // Metas de loja
    VIEW_STORE_GOALS: 'store_goals.view',
    CREATE_STORE_GOALS: 'store_goals.create',
    EDIT_STORE_GOALS: 'store_goals.edit',
    DELETE_STORE_GOALS: 'store_goals.delete',
    VIEW_MOVEMENTS: 'movements.view',
    SYNC_MOVEMENTS: 'movements.sync',
    // Férias
    VIEW_VACATIONS: 'vacations.view',
    CREATE_VACATIONS: 'vacations.create',
    EDIT_VACATIONS: 'vacations.edit',
    DELETE_VACATIONS: 'vacations.delete',
    APPROVE_VACATIONS_MANAGER: 'vacations.approve_manager',
    APPROVE_VACATIONS_RH: 'vacations.approve_rh',
    MANAGE_HOLIDAYS: 'vacations.manage_holidays',

    // Auditoria de estoque
    VIEW_STOCK_AUDITS: 'stock_audits.view',
    CREATE_STOCK_AUDITS: 'stock_audits.create',
    EDIT_STOCK_AUDITS: 'stock_audits.edit',
    DELETE_STOCK_AUDITS: 'stock_audits.delete',
    AUTHORIZE_STOCK_AUDITS: 'stock_audits.authorize',
    COUNT_STOCK_AUDITS: 'stock_audits.count',
    RECONCILE_STOCK_AUDITS: 'stock_audits.reconcile',
    MANAGE_STOCK_AUDIT_CONFIG: 'stock_audits.manage_config',
};

export const ROLES = {
    SUPER_ADMIN: 'super_admin',
    ADMIN: 'admin',
    SUPPORT: 'support',
    USER: 'user',
};

export function usePermissions() {
    const { props } = usePage();
    const user = props.auth?.user;
    const userPermissions = props.auth?.permissions || [];

    /**
     * Verifica se o usuário tem uma permissão específica.
     * Lê das permissões fornecidas pelo backend (CentralRoleResolver).
     */
    const hasPermission = (permission) => {
        if (!user || !user.role) return false;
        return userPermissions.includes(permission);
    };

    /**
     * Verifica se o usuário tem pelo menos uma das permissões
     */
    const hasAnyPermission = (permissions) => {
        return permissions.some(permission => hasPermission(permission));
    };

    /**
     * Verifica se o usuário tem todas as permissões
     */
    const hasAllPermissions = (permissions) => {
        return permissions.every(permission => hasPermission(permission));
    };

    /**
     * Verifica se o usuário tem um role específico
     */
    const hasRole = (role) => {
        return user?.role === role;
    };

    /**
     * Verifica se o usuário pode editar outro usuário
     */
    const canEditUser = (targetUser) => {
        if (!user || !targetUser) return false;

        // Super admin pode editar todos
        if (user.role === ROLES.SUPER_ADMIN) return true;

        // Admin pode editar todos exceto super admins
        if (user.role === ROLES.ADMIN) {
            return targetUser.role !== ROLES.SUPER_ADMIN;
        }

        // Support e User só podem editar a si mesmos
        return user.id === targetUser.id;
    };

    /**
     * Verifica se o usuário pode gerenciar um role específico
     */
    const canManageRole = (targetRole) => {
        if (!user) return false;

        switch (user.role) {
            case ROLES.SUPER_ADMIN:
                return true;
            case ROLES.ADMIN:
                return targetRole !== ROLES.SUPER_ADMIN;
            case ROLES.SUPPORT:
            case ROLES.USER:
                return false;
            default:
                return false;
        }
    };

    /**
     * Verifica se o usuário pode deletar outro usuário
     */
    const canDeleteUser = (targetUser) => {
        if (!user || !targetUser) return false;

        // Não pode deletar a si mesmo
        if (user.id === targetUser.id) return false;

        return canEditUser(targetUser);
    };

    /**
     * Verifica hierarquia de roles
     */
    const hasRoleLevel = (minimumRole) => {
        const hierarchy = {
            [ROLES.USER]: 1,
            [ROLES.SUPPORT]: 2,
            [ROLES.ADMIN]: 3,
            [ROLES.SUPER_ADMIN]: 4,
        };

        const userLevel = hierarchy[user?.role] || 0;
        const requiredLevel = hierarchy[minimumRole] || 0;

        return userLevel >= requiredLevel;
    };

    return {
        user,
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        hasRole,
        hasRoleLevel,
        canEditUser,
        canManageRole,
        canDeleteUser,
        // Atalhos para roles comuns
        isSuperAdmin: () => hasRole(ROLES.SUPER_ADMIN),
        isAdmin: () => hasRole(ROLES.ADMIN),
        isSupport: () => hasRole(ROLES.SUPPORT),
        isUser: () => hasRole(ROLES.USER),
        // Atalhos para permissões comuns
        canViewUsers: () => hasPermission(PERMISSIONS.VIEW_USERS),
        canCreateUsers: () => hasPermission(PERMISSIONS.CREATE_USERS),
        canEditUsers: () => hasPermission(PERMISSIONS.EDIT_USERS),
        canDeleteUsers: () => hasPermission(PERMISSIONS.DELETE_USERS),
        canManageUserRoles: () => hasPermission(PERMISSIONS.MANAGE_USER_ROLES),
        canViewProducts: () => hasPermission(PERMISSIONS.VIEW_PRODUCTS),
        canEditProducts: () => hasPermission(PERMISSIONS.EDIT_PRODUCTS),
        canSyncProducts: () => hasPermission(PERMISSIONS.SYNC_PRODUCTS),
    };
}
