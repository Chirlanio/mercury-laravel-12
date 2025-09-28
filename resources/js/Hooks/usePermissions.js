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
    };
}