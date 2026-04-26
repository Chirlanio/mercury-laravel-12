<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case FINANCE = 'finance';
    case ACCOUNTING = 'accounting';
    case FISCAL = 'fiscal';
    case MARKETING = 'marketing';
    case COMMERCIAL_SUPERVISOR = 'commercial_supervisor';
    case MANAGER = 'manager';
    case STORE = 'store';
    case SUPPORT = 'support';
    case USER = 'user';
    case DRIVER = 'drivers';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::ADMIN => 'Administrador',
            self::FINANCE => 'Financeira',
            self::ACCOUNTING => 'Contabilidade',
            self::FISCAL => 'Fiscal',
            self::MARKETING => 'Marketing',
            self::COMMERCIAL_SUPERVISOR => 'Supervisão Comercial',
            self::MANAGER => 'Gerente',
            self::STORE => 'Lojas',
            self::SUPPORT => 'Suporte',
            self::USER => 'Usuário',
            self::DRIVER => 'Motorista',
        };
    }

    public function hasPermission(Role $requiredRole): bool
    {
        // Escala com gaps intencionais (1, 2, 8, 9, 10) — deixa espaço para
        // adicionar roles intermediárias no futuro sem shift em cascata.
        $hierarchy = [
            self::USER->value => 1,
            self::DRIVER->value => 1,
            self::SUPPORT->value => 2,
            self::STORE->value => 3,
            self::MANAGER->value => 5,
            self::COMMERCIAL_SUPERVISOR->value => 7,
            self::FINANCE->value => 8,
            self::ACCOUNTING->value => 8,
            self::FISCAL->value => 8,
            self::MARKETING->value => 8,
            self::ADMIN->value => 9,
            self::SUPER_ADMIN->value => 10,
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
                // Ordens de Compra (todas)
                Permission::VIEW_PURCHASE_ORDERS->value,
                Permission::CREATE_PURCHASE_ORDERS->value,
                Permission::EDIT_PURCHASE_ORDERS->value,
                Permission::DELETE_PURCHASE_ORDERS->value,
                Permission::APPROVE_PURCHASE_ORDERS->value,
                Permission::CANCEL_PURCHASE_ORDERS->value,
                Permission::RECEIVE_PURCHASE_ORDERS->value,
                Permission::IMPORT_PURCHASE_ORDERS->value,
                Permission::EXPORT_PURCHASE_ORDERS->value,
                Permission::MANAGE_PURCHASE_ORDERS->value,
                Permission::MANAGE_PURCHASE_ORDER_SIZE_MAPPINGS->value,
                Permission::MANAGE_PURCHASE_ORDER_BRAND_ALIASES->value,
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
                // Abertura de Vagas (todas)
                Permission::VIEW_VACANCIES->value,
                Permission::CREATE_VACANCIES->value,
                Permission::EDIT_VACANCIES->value,
                Permission::DELETE_VACANCIES->value,
                Permission::MANAGE_VACANCIES->value,
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
                // Chat (todas)
                Permission::VIEW_CHAT->value,
                Permission::SEND_CHAT_MESSAGES->value,
                Permission::CREATE_CHAT_GROUPS->value,
                Permission::MANAGE_CHAT_GROUPS->value,
                Permission::SEND_BROADCASTS->value,
                Permission::MANAGE_BROADCASTS->value,
                // Helpdesk (todas)
                Permission::VIEW_HELPDESK->value,
                Permission::CREATE_TICKETS->value,
                Permission::MANAGE_TICKETS->value,
                Permission::MANAGE_HD_DEPARTMENTS->value,
                Permission::VIEW_HD_REPORTS->value,
                Permission::MANAGE_HD_PERMISSIONS->value,
                // TaneIA (todas)
                Permission::VIEW_TANEIA->value,
                Permission::SEND_TANEIA_MESSAGES->value,
                Permission::MANAGE_TANEIA->value,
                // Estornos (todas)
                Permission::VIEW_REVERSALS->value,
                Permission::CREATE_REVERSALS->value,
                Permission::EDIT_REVERSALS->value,
                Permission::DELETE_REVERSALS->value,
                Permission::APPROVE_REVERSALS->value,
                Permission::PROCESS_REVERSALS->value,
                Permission::MANAGE_REVERSALS->value,
                Permission::IMPORT_REVERSALS->value,
                Permission::EXPORT_REVERSALS->value,
                Permission::MANAGE_REVERSAL_REASONS->value,
                // Devoluções / Trocas (todas)
                Permission::VIEW_RETURNS->value,
                Permission::CREATE_RETURNS->value,
                Permission::EDIT_RETURNS->value,
                Permission::APPROVE_RETURNS->value,
                Permission::PROCESS_RETURNS->value,
                Permission::CANCEL_RETURNS->value,
                Permission::DELETE_RETURNS->value,
                Permission::MANAGE_RETURNS->value,
                Permission::IMPORT_RETURNS->value,
                Permission::EXPORT_RETURNS->value,
                Permission::MANAGE_RETURN_REASONS->value,
                // Centros de Custo (todas)
                Permission::VIEW_COST_CENTERS->value,
                Permission::CREATE_COST_CENTERS->value,
                Permission::EDIT_COST_CENTERS->value,
                Permission::DELETE_COST_CENTERS->value,
                Permission::MANAGE_COST_CENTERS->value,
                Permission::IMPORT_COST_CENTERS->value,
                Permission::EXPORT_COST_CENTERS->value,
                // Plano de Contas Contábil (todas)
                Permission::VIEW_ACCOUNTING_CLASSES->value,
                Permission::CREATE_ACCOUNTING_CLASSES->value,
                Permission::EDIT_ACCOUNTING_CLASSES->value,
                Permission::DELETE_ACCOUNTING_CLASSES->value,
                Permission::MANAGE_ACCOUNTING_CLASSES->value,
                Permission::IMPORT_ACCOUNTING_CLASSES->value,
                Permission::EXPORT_ACCOUNTING_CLASSES->value,
                // Plano de Contas Gerencial (todas)
                Permission::VIEW_MANAGEMENT_CLASSES->value,
                Permission::CREATE_MANAGEMENT_CLASSES->value,
                Permission::EDIT_MANAGEMENT_CLASSES->value,
                Permission::DELETE_MANAGEMENT_CLASSES->value,
                Permission::MANAGE_MANAGEMENT_CLASSES->value,
                Permission::IMPORT_MANAGEMENT_CLASSES->value,
                Permission::EXPORT_MANAGEMENT_CLASSES->value,
                // Orçamentos (todas)
                Permission::VIEW_BUDGETS->value,
                Permission::UPLOAD_BUDGETS->value,
                Permission::DOWNLOAD_BUDGETS->value,
                Permission::DELETE_BUDGETS->value,
                Permission::MANAGE_BUDGETS->value,
                Permission::EXPORT_BUDGETS->value,
                Permission::VIEW_BUDGET_CONSUMPTION->value,
                // DRE (todas)
                Permission::VIEW_DRE->value,
                Permission::MANAGE_DRE_STRUCTURE->value,
                Permission::MANAGE_DRE_MAPPINGS->value,
                Permission::VIEW_DRE_PENDING_ACCOUNTS->value,
                Permission::IMPORT_DRE_ACTUALS->value,
                Permission::IMPORT_DRE_BUDGETS->value,
                Permission::MANAGE_DRE_PERIODS->value,
                Permission::EXPORT_DRE->value,
                // Cupons (todas)
                Permission::VIEW_COUPONS->value,
                Permission::CREATE_COUPONS->value,
                Permission::EDIT_COUPONS->value,
                Permission::DELETE_COUPONS->value,
                Permission::MANAGE_COUPONS->value,
                Permission::ISSUE_COUPON_CODE->value,
                Permission::IMPORT_COUPONS->value,
                Permission::EXPORT_COUPONS->value,
                // Consignações (todas)
                Permission::VIEW_CONSIGNMENTS->value,
                Permission::CREATE_CONSIGNMENTS->value,
                Permission::EDIT_CONSIGNMENTS->value,
                Permission::DELETE_CONSIGNMENTS->value,
                Permission::MANAGE_CONSIGNMENTS->value,
                Permission::REGISTER_CONSIGNMENT_RETURN->value,
                Permission::COMPLETE_CONSIGNMENT->value,
                Permission::CANCEL_CONSIGNMENT->value,
                Permission::EXPORT_CONSIGNMENTS->value,
                Permission::IMPORT_CONSIGNMENTS->value,
                Permission::OVERRIDE_CONSIGNMENT_LOCK->value,
                // Clientes (todas)
                Permission::VIEW_CUSTOMERS->value,
                Permission::EXPORT_CUSTOMERS->value,
                Permission::SYNC_CUSTOMERS->value,
                // Clientes VIP (todas)
                Permission::VIEW_VIP_CUSTOMERS->value,
                Permission::MANAGE_VIP_CUSTOMERS->value,
                Permission::CURATE_VIP_CUSTOMERS->value,
                Permission::VIEW_VIP_REPORTS->value,
                Permission::MANAGE_VIP_ACTIVITIES->value,
                Permission::MANAGE_VIP_TIER_CONFIG->value,
                Permission::IMPORT_VIP_CUSTOMERS->value,
                // Verbas de Viagem (todas)
                Permission::VIEW_TRAVEL_EXPENSES->value,
                Permission::CREATE_TRAVEL_EXPENSES->value,
                Permission::EDIT_TRAVEL_EXPENSES->value,
                Permission::DELETE_TRAVEL_EXPENSES->value,
                Permission::APPROVE_TRAVEL_EXPENSES->value,
                Permission::MANAGE_TRAVEL_EXPENSES->value,
                Permission::MANAGE_ACCOUNTABILITY->value,
                Permission::EXPORT_TRAVEL_EXPENSES->value,
                // Lista da Vez (todas)
                Permission::VIEW_TURN_LIST->value,
                Permission::OPERATE_TURN_LIST->value,
                Permission::MANAGE_TURN_LIST->value,
                Permission::MANAGE_TURN_LIST_OUTCOMES->value,
                Permission::MANAGE_TURN_LIST_BREAK_TYPES->value,
                Permission::VIEW_TURN_LIST_REPORTS->value,
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
                // Ordens de Compra (todas)
                Permission::VIEW_PURCHASE_ORDERS->value,
                Permission::CREATE_PURCHASE_ORDERS->value,
                Permission::EDIT_PURCHASE_ORDERS->value,
                Permission::DELETE_PURCHASE_ORDERS->value,
                Permission::APPROVE_PURCHASE_ORDERS->value,
                Permission::CANCEL_PURCHASE_ORDERS->value,
                Permission::RECEIVE_PURCHASE_ORDERS->value,
                Permission::IMPORT_PURCHASE_ORDERS->value,
                Permission::EXPORT_PURCHASE_ORDERS->value,
                Permission::MANAGE_PURCHASE_ORDERS->value,
                Permission::MANAGE_PURCHASE_ORDER_SIZE_MAPPINGS->value,
                Permission::MANAGE_PURCHASE_ORDER_BRAND_ALIASES->value,
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
                // Abertura de Vagas (todas)
                Permission::VIEW_VACANCIES->value,
                Permission::CREATE_VACANCIES->value,
                Permission::EDIT_VACANCIES->value,
                Permission::DELETE_VACANCIES->value,
                Permission::MANAGE_VACANCIES->value,
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
                // Chat (todas)
                Permission::VIEW_CHAT->value,
                Permission::SEND_CHAT_MESSAGES->value,
                Permission::CREATE_CHAT_GROUPS->value,
                Permission::MANAGE_CHAT_GROUPS->value,
                Permission::SEND_BROADCASTS->value,
                Permission::MANAGE_BROADCASTS->value,
                // Helpdesk (todas)
                Permission::VIEW_HELPDESK->value,
                Permission::CREATE_TICKETS->value,
                Permission::MANAGE_TICKETS->value,
                Permission::MANAGE_HD_DEPARTMENTS->value,
                Permission::VIEW_HD_REPORTS->value,
                Permission::MANAGE_HD_PERMISSIONS->value,
                // TaneIA (todas)
                Permission::VIEW_TANEIA->value,
                Permission::SEND_TANEIA_MESSAGES->value,
                Permission::MANAGE_TANEIA->value,
                // Estornos (todas)
                Permission::VIEW_REVERSALS->value,
                Permission::CREATE_REVERSALS->value,
                Permission::EDIT_REVERSALS->value,
                Permission::DELETE_REVERSALS->value,
                Permission::APPROVE_REVERSALS->value,
                Permission::PROCESS_REVERSALS->value,
                Permission::MANAGE_REVERSALS->value,
                Permission::IMPORT_REVERSALS->value,
                Permission::EXPORT_REVERSALS->value,
                Permission::MANAGE_REVERSAL_REASONS->value,
                // Devoluções / Trocas (todas)
                Permission::VIEW_RETURNS->value,
                Permission::CREATE_RETURNS->value,
                Permission::EDIT_RETURNS->value,
                Permission::APPROVE_RETURNS->value,
                Permission::PROCESS_RETURNS->value,
                Permission::CANCEL_RETURNS->value,
                Permission::DELETE_RETURNS->value,
                Permission::MANAGE_RETURNS->value,
                Permission::IMPORT_RETURNS->value,
                Permission::EXPORT_RETURNS->value,
                Permission::MANAGE_RETURN_REASONS->value,
                // Centros de Custo (todas)
                Permission::VIEW_COST_CENTERS->value,
                Permission::CREATE_COST_CENTERS->value,
                Permission::EDIT_COST_CENTERS->value,
                Permission::DELETE_COST_CENTERS->value,
                Permission::MANAGE_COST_CENTERS->value,
                Permission::IMPORT_COST_CENTERS->value,
                Permission::EXPORT_COST_CENTERS->value,
                // Plano de Contas Contábil (todas)
                Permission::VIEW_ACCOUNTING_CLASSES->value,
                Permission::CREATE_ACCOUNTING_CLASSES->value,
                Permission::EDIT_ACCOUNTING_CLASSES->value,
                Permission::DELETE_ACCOUNTING_CLASSES->value,
                Permission::MANAGE_ACCOUNTING_CLASSES->value,
                Permission::IMPORT_ACCOUNTING_CLASSES->value,
                Permission::EXPORT_ACCOUNTING_CLASSES->value,
                // Plano de Contas Gerencial (todas)
                Permission::VIEW_MANAGEMENT_CLASSES->value,
                Permission::CREATE_MANAGEMENT_CLASSES->value,
                Permission::EDIT_MANAGEMENT_CLASSES->value,
                Permission::DELETE_MANAGEMENT_CLASSES->value,
                Permission::MANAGE_MANAGEMENT_CLASSES->value,
                Permission::IMPORT_MANAGEMENT_CLASSES->value,
                Permission::EXPORT_MANAGEMENT_CLASSES->value,
                // Orçamentos (todas)
                Permission::VIEW_BUDGETS->value,
                Permission::UPLOAD_BUDGETS->value,
                Permission::DOWNLOAD_BUDGETS->value,
                Permission::DELETE_BUDGETS->value,
                Permission::MANAGE_BUDGETS->value,
                Permission::EXPORT_BUDGETS->value,
                Permission::VIEW_BUDGET_CONSUMPTION->value,
                // DRE (todas — admin financeiro)
                Permission::VIEW_DRE->value,
                Permission::MANAGE_DRE_STRUCTURE->value,
                Permission::MANAGE_DRE_MAPPINGS->value,
                Permission::VIEW_DRE_PENDING_ACCOUNTS->value,
                Permission::IMPORT_DRE_ACTUALS->value,
                Permission::IMPORT_DRE_BUDGETS->value,
                Permission::MANAGE_DRE_PERIODS->value,
                Permission::EXPORT_DRE->value,
                // Cupons (todas)
                Permission::VIEW_COUPONS->value,
                Permission::CREATE_COUPONS->value,
                Permission::EDIT_COUPONS->value,
                Permission::DELETE_COUPONS->value,
                Permission::MANAGE_COUPONS->value,
                Permission::ISSUE_COUPON_CODE->value,
                Permission::IMPORT_COUPONS->value,
                Permission::EXPORT_COUPONS->value,
                // Consignações (todas)
                Permission::VIEW_CONSIGNMENTS->value,
                Permission::CREATE_CONSIGNMENTS->value,
                Permission::EDIT_CONSIGNMENTS->value,
                Permission::DELETE_CONSIGNMENTS->value,
                Permission::MANAGE_CONSIGNMENTS->value,
                Permission::REGISTER_CONSIGNMENT_RETURN->value,
                Permission::COMPLETE_CONSIGNMENT->value,
                Permission::CANCEL_CONSIGNMENT->value,
                Permission::EXPORT_CONSIGNMENTS->value,
                Permission::IMPORT_CONSIGNMENTS->value,
                Permission::OVERRIDE_CONSIGNMENT_LOCK->value,
                // Clientes (todas — admin pode disparar sync)
                Permission::VIEW_CUSTOMERS->value,
                Permission::EXPORT_CUSTOMERS->value,
                Permission::SYNC_CUSTOMERS->value,
                // Clientes VIP (todas — admin cobre Marketing)
                Permission::VIEW_VIP_CUSTOMERS->value,
                Permission::MANAGE_VIP_CUSTOMERS->value,
                Permission::CURATE_VIP_CUSTOMERS->value,
                Permission::VIEW_VIP_REPORTS->value,
                Permission::MANAGE_VIP_ACTIVITIES->value,
                Permission::MANAGE_VIP_TIER_CONFIG->value,
                Permission::IMPORT_VIP_CUSTOMERS->value,
                // Verbas de Viagem (todas)
                Permission::VIEW_TRAVEL_EXPENSES->value,
                Permission::CREATE_TRAVEL_EXPENSES->value,
                Permission::EDIT_TRAVEL_EXPENSES->value,
                Permission::DELETE_TRAVEL_EXPENSES->value,
                Permission::APPROVE_TRAVEL_EXPENSES->value,
                Permission::MANAGE_TRAVEL_EXPENSES->value,
                Permission::MANAGE_ACCOUNTABILITY->value,
                Permission::EXPORT_TRAVEL_EXPENSES->value,
                // Lista da Vez (todas)
                Permission::VIEW_TURN_LIST->value,
                Permission::OPERATE_TURN_LIST->value,
                Permission::MANAGE_TURN_LIST->value,
                Permission::MANAGE_TURN_LIST_OUTCOMES->value,
                Permission::MANAGE_TURN_LIST_BREAK_TYPES->value,
                Permission::VIEW_TURN_LIST_REPORTS->value,
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
                // SUPPORT/USER editam OPs. Campos críticos são filtrados no
                // controller pelo OrderPaymentController::CRITICAL_FIELDS
                // (só SUPPORT/ADMIN/SUPER_ADMIN mudam valores/datas/CC/AC).
                Permission::EDIT_ORDER_PAYMENTS->value,
                Permission::VIEW_SUPPLIERS->value,
                // Ordens de Compra (view + create + edit + approve/cancel/receive,
                // SEM delete e SEM manage — fica com store scoping automático)
                Permission::VIEW_PURCHASE_ORDERS->value,
                Permission::CREATE_PURCHASE_ORDERS->value,
                Permission::EDIT_PURCHASE_ORDERS->value,
                Permission::APPROVE_PURCHASE_ORDERS->value,
                Permission::CANCEL_PURCHASE_ORDERS->value,
                Permission::RECEIVE_PURCHASE_ORDERS->value,
                Permission::IMPORT_PURCHASE_ORDERS->value,
                Permission::EXPORT_PURCHASE_ORDERS->value,
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
                // Abertura de Vagas (view + create + edit + manage, sem delete)
                Permission::VIEW_VACANCIES->value,
                Permission::CREATE_VACANCIES->value,
                Permission::EDIT_VACANCIES->value,
                Permission::MANAGE_VACANCIES->value,
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
                // Chat (view + send + criar grupos + enviar broadcasts)
                Permission::VIEW_CHAT->value,
                Permission::SEND_CHAT_MESSAGES->value,
                Permission::CREATE_CHAT_GROUPS->value,
                Permission::SEND_BROADCASTS->value,
                // Helpdesk (view + criar + gerenciar + relatórios + configuração de departamentos)
                // MANAGE_HD_DEPARTMENTS permite configurar expediente, feriados,
                // templates de intake e IA por departamento. Não inclui
                // MANAGE_HD_PERMISSIONS (gerenciar quem é técnico/manager),
                // que continua restrito a Admin/SuperAdmin.
                Permission::VIEW_HELPDESK->value,
                Permission::CREATE_TICKETS->value,
                Permission::MANAGE_TICKETS->value,
                Permission::MANAGE_HD_DEPARTMENTS->value,
                Permission::VIEW_HD_REPORTS->value,
                // TaneIA (view + send)
                Permission::VIEW_TANEIA->value,
                Permission::SEND_TANEIA_MESSAGES->value,
                // Estornos (view + create + edit + approve + process + export, sem delete e
                // sem manage — fica com store scoping automático por ausência de MANAGE_REVERSALS)
                Permission::VIEW_REVERSALS->value,
                Permission::CREATE_REVERSALS->value,
                Permission::EDIT_REVERSALS->value,
                Permission::APPROVE_REVERSALS->value,
                Permission::PROCESS_REVERSALS->value,
                Permission::EXPORT_REVERSALS->value,
                // Devoluções / Trocas (view + create + edit + approve + process + cancel +
                // export, sem delete e sem manage — store scoping automático)
                Permission::VIEW_RETURNS->value,
                Permission::CREATE_RETURNS->value,
                Permission::EDIT_RETURNS->value,
                Permission::APPROVE_RETURNS->value,
                Permission::PROCESS_RETURNS->value,
                Permission::CANCEL_RETURNS->value,
                Permission::EXPORT_RETURNS->value,
                // Centros de Custo (view + export apenas — cadastro é do financeiro/admin)
                Permission::VIEW_COST_CENTERS->value,
                Permission::EXPORT_COST_CENTERS->value,
                // Plano de Contas (view + export apenas — cadastro é do contábil)
                Permission::VIEW_ACCOUNTING_CLASSES->value,
                Permission::EXPORT_ACCOUNTING_CLASSES->value,
                // Plano Gerencial (view + export apenas)
                Permission::VIEW_MANAGEMENT_CLASSES->value,
                Permission::EXPORT_MANAGEMENT_CLASSES->value,
                // Orçamentos (view + consumption dashboard — gestão precisa ver
                // saldo antes de aprovar OP; sem upload/delete/manage)
                Permission::VIEW_BUDGETS->value,
                Permission::DOWNLOAD_BUDGETS->value,
                Permission::EXPORT_BUDGETS->value,
                Permission::VIEW_BUDGET_CONSUMPTION->value,
                // DRE — support só visualiza matriz e fila de pendências
                // (não edita linhas gerenciais nem mappings).
                Permission::VIEW_DRE->value,
                Permission::VIEW_DRE_PENDING_ACCOUNTS->value,
                // Cupons (view + create + edit + issue + export, sem delete e
                // sem manage — fica com store scoping automático por
                // ausência de MANAGE_COUPONS. Inclui ISSUE_COUPON_CODE pra
                // equipe de e-commerce emitir o código na plataforma externa)
                Permission::VIEW_COUPONS->value,
                Permission::CREATE_COUPONS->value,
                Permission::EDIT_COUPONS->value,
                Permission::ISSUE_COUPON_CODE->value,
                Permission::EXPORT_COUPONS->value,
                // Consignações — support cadastra/edita, lança retorno, finaliza
                // e exporta, mas não cancela nem tem override. Escopo por loja
                // automático pela ausência de MANAGE_CONSIGNMENTS.
                Permission::VIEW_CONSIGNMENTS->value,
                Permission::CREATE_CONSIGNMENTS->value,
                Permission::EDIT_CONSIGNMENTS->value,
                Permission::REGISTER_CONSIGNMENT_RETURN->value,
                Permission::COMPLETE_CONSIGNMENT->value,
                Permission::EXPORT_CONSIGNMENTS->value,
                // Clientes — view + export (sync fica com admin+)
                Permission::VIEW_CUSTOMERS->value,
                Permission::EXPORT_CUSTOMERS->value,
                // Verbas de Viagem — solicita, edita as próprias e lança prestação.
                // Aprovação fica com Financeiro. Sem MANAGE faz scoping automático
                // por solicitante/beneficiado.
                Permission::VIEW_TRAVEL_EXPENSES->value,
                Permission::CREATE_TRAVEL_EXPENSES->value,
                Permission::EDIT_TRAVEL_EXPENSES->value,
                Permission::MANAGE_ACCOUNTABILITY->value,
                Permission::EXPORT_TRAVEL_EXPENSES->value,
                // Lista da Vez — view + operate + manage (qualquer loja) + reports.
                // Sem permissions de config (outcomes/break_types) — fica com Admin.
                Permission::VIEW_TURN_LIST->value,
                Permission::OPERATE_TURN_LIST->value,
                Permission::MANAGE_TURN_LIST->value,
                Permission::VIEW_TURN_LIST_REPORTS->value,
            ],
            self::FINANCE => [
                // Financeira — contas a pagar, orçamentos, imports de realizado DRE.
                // Não edita plano de contas (fica em Contabilidade), não fecha período.
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,

                // Ordens de pagamento (CRUD)
                Permission::VIEW_ORDER_PAYMENTS->value,
                Permission::CREATE_ORDER_PAYMENTS->value,
                Permission::EDIT_ORDER_PAYMENTS->value,
                Permission::DELETE_ORDER_PAYMENTS->value,

                // Fornecedores (CRUD)
                Permission::VIEW_SUPPLIERS->value,
                Permission::CREATE_SUPPLIERS->value,
                Permission::EDIT_SUPPLIERS->value,

                // Ordens de compra (view)
                Permission::VIEW_PURCHASE_ORDERS->value,
                Permission::EXPORT_PURCHASE_ORDERS->value,

                // Centros de custo, plano contábil, plano gerencial (view only)
                Permission::VIEW_COST_CENTERS->value,
                Permission::EXPORT_COST_CENTERS->value,
                Permission::VIEW_ACCOUNTING_CLASSES->value,
                Permission::EXPORT_ACCOUNTING_CLASSES->value,
                Permission::VIEW_MANAGEMENT_CLASSES->value,
                Permission::EXPORT_MANAGEMENT_CLASSES->value,

                // Orçamentos (full CRUD + consumo)
                Permission::VIEW_BUDGETS->value,
                Permission::UPLOAD_BUDGETS->value,
                Permission::DOWNLOAD_BUDGETS->value,
                Permission::DELETE_BUDGETS->value,
                Permission::MANAGE_BUDGETS->value,
                Permission::EXPORT_BUDGETS->value,
                Permission::VIEW_BUDGET_CONSUMPTION->value,

                // DRE: matriz + imports + pendências + export (sem estrutura/mappings/fechamento)
                Permission::VIEW_DRE->value,
                Permission::VIEW_DRE_PENDING_ACCOUNTS->value,
                Permission::IMPORT_DRE_ACTUALS->value,
                Permission::IMPORT_DRE_BUDGETS->value,
                Permission::EXPORT_DRE->value,

                // Dados operacionais de leitura pra contexto
                Permission::VIEW_SALES->value,
                Permission::VIEW_MOVEMENTS->value,
                Permission::VIEW_REVERSALS->value,
                Permission::VIEW_RETURNS->value,

                // Verbas de Viagem (full lifecycle de aprovação) — Contas a Pagar
                // recebia as notificações na v1; aqui mantém o controle financeiro.
                Permission::VIEW_TRAVEL_EXPENSES->value,
                Permission::CREATE_TRAVEL_EXPENSES->value,
                Permission::EDIT_TRAVEL_EXPENSES->value,
                Permission::DELETE_TRAVEL_EXPENSES->value,
                Permission::APPROVE_TRAVEL_EXPENSES->value,
                Permission::MANAGE_TRAVEL_EXPENSES->value,
                Permission::MANAGE_ACCOUNTABILITY->value,
                Permission::EXPORT_TRAVEL_EXPENSES->value,
            ],
            self::ACCOUNTING => [
                // Contabilidade — plano de contas, classes gerenciais, centros de
                // custo, mapeamentos e estrutura DRE. Só visualiza lançamentos.
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,

                // Plano de contas (CRUD + import/export)
                Permission::VIEW_ACCOUNTING_CLASSES->value,
                Permission::CREATE_ACCOUNTING_CLASSES->value,
                Permission::EDIT_ACCOUNTING_CLASSES->value,
                Permission::MANAGE_ACCOUNTING_CLASSES->value,
                Permission::IMPORT_ACCOUNTING_CLASSES->value,
                Permission::EXPORT_ACCOUNTING_CLASSES->value,

                // Plano gerencial (CRUD + import/export)
                Permission::VIEW_MANAGEMENT_CLASSES->value,
                Permission::CREATE_MANAGEMENT_CLASSES->value,
                Permission::EDIT_MANAGEMENT_CLASSES->value,
                Permission::MANAGE_MANAGEMENT_CLASSES->value,
                Permission::IMPORT_MANAGEMENT_CLASSES->value,
                Permission::EXPORT_MANAGEMENT_CLASSES->value,

                // Centros de custo (CRUD + import/export)
                Permission::VIEW_COST_CENTERS->value,
                Permission::CREATE_COST_CENTERS->value,
                Permission::EDIT_COST_CENTERS->value,
                Permission::MANAGE_COST_CENTERS->value,
                Permission::IMPORT_COST_CENTERS->value,
                Permission::EXPORT_COST_CENTERS->value,

                // DRE: estrutura + mappings + pendências + view + export (sem imports/fechamento)
                Permission::VIEW_DRE->value,
                Permission::MANAGE_DRE_STRUCTURE->value,
                Permission::MANAGE_DRE_MAPPINGS->value,
                Permission::VIEW_DRE_PENDING_ACCOUNTS->value,
                Permission::EXPORT_DRE->value,

                // Orçamentos (view) + OPs (view) + movimentos (view) — contexto contábil.
                Permission::VIEW_BUDGETS->value,
                Permission::VIEW_ORDER_PAYMENTS->value,
                Permission::VIEW_SUPPLIERS->value,
                Permission::VIEW_SALES->value,
                Permission::VIEW_MOVEMENTS->value,
            ],
            self::FISCAL => [
                // Fiscal — NF, movimentações, estornos, devoluções. Escrita nessas
                // operações; só leitura em orçamento/plano contábil.
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,

                // Movimentações (CRUD)
                Permission::VIEW_MOVEMENTS->value,
                Permission::SYNC_MOVEMENTS->value,

                // Estornos (full lifecycle)
                Permission::VIEW_REVERSALS->value,
                Permission::CREATE_REVERSALS->value,
                Permission::EDIT_REVERSALS->value,
                Permission::APPROVE_REVERSALS->value,
                Permission::PROCESS_REVERSALS->value,
                Permission::MANAGE_REVERSALS->value,
                Permission::IMPORT_REVERSALS->value,
                Permission::EXPORT_REVERSALS->value,
                Permission::MANAGE_REVERSAL_REASONS->value,

                // Devoluções / Trocas (full lifecycle)
                Permission::VIEW_RETURNS->value,
                Permission::CREATE_RETURNS->value,
                Permission::EDIT_RETURNS->value,
                Permission::APPROVE_RETURNS->value,
                Permission::PROCESS_RETURNS->value,
                Permission::CANCEL_RETURNS->value,
                Permission::MANAGE_RETURNS->value,
                Permission::IMPORT_RETURNS->value,
                Permission::EXPORT_RETURNS->value,
                Permission::MANAGE_RETURN_REASONS->value,

                // Vendas, OPs, Fornecedores, plano (view) — contexto fiscal
                Permission::VIEW_SALES->value,
                Permission::VIEW_ORDER_PAYMENTS->value,
                Permission::VIEW_SUPPLIERS->value,
                Permission::VIEW_ACCOUNTING_CLASSES->value,
                Permission::VIEW_COST_CENTERS->value,
                Permission::VIEW_MANAGEMENT_CLASSES->value,

                // DRE: matriz + export (leitura executiva)
                Permission::VIEW_DRE->value,
                Permission::EXPORT_DRE->value,
            ],
            self::MARKETING => [
                // Marketing — curadoria de VIPs e atividades de relacionamento.
                // Precisa ver clientes (para detalhes/contato) e movements (contexto
                // de faturamento), mas não sincroniza, não exporta base completa,
                // não toca em qualquer outro módulo. Cupons ficam fora do escopo
                // (fluxo Consultor/Influencer é operado por vendas/e-commerce).
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,

                // Clientes (view apenas — sem export de base / sem sync)
                Permission::VIEW_CUSTOMERS->value,

                // Clientes VIP (full lifecycle — fluxo principal da role)
                Permission::VIEW_VIP_CUSTOMERS->value,
                Permission::MANAGE_VIP_CUSTOMERS->value,
                Permission::CURATE_VIP_CUSTOMERS->value,
                Permission::VIEW_VIP_REPORTS->value,
                Permission::MANAGE_VIP_ACTIVITIES->value,
                Permission::MANAGE_VIP_TIER_CONFIG->value,

                // Movimentações (view — contexto pra entender o faturamento do VIP)
                Permission::VIEW_MOVEMENTS->value,
            ],
            // Supervisão Comercial — supervisor regional, vê todas as lojas.
            // Sem scoping por user.store_id; permissions específicas (acesso a
            // vendas/metas/movements/etc) serão atribuídas via SaaS admin.
            self::COMMERCIAL_SUPERVISOR => [
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
            ],
            // Gerente — gerente de loja. Restrito à própria loja (user.store_id)
            // pelo padrão de cada módulo (ausência de MANAGE_X faz scoping
            // automático). Permissions específicas atribuídas via SaaS admin.
            self::MANAGER => [
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
            ],
            // Lojas — operador/vendedor da loja. Restrito à própria loja
            // (user.store_id) pelo padrão de cada módulo. Permissions
            // específicas atribuídas via SaaS admin.
            self::STORE => [
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
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
                // Chat (view + send)
                Permission::VIEW_CHAT->value,
                Permission::SEND_CHAT_MESSAGES->value,
                // Helpdesk (view + create)
                Permission::VIEW_HELPDESK->value,
                Permission::CREATE_TICKETS->value,
                // TaneIA (view + send)
                Permission::VIEW_TANEIA->value,
                Permission::SEND_TANEIA_MESSAGES->value,
                // Ordens de pagamento: view + edit (só campos não críticos —
                // filtro por role no OrderPaymentController::CRITICAL_FIELDS
                // impede tampering de valor/datas/CC/AC).
                Permission::VIEW_ORDER_PAYMENTS->value,
                Permission::EDIT_ORDER_PAYMENTS->value,
                // Cupons (view + create apenas — vendedor solicita cupom
                // para a própria loja; edição/emissão com SUPPORT/ADMIN)
                Permission::VIEW_COUPONS->value,
                Permission::CREATE_COUPONS->value,
                // Consignações — vendedor cria/edita consignação para a
                // própria loja e lança retorno. Finalizar e cancelar ficam
                // com SUPPORT/ADMIN. Escopo por loja automático pela ausência
                // de MANAGE_CONSIGNMENTS.
                Permission::VIEW_CONSIGNMENTS->value,
                Permission::CREATE_CONSIGNMENTS->value,
                Permission::EDIT_CONSIGNMENTS->value,
                Permission::REGISTER_CONSIGNMENT_RETURN->value,
                // Clientes — view apenas (vendedor busca cliente ao cadastrar consignação)
                Permission::VIEW_CUSTOMERS->value,
                // Verbas de Viagem — colaborador solicita verba e lança prestação
                // de contas das próprias viagens. Sem MANAGE limita escopo a si.
                Permission::VIEW_TRAVEL_EXPENSES->value,
                Permission::CREATE_TRAVEL_EXPENSES->value,
                Permission::MANAGE_ACCOUNTABILITY->value,
                // Lista da Vez — vendedora opera no PDV apenas na própria loja.
                // Sem MANAGE_TURN_LIST → scoping automático por user.store_id.
                Permission::VIEW_TURN_LIST->value,
                Permission::OPERATE_TURN_LIST->value,
            ],
            self::DRIVER => [
                // Perfil próprio
                Permission::VIEW_OWN_PROFILE->value,
                Permission::EDIT_OWN_PROFILE->value,
                Permission::ACCESS_DASHBOARD->value,
                // Entregas: visualizar + completar entregas no painel do motorista
                Permission::VIEW_DELIVERIES->value,
                Permission::EDIT_DELIVERIES->value,
                Permission::MANAGE_ROUTES->value,
                // Chat (view + send)
                Permission::VIEW_CHAT->value,
                Permission::SEND_CHAT_MESSAGES->value,
                // Helpdesk (view + create)
                Permission::VIEW_HELPDESK->value,
                Permission::CREATE_TICKETS->value,
                // TaneIA (view + send)
                Permission::VIEW_TANEIA->value,
                Permission::SEND_TANEIA_MESSAGES->value,
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
