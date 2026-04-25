<?php

namespace App\Enums;

enum Permission: string
{
    // Gestão de usuários
    case VIEW_USERS = 'users.view';
    case CREATE_USERS = 'users.create';
    case EDIT_USERS = 'users.edit';
    case DELETE_USERS = 'users.delete';
    case MANAGE_USER_ROLES = 'users.manage_roles';

    // Gestão de perfil
    case VIEW_OWN_PROFILE = 'profile.view_own';
    case EDIT_OWN_PROFILE = 'profile.edit_own';
    case VIEW_ANY_PROFILE = 'profile.view_any';
    case EDIT_ANY_PROFILE = 'profile.edit_any';

    // Acesso ao sistema
    case ACCESS_DASHBOARD = 'dashboard.access';
    case ACCESS_ADMIN_PANEL = 'admin.access';
    case ACCESS_SUPPORT_PANEL = 'support.access';

    // Configurações do sistema
    case MANAGE_SETTINGS = 'settings.manage';
    case VIEW_LOGS = 'logs.view';
    case MANAGE_SYSTEM = 'system.manage';

    // Logs de atividade
    case VIEW_ACTIVITY_LOGS = 'activity_logs.view';
    case EXPORT_ACTIVITY_LOGS = 'activity_logs.export';
    case MANAGE_SYSTEM_SETTINGS = 'system_settings.manage';

    // Gestão comercial
    case VIEW_SALES = 'sales.view';
    case CREATE_SALES = 'sales.create';
    case EDIT_SALES = 'sales.edit';
    case DELETE_SALES = 'sales.delete';

    // Gestão de produtos
    case VIEW_PRODUCTS = 'products.view';
    case EDIT_PRODUCTS = 'products.edit';
    case SYNC_PRODUCTS = 'products.sync';

    // Usuários online
    case VIEW_USER_SESSIONS = 'user_sessions.view';
    case MANAGE_USER_SESSIONS = 'user_sessions.manage';

    // Transferências entre lojas
    case VIEW_TRANSFERS = 'transfers.view';
    case CREATE_TRANSFERS = 'transfers.create';
    case EDIT_TRANSFERS = 'transfers.edit';
    case DELETE_TRANSFERS = 'transfers.delete';

    // Ajustes de estoque
    case VIEW_ADJUSTMENTS = 'adjustments.view';
    case CREATE_ADJUSTMENTS = 'adjustments.create';
    case EDIT_ADJUSTMENTS = 'adjustments.edit';
    case DELETE_ADJUSTMENTS = 'adjustments.delete';

    // Ordens de pagamento
    case VIEW_ORDER_PAYMENTS = 'order_payments.view';
    case CREATE_ORDER_PAYMENTS = 'order_payments.create';
    case EDIT_ORDER_PAYMENTS = 'order_payments.edit';
    case DELETE_ORDER_PAYMENTS = 'order_payments.delete';

    // Fornecedores
    case VIEW_SUPPLIERS = 'suppliers.view';
    case CREATE_SUPPLIERS = 'suppliers.create';
    case EDIT_SUPPLIERS = 'suppliers.edit';
    case DELETE_SUPPLIERS = 'suppliers.delete';

    // Ordens de Compra (PurchaseOrders)
    case VIEW_PURCHASE_ORDERS = 'purchase_orders.view';
    case CREATE_PURCHASE_ORDERS = 'purchase_orders.create';
    case EDIT_PURCHASE_ORDERS = 'purchase_orders.edit';
    case DELETE_PURCHASE_ORDERS = 'purchase_orders.delete';
    case APPROVE_PURCHASE_ORDERS = 'purchase_orders.approve';
    case CANCEL_PURCHASE_ORDERS = 'purchase_orders.cancel';
    case RECEIVE_PURCHASE_ORDERS = 'purchase_orders.receive';
    case IMPORT_PURCHASE_ORDERS = 'purchase_orders.import';
    case EXPORT_PURCHASE_ORDERS = 'purchase_orders.export';
    case MANAGE_PURCHASE_ORDERS = 'purchase_orders.manage';
    case MANAGE_PURCHASE_ORDER_SIZE_MAPPINGS = 'purchase_orders.manage_size_mappings';
    case MANAGE_PURCHASE_ORDER_BRAND_ALIASES = 'purchase_orders.manage_brand_aliases';

    // Checklists de qualidade
    case VIEW_CHECKLISTS = 'checklists.view';
    case CREATE_CHECKLISTS = 'checklists.create';
    case EDIT_CHECKLISTS = 'checklists.edit';
    case DELETE_CHECKLISTS = 'checklists.delete';

    // Atestados medicos
    case VIEW_MEDICAL_CERTIFICATES = 'medical_certificates.view';
    case CREATE_MEDICAL_CERTIFICATES = 'medical_certificates.create';
    case EDIT_MEDICAL_CERTIFICATES = 'medical_certificates.edit';
    case DELETE_MEDICAL_CERTIFICATES = 'medical_certificates.delete';

    // Controle de faltas
    case VIEW_ABSENCES = 'absences.view';
    case CREATE_ABSENCES = 'absences.create';
    case EDIT_ABSENCES = 'absences.edit';
    case DELETE_ABSENCES = 'absences.delete';

    // Controle de horas extras
    case VIEW_OVERTIME = 'overtime.view';
    case CREATE_OVERTIME = 'overtime.create';
    case EDIT_OVERTIME = 'overtime.edit';
    case DELETE_OVERTIME = 'overtime.delete';

    // Metas de loja
    case VIEW_STORE_GOALS = 'store_goals.view';
    case CREATE_STORE_GOALS = 'store_goals.create';
    case EDIT_STORE_GOALS = 'store_goals.edit';
    case DELETE_STORE_GOALS = 'store_goals.delete';

    // Movimentações Diárias
    case VIEW_MOVEMENTS = 'movements.view';
    case SYNC_MOVEMENTS = 'movements.sync';

    // Férias
    case VIEW_VACATIONS = 'vacations.view';
    case CREATE_VACATIONS = 'vacations.create';
    case EDIT_VACATIONS = 'vacations.edit';
    case DELETE_VACATIONS = 'vacations.delete';
    case APPROVE_VACATIONS_MANAGER = 'vacations.approve_manager';
    case APPROVE_VACATIONS_RH = 'vacations.approve_rh';
    case MANAGE_HOLIDAYS = 'vacations.manage_holidays';

    // Auditoria de estoque
    case VIEW_STOCK_AUDITS = 'stock_audits.view';
    case CREATE_STOCK_AUDITS = 'stock_audits.create';
    case EDIT_STOCK_AUDITS = 'stock_audits.edit';
    case DELETE_STOCK_AUDITS = 'stock_audits.delete';
    case AUTHORIZE_STOCK_AUDITS = 'stock_audits.authorize';
    case COUNT_STOCK_AUDITS = 'stock_audits.count';
    case RECONCILE_STOCK_AUDITS = 'stock_audits.reconcile';
    case MANAGE_STOCK_AUDIT_CONFIG = 'stock_audits.manage_config';

    // Movimentação de Pessoal
    case VIEW_PERSONNEL_MOVEMENTS = 'personnel_movements.view';
    case CREATE_PERSONNEL_MOVEMENTS = 'personnel_movements.create';
    case EDIT_PERSONNEL_MOVEMENTS = 'personnel_movements.edit';
    case DELETE_PERSONNEL_MOVEMENTS = 'personnel_movements.delete';

    // Abertura de Vagas (VacancyOpening)
    case VIEW_VACANCIES = 'vacancies.view';
    case CREATE_VACANCIES = 'vacancies.create';
    case EDIT_VACANCIES = 'vacancies.edit';
    case DELETE_VACANCIES = 'vacancies.delete';
    case MANAGE_VACANCIES = 'vacancies.manage';

    // Treinamentos
    case VIEW_TRAININGS = 'trainings.view';
    case CREATE_TRAININGS = 'trainings.create';
    case EDIT_TRAININGS = 'trainings.edit';
    case DELETE_TRAININGS = 'trainings.delete';
    case MANAGE_TRAINING_ATTENDANCE = 'trainings.manage_attendance';
    case MANAGE_TRAINING_CONTENT = 'training_content.manage';

    // Cursos de treinamento
    case VIEW_TRAINING_COURSES = 'training_courses.view';
    case CREATE_TRAINING_COURSES = 'training_courses.create';
    case EDIT_TRAINING_COURSES = 'training_courses.edit';
    case DELETE_TRAINING_COURSES = 'training_courses.delete';

    // Quizzes de treinamento
    case MANAGE_TRAINING_QUIZZES = 'training_quizzes.manage';

    // Avaliacao de experiencia
    case VIEW_EXPERIENCE_TRACKER = 'experience_tracker.view';
    case MANAGE_EXPERIENCE_TRACKER = 'experience_tracker.manage';
    case FILL_EXPERIENCE_EVALUATION = 'experience_tracker.fill';

    // Entregas e rotas
    case VIEW_DELIVERIES = 'deliveries.view';
    case CREATE_DELIVERIES = 'deliveries.create';
    case EDIT_DELIVERIES = 'deliveries.edit';
    case DELETE_DELIVERIES = 'deliveries.delete';
    case MANAGE_ROUTES = 'delivery_routes.manage';

    // Chat
    case VIEW_CHAT = 'chat.view';
    case SEND_CHAT_MESSAGES = 'chat.send';
    case CREATE_CHAT_GROUPS = 'chat.create_groups';
    case MANAGE_CHAT_GROUPS = 'chat.manage_groups';
    case SEND_BROADCASTS = 'chat.send_broadcasts';
    case MANAGE_BROADCASTS = 'chat.manage_broadcasts';

    // Helpdesk
    case VIEW_HELPDESK = 'helpdesk.view';
    case CREATE_TICKETS = 'helpdesk.create_tickets';
    case MANAGE_TICKETS = 'helpdesk.manage_tickets';
    case MANAGE_HD_DEPARTMENTS = 'helpdesk.manage_departments';
    case VIEW_HD_REPORTS = 'helpdesk.view_reports';
    case MANAGE_HD_PERMISSIONS = 'helpdesk.manage_permissions';

    // TaneIA (assistente de IA)
    case VIEW_TANEIA = 'taneia.view';
    case SEND_TANEIA_MESSAGES = 'taneia.send';
    case MANAGE_TANEIA = 'taneia.manage';

    // Estornos (Reversals)
    case VIEW_REVERSALS = 'reversals.view';
    case CREATE_REVERSALS = 'reversals.create';
    case EDIT_REVERSALS = 'reversals.edit';
    case DELETE_REVERSALS = 'reversals.delete';
    case APPROVE_REVERSALS = 'reversals.approve';
    case PROCESS_REVERSALS = 'reversals.process';
    case MANAGE_REVERSALS = 'reversals.manage';
    case IMPORT_REVERSALS = 'reversals.import';
    case EXPORT_REVERSALS = 'reversals.export';
    case MANAGE_REVERSAL_REASONS = 'reversals.manage_reasons';

    // Devoluções / Trocas (Returns — e-commerce)
    case VIEW_RETURNS = 'returns.view';
    case CREATE_RETURNS = 'returns.create';
    case EDIT_RETURNS = 'returns.edit';
    case APPROVE_RETURNS = 'returns.approve';
    case PROCESS_RETURNS = 'returns.process';
    case CANCEL_RETURNS = 'returns.cancel';
    case DELETE_RETURNS = 'returns.delete';
    case MANAGE_RETURNS = 'returns.manage';
    case IMPORT_RETURNS = 'returns.import';
    case EXPORT_RETURNS = 'returns.export';
    case MANAGE_RETURN_REASONS = 'returns.manage_reasons';

    // Centros de Custo (Cost Centers — cadastro standalone)
    case VIEW_COST_CENTERS = 'cost_centers.view';
    case CREATE_COST_CENTERS = 'cost_centers.create';
    case EDIT_COST_CENTERS = 'cost_centers.edit';
    case DELETE_COST_CENTERS = 'cost_centers.delete';
    case MANAGE_COST_CENTERS = 'cost_centers.manage';
    case IMPORT_COST_CENTERS = 'cost_centers.import';
    case EXPORT_COST_CENTERS = 'cost_centers.export';

    // Plano de Contas Contábil (Accounting Classes — fundação do DRE)
    case VIEW_ACCOUNTING_CLASSES = 'accounting_classes.view';
    case CREATE_ACCOUNTING_CLASSES = 'accounting_classes.create';
    case EDIT_ACCOUNTING_CLASSES = 'accounting_classes.edit';
    case DELETE_ACCOUNTING_CLASSES = 'accounting_classes.delete';
    case MANAGE_ACCOUNTING_CLASSES = 'accounting_classes.manage';
    case IMPORT_ACCOUNTING_CLASSES = 'accounting_classes.import';
    case EXPORT_ACCOUNTING_CLASSES = 'accounting_classes.export';

    // Plano de Contas Gerencial (Management Classes — visão interna)
    case VIEW_MANAGEMENT_CLASSES = 'management_classes.view';
    case CREATE_MANAGEMENT_CLASSES = 'management_classes.create';
    case EDIT_MANAGEMENT_CLASSES = 'management_classes.edit';
    case DELETE_MANAGEMENT_CLASSES = 'management_classes.delete';
    case MANAGE_MANAGEMENT_CLASSES = 'management_classes.manage';
    case IMPORT_MANAGEMENT_CLASSES = 'management_classes.import';
    case EXPORT_MANAGEMENT_CLASSES = 'management_classes.export';

    // Orçamentos (Budgets)
    case VIEW_BUDGETS = 'budgets.view';
    case UPLOAD_BUDGETS = 'budgets.upload';
    case DOWNLOAD_BUDGETS = 'budgets.download';
    case DELETE_BUDGETS = 'budgets.delete';
    case MANAGE_BUDGETS = 'budgets.manage';
    case EXPORT_BUDGETS = 'budgets.export';
    case VIEW_BUDGET_CONSUMPTION = 'budgets.view_consumption';

    // Cupons de desconto (Consultor / Influencer / MS Indica)
    case VIEW_COUPONS = 'coupons.view';
    case CREATE_COUPONS = 'coupons.create';
    case EDIT_COUPONS = 'coupons.edit';
    case DELETE_COUPONS = 'coupons.delete';
    case MANAGE_COUPONS = 'coupons.manage';
    case ISSUE_COUPON_CODE = 'coupons.issue_code';
    case IMPORT_COUPONS = 'coupons.import';
    case EXPORT_COUPONS = 'coupons.export';

    // DRE — Demonstrativo de Resultado do Exercício
    case VIEW_DRE = 'dre.view';
    case MANAGE_DRE_STRUCTURE = 'dre.manage_structure';
    case MANAGE_DRE_MAPPINGS = 'dre.manage_mappings';
    case VIEW_DRE_PENDING_ACCOUNTS = 'dre.view_pending_accounts';
    case IMPORT_DRE_ACTUALS = 'dre.import_actuals';
    case IMPORT_DRE_BUDGETS = 'dre.import_budgets';
    case MANAGE_DRE_PERIODS = 'dre.manage_periods';
    case EXPORT_DRE = 'dre.export';

    // Clientes (sincronizados do CIGAM — módulo majoritariamente read-only)
    case VIEW_CUSTOMERS = 'customers.view';
    case EXPORT_CUSTOMERS = 'customers.export';
    case SYNC_CUSTOMERS = 'customers.sync';

    // Clientes VIP (Black / Gold — curadoria anual de Marketing)
    // Dados ficam em tabelas separadas (customer_vip_tiers, customer_vip_activities,
    // customer_vip_tier_configs) — nunca tocados pelo CustomerSyncService.
    case VIEW_VIP_CUSTOMERS = 'customer_vips.view';
    case MANAGE_VIP_CUSTOMERS = 'customer_vips.manage';
    case CURATE_VIP_CUSTOMERS = 'customer_vips.curate';
    case VIEW_VIP_REPORTS = 'customer_vips.view_reports';
    case MANAGE_VIP_ACTIVITIES = 'customer_vips.manage_activities';
    case MANAGE_VIP_TIER_CONFIG = 'customer_vips.manage_config';
    case IMPORT_VIP_CUSTOMERS = 'customer_vips.import';

    // Consignações (Cliente / Influencer / E-commerce)
    case VIEW_CONSIGNMENTS = 'consignments.view';
    case CREATE_CONSIGNMENTS = 'consignments.create';
    case EDIT_CONSIGNMENTS = 'consignments.edit';
    case DELETE_CONSIGNMENTS = 'consignments.delete';
    case MANAGE_CONSIGNMENTS = 'consignments.manage';
    case REGISTER_CONSIGNMENT_RETURN = 'consignments.register_return';
    case COMPLETE_CONSIGNMENT = 'consignments.complete';
    case CANCEL_CONSIGNMENT = 'consignments.cancel';
    case EXPORT_CONSIGNMENTS = 'consignments.export';
    case IMPORT_CONSIGNMENTS = 'consignments.import';
    case OVERRIDE_CONSIGNMENT_LOCK = 'consignments.override_lock';

    // Verbas de Viagem (solicitação + prestação de contas)
    case VIEW_TRAVEL_EXPENSES = 'travel_expenses.view';
    case CREATE_TRAVEL_EXPENSES = 'travel_expenses.create';
    case EDIT_TRAVEL_EXPENSES = 'travel_expenses.edit';
    case DELETE_TRAVEL_EXPENSES = 'travel_expenses.delete';
    case APPROVE_TRAVEL_EXPENSES = 'travel_expenses.approve';
    case MANAGE_TRAVEL_EXPENSES = 'travel_expenses.manage';
    case MANAGE_ACCOUNTABILITY = 'travel_expenses.manage_accountability';
    case EXPORT_TRAVEL_EXPENSES = 'travel_expenses.export';

    public function label(): string
    {
        return match ($this) {
            self::VIEW_USERS => 'Visualizar usuários',
            self::CREATE_USERS => 'Criar usuários',
            self::EDIT_USERS => 'Editar usuários',
            self::DELETE_USERS => 'Deletar usuários',
            self::MANAGE_USER_ROLES => 'Gerenciar níveis de usuário',

            self::VIEW_OWN_PROFILE => 'Visualizar próprio perfil',
            self::EDIT_OWN_PROFILE => 'Editar próprio perfil',
            self::VIEW_ANY_PROFILE => 'Visualizar qualquer perfil',
            self::EDIT_ANY_PROFILE => 'Editar qualquer perfil',

            self::ACCESS_DASHBOARD => 'Acessar dashboard',
            self::ACCESS_ADMIN_PANEL => 'Acessar painel administrativo',
            self::ACCESS_SUPPORT_PANEL => 'Acessar painel de suporte',

            self::MANAGE_SETTINGS => 'Gerenciar configurações',
            self::VIEW_LOGS => 'Visualizar logs',
            self::MANAGE_SYSTEM => 'Gerenciar sistema',

            self::VIEW_ACTIVITY_LOGS => 'Visualizar logs de atividade',
            self::EXPORT_ACTIVITY_LOGS => 'Exportar logs de atividade',
            self::MANAGE_SYSTEM_SETTINGS => 'Gerenciar configurações do sistema',

            self::VIEW_SALES => 'Visualizar vendas',
            self::CREATE_SALES => 'Criar vendas',
            self::EDIT_SALES => 'Editar vendas',
            self::DELETE_SALES => 'Deletar vendas',

            self::VIEW_PRODUCTS => 'Visualizar produtos',
            self::EDIT_PRODUCTS => 'Editar produtos',
            self::SYNC_PRODUCTS => 'Sincronizar produtos',

            self::VIEW_USER_SESSIONS => 'Visualizar usuários online',
            self::MANAGE_USER_SESSIONS => 'Gerenciar sessões de usuários',

            self::VIEW_TRANSFERS => 'Visualizar transferências',
            self::CREATE_TRANSFERS => 'Criar transferências',
            self::EDIT_TRANSFERS => 'Editar transferências',
            self::DELETE_TRANSFERS => 'Deletar transferências',

            self::VIEW_ADJUSTMENTS => 'Visualizar ajustes de estoque',
            self::CREATE_ADJUSTMENTS => 'Criar ajustes de estoque',
            self::EDIT_ADJUSTMENTS => 'Editar ajustes de estoque',
            self::DELETE_ADJUSTMENTS => 'Deletar ajustes de estoque',

            self::VIEW_ORDER_PAYMENTS => 'Visualizar ordens de pagamento',
            self::CREATE_ORDER_PAYMENTS => 'Criar ordens de pagamento',
            self::EDIT_ORDER_PAYMENTS => 'Editar ordens de pagamento',
            self::DELETE_ORDER_PAYMENTS => 'Deletar ordens de pagamento',

            self::VIEW_SUPPLIERS => 'Visualizar fornecedores',
            self::CREATE_SUPPLIERS => 'Criar fornecedores',
            self::EDIT_SUPPLIERS => 'Editar fornecedores',
            self::DELETE_SUPPLIERS => 'Deletar fornecedores',

            self::VIEW_PURCHASE_ORDERS => 'Visualizar ordens de compra',
            self::CREATE_PURCHASE_ORDERS => 'Criar ordens de compra',
            self::EDIT_PURCHASE_ORDERS => 'Editar ordens de compra',
            self::DELETE_PURCHASE_ORDERS => 'Excluir ordens de compra',
            self::APPROVE_PURCHASE_ORDERS => 'Faturar ordens de compra',
            self::CANCEL_PURCHASE_ORDERS => 'Cancelar ordens de compra',
            self::RECEIVE_PURCHASE_ORDERS => 'Registrar recebimento de ordens',
            self::IMPORT_PURCHASE_ORDERS => 'Importar ordens de compra (planilha)',
            self::EXPORT_PURCHASE_ORDERS => 'Exportar ordens de compra',
            self::MANAGE_PURCHASE_ORDERS => 'Gerenciar ordens de compra (todas as lojas)',
            self::MANAGE_PURCHASE_ORDER_SIZE_MAPPINGS => 'Gerenciar mapeamento de tamanhos de importação',
            self::MANAGE_PURCHASE_ORDER_BRAND_ALIASES => 'Gerenciar aliases de marcas de importação',

            self::VIEW_CHECKLISTS => 'Visualizar checklists',
            self::CREATE_CHECKLISTS => 'Criar checklists',
            self::EDIT_CHECKLISTS => 'Editar checklists',
            self::DELETE_CHECKLISTS => 'Deletar checklists',

            self::VIEW_MEDICAL_CERTIFICATES => 'Visualizar atestados médicos',
            self::CREATE_MEDICAL_CERTIFICATES => 'Criar atestados médicos',
            self::EDIT_MEDICAL_CERTIFICATES => 'Editar atestados médicos',
            self::DELETE_MEDICAL_CERTIFICATES => 'Deletar atestados médicos',

            self::VIEW_ABSENCES => 'Visualizar faltas',
            self::CREATE_ABSENCES => 'Registrar faltas',
            self::EDIT_ABSENCES => 'Editar faltas',
            self::DELETE_ABSENCES => 'Deletar faltas',

            self::VIEW_OVERTIME => 'Visualizar horas extras',
            self::CREATE_OVERTIME => 'Registrar horas extras',
            self::EDIT_OVERTIME => 'Editar horas extras',
            self::DELETE_OVERTIME => 'Deletar horas extras',

            self::VIEW_STORE_GOALS => 'Visualizar metas de loja',
            self::CREATE_STORE_GOALS => 'Criar metas de loja',
            self::EDIT_STORE_GOALS => 'Editar metas de loja',
            self::DELETE_STORE_GOALS => 'Deletar metas de loja',

            self::VIEW_MOVEMENTS => 'Visualizar movimentações',
            self::SYNC_MOVEMENTS => 'Sincronizar movimentações',

            self::VIEW_VACATIONS => 'Visualizar férias',
            self::CREATE_VACATIONS => 'Criar solicitação de férias',
            self::EDIT_VACATIONS => 'Editar férias',
            self::DELETE_VACATIONS => 'Excluir férias',
            self::APPROVE_VACATIONS_MANAGER => 'Aprovar férias (Gestor)',
            self::APPROVE_VACATIONS_RH => 'Aprovar férias (RH)',
            self::MANAGE_HOLIDAYS => 'Gerenciar feriados',

            self::VIEW_STOCK_AUDITS => 'Visualizar auditorias de estoque',
            self::CREATE_STOCK_AUDITS => 'Criar auditorias de estoque',
            self::EDIT_STOCK_AUDITS => 'Editar auditorias de estoque',
            self::DELETE_STOCK_AUDITS => 'Excluir auditorias de estoque',
            self::AUTHORIZE_STOCK_AUDITS => 'Autorizar auditorias de estoque',
            self::COUNT_STOCK_AUDITS => 'Realizar contagem de estoque',
            self::RECONCILE_STOCK_AUDITS => 'Conciliar auditorias de estoque',
            self::MANAGE_STOCK_AUDIT_CONFIG => 'Gerenciar config. de auditoria',

            self::VIEW_PERSONNEL_MOVEMENTS => 'Visualizar movimentações de pessoal',
            self::CREATE_PERSONNEL_MOVEMENTS => 'Criar movimentações de pessoal',
            self::EDIT_PERSONNEL_MOVEMENTS => 'Editar movimentações de pessoal',
            self::DELETE_PERSONNEL_MOVEMENTS => 'Deletar movimentações de pessoal',
            self::VIEW_VACANCIES => 'Visualizar vagas',
            self::CREATE_VACANCIES => 'Criar vagas',
            self::EDIT_VACANCIES => 'Editar vagas',
            self::DELETE_VACANCIES => 'Excluir vagas',
            self::MANAGE_VACANCIES => 'Gerenciar vagas (transições, recrutador)',

            self::VIEW_TRAININGS => 'Visualizar treinamentos',
            self::CREATE_TRAININGS => 'Criar treinamentos',
            self::EDIT_TRAININGS => 'Editar treinamentos',
            self::DELETE_TRAININGS => 'Excluir treinamentos',
            self::MANAGE_TRAINING_ATTENDANCE => 'Gerenciar presença em treinamentos',
            self::MANAGE_TRAINING_CONTENT => 'Gerenciar conteúdos de treinamento',
            self::VIEW_TRAINING_COURSES => 'Visualizar cursos de treinamento',
            self::CREATE_TRAINING_COURSES => 'Criar cursos de treinamento',
            self::EDIT_TRAINING_COURSES => 'Editar cursos de treinamento',
            self::DELETE_TRAINING_COURSES => 'Excluir cursos de treinamento',
            self::MANAGE_TRAINING_QUIZZES => 'Gerenciar quizzes de treinamento',
            self::VIEW_EXPERIENCE_TRACKER => 'Visualizar avaliações de experiência',
            self::MANAGE_EXPERIENCE_TRACKER => 'Gerenciar avaliações de experiência',
            self::FILL_EXPERIENCE_EVALUATION => 'Preencher avaliações de experiência',
            self::VIEW_DELIVERIES => 'Visualizar entregas',
            self::CREATE_DELIVERIES => 'Criar entregas',
            self::EDIT_DELIVERIES => 'Editar entregas',
            self::DELETE_DELIVERIES => 'Excluir entregas',
            self::MANAGE_ROUTES => 'Gerenciar rotas de entrega',
            // Chat
            self::VIEW_CHAT => 'Visualizar chat',
            self::SEND_CHAT_MESSAGES => 'Enviar mensagens',
            self::CREATE_CHAT_GROUPS => 'Criar grupos de chat',
            self::MANAGE_CHAT_GROUPS => 'Gerenciar grupos de chat',
            self::SEND_BROADCASTS => 'Enviar comunicados',
            self::MANAGE_BROADCASTS => 'Gerenciar comunicados',
            // Helpdesk
            self::VIEW_HELPDESK => 'Visualizar chamados',
            self::CREATE_TICKETS => 'Criar chamados',
            self::MANAGE_TICKETS => 'Gerenciar chamados',
            self::MANAGE_HD_DEPARTMENTS => 'Gerenciar departamentos',
            self::VIEW_HD_REPORTS => 'Visualizar relatórios',
            self::MANAGE_HD_PERMISSIONS => 'Gerenciar permissões do helpdesk',
            // TaneIA
            self::VIEW_TANEIA => 'Visualizar TaneIA',
            self::SEND_TANEIA_MESSAGES => 'Conversar com a TaneIA',
            self::MANAGE_TANEIA => 'Gerenciar TaneIA',
            // Estornos
            self::VIEW_REVERSALS => 'Visualizar estornos',
            self::CREATE_REVERSALS => 'Criar solicitações de estorno',
            self::EDIT_REVERSALS => 'Editar solicitações de estorno',
            self::DELETE_REVERSALS => 'Excluir solicitações de estorno',
            self::APPROVE_REVERSALS => 'Autorizar estornos',
            self::PROCESS_REVERSALS => 'Processar estornos (financeiro)',
            self::MANAGE_REVERSALS => 'Gerenciar estornos (todas as lojas)',
            self::IMPORT_REVERSALS => 'Importar estornos (planilha)',
            self::EXPORT_REVERSALS => 'Exportar estornos',
            self::MANAGE_REVERSAL_REASONS => 'Gerenciar motivos de estorno',
            // Devoluções / Trocas
            self::VIEW_RETURNS => 'Visualizar devoluções',
            self::CREATE_RETURNS => 'Criar solicitações de devolução',
            self::EDIT_RETURNS => 'Editar devoluções',
            self::APPROVE_RETURNS => 'Aprovar devoluções',
            self::PROCESS_RETURNS => 'Processar devoluções (marcar como concluída)',
            self::CANCEL_RETURNS => 'Cancelar devoluções',
            self::DELETE_RETURNS => 'Excluir devoluções',
            self::MANAGE_RETURNS => 'Gerenciar devoluções (todas as lojas)',
            self::IMPORT_RETURNS => 'Importar devoluções (planilha)',
            self::EXPORT_RETURNS => 'Exportar devoluções',
            self::MANAGE_RETURN_REASONS => 'Gerenciar motivos de devolução',
            // Centros de Custo
            self::VIEW_COST_CENTERS => 'Visualizar centros de custo',
            self::CREATE_COST_CENTERS => 'Criar centros de custo',
            self::EDIT_COST_CENTERS => 'Editar centros de custo',
            self::DELETE_COST_CENTERS => 'Excluir centros de custo',
            self::MANAGE_COST_CENTERS => 'Gerenciar centros de custo (todas as áreas)',
            self::IMPORT_COST_CENTERS => 'Importar centros de custo (planilha)',
            self::EXPORT_COST_CENTERS => 'Exportar centros de custo',
            // Plano de Contas Contábil
            self::VIEW_ACCOUNTING_CLASSES => 'Visualizar plano de contas contábil',
            self::CREATE_ACCOUNTING_CLASSES => 'Criar contas contábeis',
            self::EDIT_ACCOUNTING_CLASSES => 'Editar contas contábeis',
            self::DELETE_ACCOUNTING_CLASSES => 'Excluir contas contábeis',
            self::MANAGE_ACCOUNTING_CLASSES => 'Gerenciar plano de contas (estrutura completa)',
            self::IMPORT_ACCOUNTING_CLASSES => 'Importar plano de contas (planilha)',
            self::EXPORT_ACCOUNTING_CLASSES => 'Exportar plano de contas',
            // Plano de Contas Gerencial
            self::VIEW_MANAGEMENT_CLASSES => 'Visualizar plano de contas gerencial',
            self::CREATE_MANAGEMENT_CLASSES => 'Criar contas gerenciais',
            self::EDIT_MANAGEMENT_CLASSES => 'Editar contas gerenciais',
            self::DELETE_MANAGEMENT_CLASSES => 'Excluir contas gerenciais',
            self::MANAGE_MANAGEMENT_CLASSES => 'Gerenciar plano de contas gerencial (estrutura completa)',
            self::IMPORT_MANAGEMENT_CLASSES => 'Importar plano de contas gerencial (planilha)',
            self::EXPORT_MANAGEMENT_CLASSES => 'Exportar plano de contas gerencial',
            // Orçamentos
            self::VIEW_BUDGETS => 'Visualizar orçamentos',
            self::UPLOAD_BUDGETS => 'Enviar planilhas de orçamento',
            self::DOWNLOAD_BUDGETS => 'Baixar planilha original do orçamento',
            self::DELETE_BUDGETS => 'Excluir versões de orçamento (não-ativas)',
            self::MANAGE_BUDGETS => 'Gerenciar orçamentos de todos os escopos',
            self::EXPORT_BUDGETS => 'Exportar orçamento consolidado (com consumo realizado)',
            self::VIEW_BUDGET_CONSUMPTION => 'Visualizar dashboard de consumo previsto × realizado',

            // Cupons
            self::VIEW_COUPONS => 'Visualizar cupons',
            self::CREATE_COUPONS => 'Criar solicitações de cupom',
            self::EDIT_COUPONS => 'Editar cupons',
            self::DELETE_COUPONS => 'Excluir cupons',
            self::MANAGE_COUPONS => 'Gerenciar cupons (todas as lojas)',
            self::ISSUE_COUPON_CODE => 'Emitir código do cupom (e-commerce)',
            self::IMPORT_COUPONS => 'Importar cupons (planilha)',
            self::EXPORT_COUPONS => 'Exportar cupons',

            self::VIEW_DRE => 'Visualizar DRE gerencial',
            self::MANAGE_DRE_STRUCTURE => 'Gerenciar estrutura da DRE (linhas gerenciais)',
            self::MANAGE_DRE_MAPPINGS => 'Gerenciar de-para conta contábil → linha gerencial',
            self::VIEW_DRE_PENDING_ACCOUNTS => 'Visualizar fila de contas pendentes de mapeamento',
            self::IMPORT_DRE_ACTUALS => 'Importar realizado manual da DRE (XLSX)',
            self::IMPORT_DRE_BUDGETS => 'Importar orçado manual da DRE (XLSX)',
            self::MANAGE_DRE_PERIODS => 'Fechar e reabrir períodos da DRE',
            self::EXPORT_DRE => 'Exportar matriz DRE em XLSX/PDF',

            self::VIEW_CUSTOMERS => 'Visualizar clientes',
            self::EXPORT_CUSTOMERS => 'Exportar clientes',
            self::SYNC_CUSTOMERS => 'Disparar sincronização manual com CIGAM',

            self::VIEW_VIP_CUSTOMERS => 'Visualizar clientes VIP',
            self::MANAGE_VIP_CUSTOMERS => 'Gerar sugestões de VIPs (rodar classificação)',
            self::CURATE_VIP_CUSTOMERS => 'Curar lista VIP (promover/rebaixar/remover)',
            self::VIEW_VIP_REPORTS => 'Visualizar relatórios VIP (YoY de faturamento)',
            self::MANAGE_VIP_ACTIVITIES => 'Registrar atividades de marketing (brindes, eventos, contatos)',
            self::MANAGE_VIP_TIER_CONFIG => 'Configurar thresholds de tier VIP por ano',
            self::IMPORT_VIP_CUSTOMERS => 'Importar lista de clientes VIP via XLSX',

            self::VIEW_CONSIGNMENTS => 'Visualizar consignações',
            self::CREATE_CONSIGNMENTS => 'Criar consignações',
            self::EDIT_CONSIGNMENTS => 'Editar consignações',
            self::DELETE_CONSIGNMENTS => 'Excluir consignações',
            self::MANAGE_CONSIGNMENTS => 'Gerenciar consignações (todas as lojas)',
            self::REGISTER_CONSIGNMENT_RETURN => 'Lançar nota de retorno de consignação',
            self::COMPLETE_CONSIGNMENT => 'Finalizar consignação',
            self::CANCEL_CONSIGNMENT => 'Cancelar consignação',
            self::EXPORT_CONSIGNMENTS => 'Exportar consignações (XLSX/PDF)',
            self::IMPORT_CONSIGNMENTS => 'Importar consignações (planilha)',
            self::OVERRIDE_CONSIGNMENT_LOCK => 'Ignorar bloqueio de inadimplência e editar finalizadas',

            self::VIEW_TRAVEL_EXPENSES => 'Visualizar verbas de viagem',
            self::CREATE_TRAVEL_EXPENSES => 'Solicitar verba de viagem',
            self::EDIT_TRAVEL_EXPENSES => 'Editar solicitação de verba',
            self::DELETE_TRAVEL_EXPENSES => 'Excluir solicitação de verba',
            self::APPROVE_TRAVEL_EXPENSES => 'Aprovar/rejeitar verbas (financeiro)',
            self::MANAGE_TRAVEL_EXPENSES => 'Gerenciar verbas (todas as lojas)',
            self::MANAGE_ACCOUNTABILITY => 'Lançar prestação de contas',
            self::EXPORT_TRAVEL_EXPENSES => 'Exportar verbas (XLSX/PDF)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::VIEW_USERS => 'Permite visualizar lista de usuários e detalhes',
            self::CREATE_USERS => 'Permite criar novos usuários no sistema',
            self::EDIT_USERS => 'Permite editar informações de usuários',
            self::DELETE_USERS => 'Permite deletar usuários do sistema',
            self::MANAGE_USER_ROLES => 'Permite alterar níveis de acesso de usuários',

            self::VIEW_OWN_PROFILE => 'Permite visualizar próprias informações de perfil',
            self::EDIT_OWN_PROFILE => 'Permite editar próprias informações de perfil',
            self::VIEW_ANY_PROFILE => 'Permite visualizar perfil de qualquer usuário',
            self::EDIT_ANY_PROFILE => 'Permite editar perfil de qualquer usuário',

            self::ACCESS_DASHBOARD => 'Permite acessar o dashboard principal',
            self::ACCESS_ADMIN_PANEL => 'Permite acessar funcionalidades administrativas',
            self::ACCESS_SUPPORT_PANEL => 'Permite acessar funcionalidades de suporte',

            self::MANAGE_SETTINGS => 'Permite gerenciar configurações do sistema',
            self::VIEW_LOGS => 'Permite visualizar logs do sistema',
            self::MANAGE_SYSTEM => 'Permite gerenciar configurações avançadas do sistema',

            self::VIEW_ACTIVITY_LOGS => 'Permite visualizar histórico de atividades dos usuários',
            self::EXPORT_ACTIVITY_LOGS => 'Permite exportar logs de atividade em diversos formatos',
            self::MANAGE_SYSTEM_SETTINGS => 'Permite gerenciar configurações críticas do sistema',

            self::VIEW_SALES => 'Permite visualizar registros de vendas',
            self::CREATE_SALES => 'Permite criar novos registros de vendas',
            self::EDIT_SALES => 'Permite editar registros de vendas existentes',
            self::DELETE_SALES => 'Permite deletar registros de vendas',

            self::VIEW_PRODUCTS => 'Permite visualizar catálogo de produtos',
            self::EDIT_PRODUCTS => 'Permite editar informações de produtos',
            self::SYNC_PRODUCTS => 'Permite sincronizar produtos com o CIGAM',

            self::VIEW_USER_SESSIONS => 'Permite visualizar usuários online no sistema',
            self::MANAGE_USER_SESSIONS => 'Permite gerenciar sessões e forçar logout de usuários',

            self::VIEW_TRANSFERS => 'Permite visualizar transferências entre lojas',
            self::CREATE_TRANSFERS => 'Permite criar novas transferências entre lojas',
            self::EDIT_TRANSFERS => 'Permite editar transferências existentes',
            self::DELETE_TRANSFERS => 'Permite deletar transferências',

            self::VIEW_ADJUSTMENTS => 'Permite visualizar ajustes de estoque',
            self::CREATE_ADJUSTMENTS => 'Permite criar novos ajustes de estoque',
            self::EDIT_ADJUSTMENTS => 'Permite editar ajustes de estoque existentes',
            self::DELETE_ADJUSTMENTS => 'Permite deletar ajustes de estoque',

            self::VIEW_ORDER_PAYMENTS => 'Permite visualizar ordens de pagamento',
            self::CREATE_ORDER_PAYMENTS => 'Permite criar novas ordens de pagamento',
            self::EDIT_ORDER_PAYMENTS => 'Permite editar ordens de pagamento existentes',
            self::DELETE_ORDER_PAYMENTS => 'Permite deletar ordens de pagamento',

            self::VIEW_SUPPLIERS => 'Permite visualizar cadastro de fornecedores',
            self::CREATE_SUPPLIERS => 'Permite cadastrar novos fornecedores',
            self::EDIT_SUPPLIERS => 'Permite editar dados de fornecedores',
            self::DELETE_SUPPLIERS => 'Permite excluir fornecedores',

            self::VIEW_PURCHASE_ORDERS => 'Permite visualizar ordens de compra. Sem MANAGE_PURCHASE_ORDERS, o usuário só vê ordens da sua loja',
            self::CREATE_PURCHASE_ORDERS => 'Permite criar novas ordens de compra. Sem MANAGE_PURCHASE_ORDERS, só pode criar para a própria loja',
            self::EDIT_PURCHASE_ORDERS => 'Permite editar dados da ordem enquanto ela estiver em Pendente',
            self::DELETE_PURCHASE_ORDERS => 'Permite excluir ordens de compra. Bloqueia se houver ordens de pagamento vinculadas',
            self::APPROVE_PURCHASE_ORDERS => 'Permite transicionar Pendente → Faturado / Faturado Parcial (confirmar NF do fornecedor)',
            self::CANCEL_PURCHASE_ORDERS => 'Permite cancelar ordens em qualquer estado não terminal e reabrir ordens canceladas',
            self::RECEIVE_PURCHASE_ORDERS => 'Permite registrar recebimento de mercadoria (gera movimento de estoque automaticamente)',
            self::IMPORT_PURCHASE_ORDERS => 'Permite importar ordens via planilha (XLSX/CSV) com upsert por número da ordem',
            self::EXPORT_PURCHASE_ORDERS => 'Permite exportar ordens para Excel ou PDF',
            self::MANAGE_PURCHASE_ORDERS => 'Permite gerenciar ordens de todas as lojas (sem filtro de store scoping)',
            self::MANAGE_PURCHASE_ORDER_SIZE_MAPPINGS => 'Permite cadastrar e editar o de-para de tamanhos usados na importação de planilhas de ordens de compra',
            self::MANAGE_PURCHASE_ORDER_BRAND_ALIASES => 'Permite cadastrar aliases de nomes de marca usados na importação (ex: mapear "FACCINE" → "MS FACCINE") e criar marcas manualmente quando não existem no catálogo CIGAM',

            self::VIEW_CHECKLISTS => 'Permite visualizar checklists de qualidade',
            self::CREATE_CHECKLISTS => 'Permite criar novos checklists de qualidade',
            self::EDIT_CHECKLISTS => 'Permite editar e responder checklists',
            self::DELETE_CHECKLISTS => 'Permite deletar checklists pendentes',

            self::VIEW_MEDICAL_CERTIFICATES => 'Permite visualizar atestados médicos',
            self::CREATE_MEDICAL_CERTIFICATES => 'Permite cadastrar novos atestados médicos',
            self::EDIT_MEDICAL_CERTIFICATES => 'Permite editar atestados médicos',
            self::DELETE_MEDICAL_CERTIFICATES => 'Permite excluir atestados médicos',

            self::VIEW_ABSENCES => 'Permite visualizar registros de faltas',
            self::CREATE_ABSENCES => 'Permite registrar novas faltas',
            self::EDIT_ABSENCES => 'Permite editar registros de faltas',
            self::DELETE_ABSENCES => 'Permite excluir registros de faltas',

            self::VIEW_OVERTIME => 'Permite visualizar registros de horas extras',
            self::CREATE_OVERTIME => 'Permite registrar novas horas extras',
            self::EDIT_OVERTIME => 'Permite editar registros de horas extras',
            self::DELETE_OVERTIME => 'Permite excluir registros de horas extras',

            self::VIEW_STORE_GOALS => 'Permite visualizar metas de loja',
            self::CREATE_STORE_GOALS => 'Permite criar metas de loja',
            self::EDIT_STORE_GOALS => 'Permite editar metas de loja',
            self::DELETE_STORE_GOALS => 'Permite excluir metas de loja',

            self::VIEW_MOVEMENTS => 'Permite visualizar movimentações diárias',
            self::SYNC_MOVEMENTS => 'Permite sincronizar movimentações do CIGAM',

            self::VIEW_VACATIONS => 'Permite visualizar solicitações de férias',
            self::CREATE_VACATIONS => 'Permite criar solicitações de férias',
            self::EDIT_VACATIONS => 'Permite editar solicitações de férias',
            self::DELETE_VACATIONS => 'Permite excluir solicitações de férias',
            self::APPROVE_VACATIONS_MANAGER => 'Permite aprovar/rejeitar férias como gestor',
            self::APPROVE_VACATIONS_RH => 'Permite aprovar/rejeitar férias como RH e iniciar/finalizar gozo',
            self::MANAGE_HOLIDAYS => 'Permite cadastrar e gerenciar feriados',

            self::VIEW_STOCK_AUDITS => 'Permite visualizar auditorias de estoque',
            self::CREATE_STOCK_AUDITS => 'Permite criar novas auditorias de estoque',
            self::EDIT_STOCK_AUDITS => 'Permite editar auditorias de estoque',
            self::DELETE_STOCK_AUDITS => 'Permite excluir auditorias de estoque',
            self::AUTHORIZE_STOCK_AUDITS => 'Permite autorizar o inicio de auditorias de estoque',
            self::COUNT_STOCK_AUDITS => 'Permite realizar contagem física de estoque',
            self::RECONCILE_STOCK_AUDITS => 'Permite conciliar divergências em auditorias de estoque',
            self::MANAGE_STOCK_AUDIT_CONFIG => 'Permite gerenciar ciclos e empresas auditoras',

            self::VIEW_PERSONNEL_MOVEMENTS => 'Permite visualizar movimentações de pessoal',
            self::CREATE_PERSONNEL_MOVEMENTS => 'Permite criar movimentações de pessoal (desligamento, promoção, transferência, reativação)',
            self::EDIT_PERSONNEL_MOVEMENTS => 'Permite editar e transicionar movimentações de pessoal',
            self::DELETE_PERSONNEL_MOVEMENTS => 'Permite excluir movimentações de pessoal',
            self::VIEW_VACANCIES => 'Permite visualizar vagas abertas e seu histórico',
            self::CREATE_VACANCIES => 'Permite abrir vagas. Gestores de loja só podem abrir vagas para sua própria loja',
            self::EDIT_VACANCIES => 'Permite editar dados de vagas (recrutador, SLA, entrevistas, observações)',
            self::DELETE_VACANCIES => 'Permite excluir vagas. Exige motivo de exclusão',
            self::MANAGE_VACANCIES => 'Permite transicionar status de vagas, atribuir recrutador e finalizar com pré-cadastro de funcionário',

            self::VIEW_TRAININGS => 'Permite visualizar treinamentos e eventos de capacitação',
            self::CREATE_TRAININGS => 'Permite criar novos treinamentos e eventos',
            self::EDIT_TRAININGS => 'Permite editar treinamentos, transicionar status e gerar certificados',
            self::DELETE_TRAININGS => 'Permite excluir treinamentos',
            self::MANAGE_TRAINING_ATTENDANCE => 'Permite gerenciar presença e QR codes de treinamentos',
            self::MANAGE_TRAINING_CONTENT => 'Permite criar, editar e excluir conteúdos de treinamento (videos, documentos, etc.)',
            self::VIEW_TRAINING_COURSES => 'Permite visualizar cursos e trilhas de aprendizagem',
            self::CREATE_TRAINING_COURSES => 'Permite criar cursos e trilhas de aprendizagem',
            self::EDIT_TRAINING_COURSES => 'Permite editar cursos, gerenciar conteúdos e visibilidade',
            self::DELETE_TRAINING_COURSES => 'Permite excluir cursos de treinamento',
            self::MANAGE_TRAINING_QUIZZES => 'Permite criar, editar e excluir quizzes de treinamento',
            self::VIEW_EXPERIENCE_TRACKER => 'Permite visualizar avaliações de período de experiência',
            self::MANAGE_EXPERIENCE_TRACKER => 'Permite criar e gerenciar avaliações de período de experiência',
            self::FILL_EXPERIENCE_EVALUATION => 'Permite preencher formulários de avaliação de período de experiência',
            self::VIEW_DELIVERIES => 'Permite visualizar entregas e rotas',
            self::CREATE_DELIVERIES => 'Permite criar novas entregas',
            self::EDIT_DELIVERIES => 'Permite editar entregas e transicionar status',
            self::DELETE_DELIVERIES => 'Permite excluir entregas',
            self::MANAGE_ROUTES => 'Permite criar, iniciar e gerenciar rotas de entrega',
            // Chat
            self::VIEW_CHAT => 'Permite acessar o módulo de chat',
            self::SEND_CHAT_MESSAGES => 'Permite enviar mensagens diretas e em grupos',
            self::CREATE_CHAT_GROUPS => 'Permite criar grupos de chat',
            self::MANAGE_CHAT_GROUPS => 'Permite editar e gerenciar grupos de chat',
            self::SEND_BROADCASTS => 'Permite enviar comunicados para a equipe',
            self::MANAGE_BROADCASTS => 'Permite editar e excluir comunicados',
            // Helpdesk
            self::VIEW_HELPDESK => 'Permite visualizar chamados do helpdesk',
            self::CREATE_TICKETS => 'Permite criar novos chamados',
            self::MANAGE_TICKETS => 'Permite gerenciar, atribuir e transicionar chamados',
            self::MANAGE_HD_DEPARTMENTS => 'Permite configurar departamentos e categorias',
            self::VIEW_HD_REPORTS => 'Permite visualizar relatórios do helpdesk',
            self::MANAGE_HD_PERMISSIONS => 'Permite gerenciar permissões de técnicos por departamento',
            // TaneIA
            self::VIEW_TANEIA => 'Permite acessar o módulo da assistente virtual TaneIA',
            self::SEND_TANEIA_MESSAGES => 'Permite criar conversas e enviar mensagens para a TaneIA',
            self::MANAGE_TANEIA => 'Permite gerenciar configurações e histórico global da TaneIA',
            // Estornos
            self::VIEW_REVERSALS => 'Permite visualizar estornos. Sem MANAGE_REVERSALS, o usuário só vê estornos da sua loja',
            self::CREATE_REVERSALS => 'Permite abrir solicitações de estorno a partir de NF/cupom fiscal',
            self::EDIT_REVERSALS => 'Permite editar dados do estorno enquanto estiver em Aguardando Estorno ou Aguardando Autorização',
            self::DELETE_REVERSALS => 'Permite excluir estornos (soft delete). Exige motivo',
            self::APPROVE_REVERSALS => 'Permite autorizar estornos (Aguardando Autorização → Autorizado) e cancelar em qualquer estado não terminal',
            self::PROCESS_REVERSALS => 'Permite registrar execução financeira do estorno (Aguardando Financeira → Estornado)',
            self::MANAGE_REVERSALS => 'Permite gerenciar estornos de todas as lojas (sem filtro de store scoping)',
            self::IMPORT_REVERSALS => 'Permite importar estornos via planilha (XLSX/CSV) — usado na migração de dados históricos',
            self::EXPORT_REVERSALS => 'Permite exportar estornos para Excel ou comprovante PDF',
            self::MANAGE_REVERSAL_REASONS => 'Permite cadastrar e editar motivos de estorno no catálogo',
            // Devoluções / Trocas
            self::VIEW_RETURNS => 'Permite visualizar devoluções. Sem MANAGE_RETURNS, o usuário só vê devoluções da sua loja',
            self::CREATE_RETURNS => 'Permite abrir solicitações de devolução a partir de NF/cupom fiscal',
            self::EDIT_RETURNS => 'Permite editar dados da devolução enquanto estiver em Pendente ou Aprovado',
            self::APPROVE_RETURNS => 'Permite aprovar devoluções (Pendente → Aprovado) e cancelar em qualquer estado não terminal',
            self::PROCESS_RETURNS => 'Permite movimentar devoluções para Processando e Completo após recebimento do produto',
            self::CANCEL_RETURNS => 'Permite cancelar devoluções (alias granular de APPROVE_RETURNS)',
            self::DELETE_RETURNS => 'Permite excluir devoluções (soft delete). Exige motivo',
            self::MANAGE_RETURNS => 'Permite gerenciar devoluções de todas as lojas (sem filtro de store scoping)',
            self::IMPORT_RETURNS => 'Permite importar devoluções via planilha — usado na migração de dados históricos v1',
            self::EXPORT_RETURNS => 'Permite exportar devoluções para Excel ou comprovante PDF',
            self::MANAGE_RETURN_REASONS => 'Permite cadastrar e editar motivos de devolução no catálogo',
            // Centros de Custo
            self::VIEW_COST_CENTERS => 'Permite visualizar centros de custo cadastrados (fundação do DRE e dimensão de orçamentos)',
            self::CREATE_COST_CENTERS => 'Permite cadastrar novos centros de custo',
            self::EDIT_COST_CENTERS => 'Permite editar dados de centros de custo existentes',
            self::DELETE_COST_CENTERS => 'Permite excluir centros de custo (soft delete). Exige motivo',
            self::MANAGE_COST_CENTERS => 'Permite gerenciar centros de custo de todas as áreas (sem filtro por área)',
            self::IMPORT_COST_CENTERS => 'Permite importar centros de custo via planilha (XLSX/CSV)',
            self::EXPORT_COST_CENTERS => 'Permite exportar centros de custo para Excel',
            // Plano de Contas Contábil
            self::VIEW_ACCOUNTING_CLASSES => 'Permite visualizar o plano de contas contábil (estrutura hierárquica + grupos DRE)',
            self::CREATE_ACCOUNTING_CLASSES => 'Permite cadastrar novas contas contábeis no plano',
            self::EDIT_ACCOUNTING_CLASSES => 'Permite editar contas existentes (incluindo reclassificação DRE)',
            self::DELETE_ACCOUNTING_CLASSES => 'Permite excluir contas contábeis (soft delete). Bloqueado se houver filhas ativas ou lançamentos',
            self::MANAGE_ACCOUNTING_CLASSES => 'Permite gerenciar toda a estrutura do plano de contas (alterar hierarquia e grupos DRE)',
            self::IMPORT_ACCOUNTING_CLASSES => 'Permite importar plano de contas personalizado via planilha',
            self::EXPORT_ACCOUNTING_CLASSES => 'Permite exportar o plano de contas para Excel',
            // Plano de Contas Gerencial
            self::VIEW_MANAGEMENT_CLASSES => 'Permite visualizar o plano de contas gerencial (visão interna operacional, complementar ao plano contábil)',
            self::CREATE_MANAGEMENT_CLASSES => 'Permite cadastrar novas contas gerenciais',
            self::EDIT_MANAGEMENT_CLASSES => 'Permite editar contas gerenciais (incluindo vínculo com conta contábil e centro de custo default)',
            self::DELETE_MANAGEMENT_CLASSES => 'Permite excluir contas gerenciais (soft delete). Bloqueado se houver filhas ativas',
            self::MANAGE_MANAGEMENT_CLASSES => 'Permite gerenciar toda a estrutura do plano gerencial (hierarquia e vínculos com contábil)',
            self::IMPORT_MANAGEMENT_CLASSES => 'Permite importar plano gerencial via planilha',
            self::EXPORT_MANAGEMENT_CLASSES => 'Permite exportar plano gerencial para Excel',
            // Orçamentos
            self::VIEW_BUDGETS => 'Permite visualizar orçamentos cadastrados, com filtros por ano/escopo/versão',
            self::UPLOAD_BUDGETS => 'Permite enviar novas planilhas de orçamento (Excel). Aciona o fluxo de preview + reconciliação de FKs',
            self::DOWNLOAD_BUDGETS => 'Permite baixar a planilha xlsx original armazenada (para auditoria ou re-upload)',
            self::DELETE_BUDGETS => 'Permite excluir versões não-ativas. A versão ativa nunca pode ser excluída diretamente — só desativada por uma nova versão',
            self::MANAGE_BUDGETS => 'Permite gerenciar orçamentos de todos os escopos (sem filtro de área)',
            self::EXPORT_BUDGETS => 'Permite exportar orçamento consolidado em Excel (com colunas de previsto × realizado lado a lado)',
            self::VIEW_BUDGET_CONSUMPTION => 'Permite acessar o dashboard de consumo previsto × realizado, com alertas de utilização',

            // Cupons
            self::VIEW_COUPONS => 'Permite visualizar cupons. Sem MANAGE_COUPONS, o usuário só vê cupons da sua loja',
            self::CREATE_COUPONS => 'Permite criar solicitações de cupom. Sem MANAGE_COUPONS, só pode criar para a própria loja',
            self::EDIT_COUPONS => 'Permite editar cupons enquanto estiverem em Draft ou Requested. Cupons emitidos/ativos só editáveis com MANAGE_COUPONS',
            self::DELETE_COUPONS => 'Permite excluir cupons (soft delete). Bloqueado se já tiver código emitido',
            self::MANAGE_COUPONS => 'Permite gerenciar cupons de todas as lojas (sem filtro de store scoping). Pode editar cupons em qualquer status',
            self::ISSUE_COUPON_CODE => 'Permite emitir o código do cupom (preencher coupon_site) na plataforma de e-commerce e transicionar requested → issued',
            self::IMPORT_COUPONS => 'Permite importar cupons via planilha (XLSX/CSV) — usado na migração de dados históricos v1',
            self::EXPORT_COUPONS => 'Permite exportar cupons para Excel ou PDF (listagem com CPF mascarado)',

            self::VIEW_DRE => 'Permite visualizar a matriz gerencial da DRE (realizado × orçado × ano anterior), por período/loja/rede',
            self::MANAGE_DRE_STRUCTURE => 'Permite criar, editar, reordenar e remover linhas do plano gerencial da DRE (ex: EBITDA, Headcount, Lucro Líquido)',
            self::MANAGE_DRE_MAPPINGS => 'Permite criar o de-para entre conta contábil analítica (+ centro de custo opcional) e linha gerencial da DRE. Atribuições em lote também usam essa permissão',
            self::VIEW_DRE_PENDING_ACCOUNTS => 'Permite visualizar a fila de contas analíticas de resultado (grupos 3/4/5) que ainda não têm mapeamento vigente',
            self::IMPORT_DRE_ACTUALS => 'Permite importar realizado manual (balancete externo, cash movements fora do ERP) via XLSX para dre_actuals com source=MANUAL_IMPORT',
            self::IMPORT_DRE_BUDGETS => 'Permite importar orçado manual via XLSX para dre_budgets (alternativa ao fluxo Budgets → projeção automática)',
            self::MANAGE_DRE_PERIODS => 'Permite fechar períodos (gera snapshots imutáveis) e reabrir fechamentos (com justificativa obrigatória e diff consolidado). Fechar bloqueia edição de mappings/imports no período.',
            self::EXPORT_DRE => 'Permite exportar a matriz DRE (mesmo filtro aplicado na tela) como XLSX multi-sheet ou PDF A4 landscape para compartilhamento externo.',

            self::VIEW_CUSTOMERS => 'Permite visualizar clientes sincronizados do CIGAM. Módulo majoritariamente read-only — edição acontece no ERP',
            self::EXPORT_CUSTOMERS => 'Permite exportar base de clientes em Excel/CSV (mascara CPF)',
            self::SYNC_CUSTOMERS => 'Permite disparar sync manual com CIGAM fora do schedule padrão (dailyAt 04:00)',

            self::VIEW_VIP_CUSTOMERS => 'Permite visualizar listagem anual de VIPs (Black/Gold), incluindo histórico de anos anteriores',
            self::MANAGE_VIP_CUSTOMERS => 'Permite disparar a geração automática de sugestões de VIPs com base no faturamento anual (movements code=2 soma, code=6+E subtrai)',
            self::CURATE_VIP_CUSTOMERS => 'Permite promover/rebaixar/remover clientes da lista VIP após a sugestão automática. Exclusivo da equipe de Marketing',
            self::VIEW_VIP_REPORTS => 'Permite acessar o relatório YoY de faturamento do cliente VIP (ano atual vs ano anterior, mesmo período)',
            self::MANAGE_VIP_ACTIVITIES => 'Permite registrar atividades de relacionamento com o VIP (envio de brinde, convite para evento, contato, nota)',
            self::MANAGE_VIP_TIER_CONFIG => 'Permite cadastrar/editar os thresholds mínimos de faturamento por tier e ano (base para a sugestão automática)',
            self::IMPORT_VIP_CUSTOMERS => 'Permite importar listas de clientes VIP via XLSX (cpf + ano + status). Aplica como curadoria manual em lote, com opção de substituir a lista do ano',

            self::VIEW_CONSIGNMENTS => 'Permite visualizar consignações. Sem MANAGE_CONSIGNMENTS, o usuário só vê consignações da sua loja',
            self::CREATE_CONSIGNMENTS => 'Permite criar novas consignações. Sem MANAGE_CONSIGNMENTS, só para a própria loja',
            self::EDIT_CONSIGNMENTS => 'Permite editar consignações em aberto (draft, pending, partially_returned, overdue). Inclui marcar itens como vendidos. Finalizadas/canceladas só com OVERRIDE_CONSIGNMENT_LOCK',
            self::DELETE_CONSIGNMENTS => 'Permite excluir consignações (soft delete). Bloqueado se tiver nota de retorno já lançada',
            self::MANAGE_CONSIGNMENTS => 'Permite gerenciar consignações em qualquer loja. Sem essa permissão o usuário só gerencia consignações da própria loja',
            self::REGISTER_CONSIGNMENT_RETURN => 'Permite lançar nota fiscal de retorno (movement_code=21) e conferir diff dos itens contra a NF de saída',
            self::COMPLETE_CONSIGNMENT => 'Permite finalizar consignação (estado terminal). Itens pendentes precisam ser marcados como perdidos (shrinkage) com justificativa',
            self::CANCEL_CONSIGNMENT => 'Permite cancelar consignação (estado terminal). Exige motivo. Cancelamento não retorna itens ao estoque — é apenas registro',
            self::EXPORT_CONSIGNMENTS => 'Permite exportar consignações para Excel (2 abas: cabeçalhos + itens) ou PDF comprovante individual',
            self::IMPORT_CONSIGNMENTS => 'Permite importar consignações via planilha (XLSX/CSV) — usado na migração de dados históricos v1',
            self::OVERRIDE_CONSIGNMENT_LOCK => 'Permite (a) criar consignação para destinatário com outra em atraso (override do bloqueio por inadimplência) e (b) editar consignações já finalizadas ou canceladas. Exige justificativa que fica no histórico.',

            self::VIEW_TRAVEL_EXPENSES => 'Permite visualizar verbas de viagem. Sem MANAGE_TRAVEL_EXPENSES, o usuário só vê verbas que solicitou ou onde é beneficiado',
            self::CREATE_TRAVEL_EXPENSES => 'Permite criar solicitações de verba de viagem para si ou outro colaborador',
            self::EDIT_TRAVEL_EXPENSES => 'Permite editar a solicitação enquanto estiver em rascunho ou submetida (antes da aprovação). Após aprovação só com MANAGE_TRAVEL_EXPENSES',
            self::DELETE_TRAVEL_EXPENSES => 'Permite excluir solicitação (soft delete). Bloqueado se já tiver prestação de contas com itens lançados',
            self::APPROVE_TRAVEL_EXPENSES => 'Permite aprovar/rejeitar solicitações de verba (transição submitted → approved/rejected) e a prestação de contas (accountability submitted → approved/rejected). Pertence ao Financeiro/Contas a Pagar',
            self::MANAGE_TRAVEL_EXPENSES => 'Permite gerenciar verbas de todas as lojas (sem filtro de escopo) e operar em qualquer status, inclusive cancelar verbas já aprovadas',
            self::MANAGE_ACCOUNTABILITY => 'Permite lançar/editar itens da prestação de contas (recibos, NFs, comprovantes). Solicitante e beneficiado têm essa permissão por padrão',
            self::EXPORT_TRAVEL_EXPENSES => 'Permite exportar verbas para Excel (listagem) ou PDF (comprovante individual com prestação de contas)',
        };
    }

    public static function all(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn ($case) => $case->value, self::cases()),
            array_map(fn ($case) => $case->label(), self::cases())
        );
    }
}
