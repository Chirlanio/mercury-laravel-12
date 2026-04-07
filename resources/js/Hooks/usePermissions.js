import { usePage } from '@inertiajs/react';

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
};

export const ROLES = {
    SUPER_ADMIN: 'super_admin',
    ADMIN: 'admin',
    SUPPORT: 'support',
    USER: 'user',
};

const ROLE_PERMISSIONS = {
    [ROLES.SUPER_ADMIN]: [
        PERMISSIONS.VIEW_USERS,
        PERMISSIONS.CREATE_USERS,
        PERMISSIONS.EDIT_USERS,
        PERMISSIONS.DELETE_USERS,
        PERMISSIONS.MANAGE_USER_ROLES,
        PERMISSIONS.VIEW_OWN_PROFILE,
        PERMISSIONS.EDIT_OWN_PROFILE,
        PERMISSIONS.VIEW_ANY_PROFILE,
        PERMISSIONS.EDIT_ANY_PROFILE,
        PERMISSIONS.ACCESS_DASHBOARD,
        PERMISSIONS.ACCESS_ADMIN_PANEL,
        PERMISSIONS.ACCESS_SUPPORT_PANEL,
        PERMISSIONS.MANAGE_SETTINGS,
        PERMISSIONS.VIEW_LOGS,
        PERMISSIONS.MANAGE_SYSTEM,
        PERMISSIONS.VIEW_ACTIVITY_LOGS,
        PERMISSIONS.EXPORT_ACTIVITY_LOGS,
        PERMISSIONS.MANAGE_SYSTEM_SETTINGS,
        PERMISSIONS.VIEW_SALES,
        PERMISSIONS.CREATE_SALES,
        PERMISSIONS.EDIT_SALES,
        PERMISSIONS.DELETE_SALES,
        PERMISSIONS.VIEW_PRODUCTS,
        PERMISSIONS.EDIT_PRODUCTS,
        PERMISSIONS.SYNC_PRODUCTS,
        PERMISSIONS.VIEW_USER_SESSIONS,
        PERMISSIONS.MANAGE_USER_SESSIONS,
        PERMISSIONS.VIEW_TRANSFERS,
        PERMISSIONS.CREATE_TRANSFERS,
        PERMISSIONS.EDIT_TRANSFERS,
        PERMISSIONS.DELETE_TRANSFERS,
        PERMISSIONS.VIEW_ADJUSTMENTS,
        PERMISSIONS.CREATE_ADJUSTMENTS,
        PERMISSIONS.EDIT_ADJUSTMENTS,
        PERMISSIONS.DELETE_ADJUSTMENTS,
        PERMISSIONS.VIEW_ORDER_PAYMENTS,
        PERMISSIONS.CREATE_ORDER_PAYMENTS,
        PERMISSIONS.EDIT_ORDER_PAYMENTS,
        PERMISSIONS.DELETE_ORDER_PAYMENTS,
        PERMISSIONS.VIEW_SUPPLIERS,
        PERMISSIONS.CREATE_SUPPLIERS,
        PERMISSIONS.EDIT_SUPPLIERS,
        PERMISSIONS.DELETE_SUPPLIERS,
        PERMISSIONS.VIEW_CHECKLISTS,
        PERMISSIONS.CREATE_CHECKLISTS,
        PERMISSIONS.EDIT_CHECKLISTS,
        PERMISSIONS.DELETE_CHECKLISTS,
        PERMISSIONS.VIEW_MEDICAL_CERTIFICATES,
        PERMISSIONS.CREATE_MEDICAL_CERTIFICATES,
        PERMISSIONS.EDIT_MEDICAL_CERTIFICATES,
        PERMISSIONS.DELETE_MEDICAL_CERTIFICATES,
        PERMISSIONS.VIEW_ABSENCES,
        PERMISSIONS.CREATE_ABSENCES,
        PERMISSIONS.EDIT_ABSENCES,
        PERMISSIONS.DELETE_ABSENCES,
        PERMISSIONS.VIEW_OVERTIME,
        PERMISSIONS.CREATE_OVERTIME,
        PERMISSIONS.EDIT_OVERTIME,
        PERMISSIONS.DELETE_OVERTIME,
        PERMISSIONS.VIEW_STORE_GOALS,
        PERMISSIONS.CREATE_STORE_GOALS,
        PERMISSIONS.EDIT_STORE_GOALS,
        PERMISSIONS.DELETE_STORE_GOALS,
    ],
    [ROLES.ADMIN]: [
        PERMISSIONS.VIEW_USERS,
        PERMISSIONS.CREATE_USERS,
        PERMISSIONS.EDIT_USERS,
        PERMISSIONS.DELETE_USERS,
        PERMISSIONS.VIEW_OWN_PROFILE,
        PERMISSIONS.EDIT_OWN_PROFILE,
        PERMISSIONS.VIEW_ANY_PROFILE,
        PERMISSIONS.EDIT_ANY_PROFILE,
        PERMISSIONS.ACCESS_DASHBOARD,
        PERMISSIONS.ACCESS_ADMIN_PANEL,
        PERMISSIONS.ACCESS_SUPPORT_PANEL,
        PERMISSIONS.MANAGE_SETTINGS,
        PERMISSIONS.VIEW_LOGS,
        PERMISSIONS.VIEW_ACTIVITY_LOGS,
        PERMISSIONS.EXPORT_ACTIVITY_LOGS,
        PERMISSIONS.VIEW_SALES,
        PERMISSIONS.CREATE_SALES,
        PERMISSIONS.EDIT_SALES,
        PERMISSIONS.DELETE_SALES,
        PERMISSIONS.VIEW_PRODUCTS,
        PERMISSIONS.EDIT_PRODUCTS,
        PERMISSIONS.SYNC_PRODUCTS,
        PERMISSIONS.VIEW_USER_SESSIONS,
        PERMISSIONS.VIEW_TRANSFERS,
        PERMISSIONS.CREATE_TRANSFERS,
        PERMISSIONS.EDIT_TRANSFERS,
        PERMISSIONS.DELETE_TRANSFERS,
        PERMISSIONS.VIEW_ADJUSTMENTS,
        PERMISSIONS.CREATE_ADJUSTMENTS,
        PERMISSIONS.EDIT_ADJUSTMENTS,
        PERMISSIONS.DELETE_ADJUSTMENTS,
        PERMISSIONS.VIEW_ORDER_PAYMENTS,
        PERMISSIONS.CREATE_ORDER_PAYMENTS,
        PERMISSIONS.EDIT_ORDER_PAYMENTS,
        PERMISSIONS.DELETE_ORDER_PAYMENTS,
        PERMISSIONS.VIEW_SUPPLIERS,
        PERMISSIONS.CREATE_SUPPLIERS,
        PERMISSIONS.EDIT_SUPPLIERS,
        PERMISSIONS.DELETE_SUPPLIERS,
        PERMISSIONS.VIEW_CHECKLISTS,
        PERMISSIONS.CREATE_CHECKLISTS,
        PERMISSIONS.EDIT_CHECKLISTS,
        PERMISSIONS.DELETE_CHECKLISTS,
        PERMISSIONS.VIEW_MEDICAL_CERTIFICATES,
        PERMISSIONS.CREATE_MEDICAL_CERTIFICATES,
        PERMISSIONS.EDIT_MEDICAL_CERTIFICATES,
        PERMISSIONS.DELETE_MEDICAL_CERTIFICATES,
        PERMISSIONS.VIEW_ABSENCES,
        PERMISSIONS.CREATE_ABSENCES,
        PERMISSIONS.EDIT_ABSENCES,
        PERMISSIONS.DELETE_ABSENCES,
        PERMISSIONS.VIEW_OVERTIME,
        PERMISSIONS.CREATE_OVERTIME,
        PERMISSIONS.EDIT_OVERTIME,
        PERMISSIONS.DELETE_OVERTIME,
        PERMISSIONS.VIEW_STORE_GOALS,
        PERMISSIONS.CREATE_STORE_GOALS,
        PERMISSIONS.EDIT_STORE_GOALS,
        PERMISSIONS.DELETE_STORE_GOALS,
    ],
    [ROLES.SUPPORT]: [
        PERMISSIONS.VIEW_USERS,
        PERMISSIONS.VIEW_OWN_PROFILE,
        PERMISSIONS.EDIT_OWN_PROFILE,
        PERMISSIONS.VIEW_ANY_PROFILE,
        PERMISSIONS.ACCESS_DASHBOARD,
        PERMISSIONS.ACCESS_SUPPORT_PANEL,
        PERMISSIONS.VIEW_LOGS,
        PERMISSIONS.VIEW_ACTIVITY_LOGS,
        PERMISSIONS.VIEW_SALES,
        PERMISSIONS.VIEW_PRODUCTS,
        PERMISSIONS.VIEW_USER_SESSIONS,
        PERMISSIONS.VIEW_TRANSFERS,
        PERMISSIONS.VIEW_ADJUSTMENTS,
        PERMISSIONS.VIEW_ORDER_PAYMENTS,
        PERMISSIONS.VIEW_SUPPLIERS,
        PERMISSIONS.VIEW_CHECKLISTS,
        PERMISSIONS.VIEW_MEDICAL_CERTIFICATES,
        PERMISSIONS.VIEW_ABSENCES,
        PERMISSIONS.VIEW_OVERTIME,
        PERMISSIONS.VIEW_STORE_GOALS,
    ],
    [ROLES.USER]: [
        PERMISSIONS.VIEW_OWN_PROFILE,
        PERMISSIONS.EDIT_OWN_PROFILE,
        PERMISSIONS.ACCESS_DASHBOARD,
    ],
};

export function usePermissions() {
    const { props } = usePage();
    const user = props.auth?.user;

    /**
     * Verifica se o usuário tem uma permissão específica
     */
    const hasPermission = (permission) => {
        if (!user || !user.role) return false;

        const userPermissions = ROLE_PERMISSIONS[user.role] || [];
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
                return true; // Pode gerenciar todos
            case ROLES.ADMIN:
                return targetRole !== ROLES.SUPER_ADMIN; // Não pode gerenciar super admin
            case ROLES.SUPPORT:
            case ROLES.USER:
                return false; // Não pode gerenciar roles
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