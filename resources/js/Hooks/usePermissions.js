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

    // Ordens de Compra
    VIEW_PURCHASE_ORDERS: 'purchase_orders.view',
    CREATE_PURCHASE_ORDERS: 'purchase_orders.create',
    EDIT_PURCHASE_ORDERS: 'purchase_orders.edit',
    DELETE_PURCHASE_ORDERS: 'purchase_orders.delete',
    APPROVE_PURCHASE_ORDERS: 'purchase_orders.approve',
    CANCEL_PURCHASE_ORDERS: 'purchase_orders.cancel',
    RECEIVE_PURCHASE_ORDERS: 'purchase_orders.receive',
    IMPORT_PURCHASE_ORDERS: 'purchase_orders.import',
    EXPORT_PURCHASE_ORDERS: 'purchase_orders.export',
    MANAGE_PURCHASE_ORDERS: 'purchase_orders.manage',

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

    // Movimentação de Pessoal
    VIEW_PERSONNEL_MOVEMENTS: 'personnel_movements.view',
    CREATE_PERSONNEL_MOVEMENTS: 'personnel_movements.create',
    EDIT_PERSONNEL_MOVEMENTS: 'personnel_movements.edit',
    DELETE_PERSONNEL_MOVEMENTS: 'personnel_movements.delete',

    // Abertura de Vagas
    VIEW_VACANCIES: 'vacancies.view',
    CREATE_VACANCIES: 'vacancies.create',
    EDIT_VACANCIES: 'vacancies.edit',
    DELETE_VACANCIES: 'vacancies.delete',
    MANAGE_VACANCIES: 'vacancies.manage',

    // Treinamentos
    VIEW_TRAININGS: 'trainings.view',
    CREATE_TRAININGS: 'trainings.create',
    EDIT_TRAININGS: 'trainings.edit',
    DELETE_TRAININGS: 'trainings.delete',
    MANAGE_TRAINING_ATTENDANCE: 'trainings.manage_attendance',
    MANAGE_TRAINING_CONTENT: 'training_content.manage',
    VIEW_TRAINING_COURSES: 'training_courses.view',
    CREATE_TRAINING_COURSES: 'training_courses.create',
    EDIT_TRAINING_COURSES: 'training_courses.edit',
    DELETE_TRAINING_COURSES: 'training_courses.delete',
    MANAGE_TRAINING_QUIZZES: 'training_quizzes.manage',

    // Avaliacao de experiencia
    VIEW_EXPERIENCE_TRACKER: 'experience_tracker.view',
    MANAGE_EXPERIENCE_TRACKER: 'experience_tracker.manage',
    FILL_EXPERIENCE_EVALUATION: 'experience_tracker.fill',

    // Entregas e rotas
    VIEW_DELIVERIES: 'deliveries.view',
    CREATE_DELIVERIES: 'deliveries.create',
    EDIT_DELIVERIES: 'deliveries.edit',
    DELETE_DELIVERIES: 'deliveries.delete',
    MANAGE_ROUTES: 'delivery_routes.manage',
    // Chat
    VIEW_CHAT: 'chat.view',
    SEND_CHAT_MESSAGES: 'chat.send',
    CREATE_CHAT_GROUPS: 'chat.create_groups',
    MANAGE_CHAT_GROUPS: 'chat.manage_groups',
    SEND_BROADCASTS: 'chat.send_broadcasts',
    MANAGE_BROADCASTS: 'chat.manage_broadcasts',
    // Helpdesk
    VIEW_HELPDESK: 'helpdesk.view',
    CREATE_TICKETS: 'helpdesk.create_tickets',
    MANAGE_TICKETS: 'helpdesk.manage_tickets',
    MANAGE_HD_DEPARTMENTS: 'helpdesk.manage_departments',
    VIEW_HD_REPORTS: 'helpdesk.view_reports',
    MANAGE_HD_PERMISSIONS: 'helpdesk.manage_permissions',
    // TaneIA
    VIEW_TANEIA: 'taneia.view',
    SEND_TANEIA_MESSAGES: 'taneia.send',
    MANAGE_TANEIA: 'taneia.manage',

    // Estornos (Reversals)
    VIEW_REVERSALS: 'reversals.view',
    CREATE_REVERSALS: 'reversals.create',
    EDIT_REVERSALS: 'reversals.edit',
    DELETE_REVERSALS: 'reversals.delete',
    APPROVE_REVERSALS: 'reversals.approve',
    PROCESS_REVERSALS: 'reversals.process',
    MANAGE_REVERSALS: 'reversals.manage',
    IMPORT_REVERSALS: 'reversals.import',
    EXPORT_REVERSALS: 'reversals.export',
    MANAGE_REVERSAL_REASONS: 'reversals.manage_reasons',

    // Devoluções / Trocas (Returns)
    VIEW_RETURNS: 'returns.view',
    CREATE_RETURNS: 'returns.create',
    EDIT_RETURNS: 'returns.edit',
    APPROVE_RETURNS: 'returns.approve',
    PROCESS_RETURNS: 'returns.process',
    CANCEL_RETURNS: 'returns.cancel',
    DELETE_RETURNS: 'returns.delete',
    MANAGE_RETURNS: 'returns.manage',
    IMPORT_RETURNS: 'returns.import',
    EXPORT_RETURNS: 'returns.export',
    MANAGE_RETURN_REASONS: 'returns.manage_reasons',

    // Cupons
    VIEW_COUPONS: 'coupons.view',
    CREATE_COUPONS: 'coupons.create',
    EDIT_COUPONS: 'coupons.edit',
    DELETE_COUPONS: 'coupons.delete',
    MANAGE_COUPONS: 'coupons.manage',
    ISSUE_COUPON_CODE: 'coupons.issue_code',
    IMPORT_COUPONS: 'coupons.import',
    EXPORT_COUPONS: 'coupons.export',

    // Centros de Custo
    VIEW_COST_CENTERS: 'cost_centers.view',
    CREATE_COST_CENTERS: 'cost_centers.create',
    EDIT_COST_CENTERS: 'cost_centers.edit',
    DELETE_COST_CENTERS: 'cost_centers.delete',
    MANAGE_COST_CENTERS: 'cost_centers.manage',
    IMPORT_COST_CENTERS: 'cost_centers.import',
    EXPORT_COST_CENTERS: 'cost_centers.export',

    // Plano de Contas
    VIEW_ACCOUNTING_CLASSES: 'accounting_classes.view',
    CREATE_ACCOUNTING_CLASSES: 'accounting_classes.create',
    EDIT_ACCOUNTING_CLASSES: 'accounting_classes.edit',
    DELETE_ACCOUNTING_CLASSES: 'accounting_classes.delete',
    MANAGE_ACCOUNTING_CLASSES: 'accounting_classes.manage',
    IMPORT_ACCOUNTING_CLASSES: 'accounting_classes.import',
    EXPORT_ACCOUNTING_CLASSES: 'accounting_classes.export',

    // Plano Gerencial
    VIEW_MANAGEMENT_CLASSES: 'management_classes.view',
    CREATE_MANAGEMENT_CLASSES: 'management_classes.create',
    EDIT_MANAGEMENT_CLASSES: 'management_classes.edit',
    DELETE_MANAGEMENT_CLASSES: 'management_classes.delete',
    MANAGE_MANAGEMENT_CLASSES: 'management_classes.manage',
    IMPORT_MANAGEMENT_CLASSES: 'management_classes.import',
    EXPORT_MANAGEMENT_CLASSES: 'management_classes.export',

    // Orçamentos
    VIEW_BUDGETS: 'budgets.view',
    UPLOAD_BUDGETS: 'budgets.upload',
    DOWNLOAD_BUDGETS: 'budgets.download',
    DELETE_BUDGETS: 'budgets.delete',
    MANAGE_BUDGETS: 'budgets.manage',
    EXPORT_BUDGETS: 'budgets.export',
    VIEW_BUDGET_CONSUMPTION: 'budgets.view_consumption',

    // DRE
    VIEW_DRE: 'dre.view',
    MANAGE_DRE_STRUCTURE: 'dre.manage_structure',
    MANAGE_DRE_MAPPINGS: 'dre.manage_mappings',
    VIEW_DRE_PENDING_ACCOUNTS: 'dre.view_pending_accounts',
    IMPORT_DRE_ACTUALS: 'dre.import_actuals',
    IMPORT_DRE_BUDGETS: 'dre.import_budgets',
    MANAGE_DRE_PERIODS: 'dre.manage_periods',
    EXPORT_DRE: 'dre.export',
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
