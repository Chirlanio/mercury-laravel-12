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

    public function label(): string
    {
        return match($this) {
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

            self::VIEW_CHECKLISTS => 'Visualizar checklists',
            self::CREATE_CHECKLISTS => 'Criar checklists',
            self::EDIT_CHECKLISTS => 'Editar checklists',
            self::DELETE_CHECKLISTS => 'Deletar checklists',

            self::VIEW_MEDICAL_CERTIFICATES => 'Visualizar atestados medicos',
            self::CREATE_MEDICAL_CERTIFICATES => 'Criar atestados medicos',
            self::EDIT_MEDICAL_CERTIFICATES => 'Editar atestados medicos',
            self::DELETE_MEDICAL_CERTIFICATES => 'Deletar atestados medicos',

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
        };
    }

    public function description(): string
    {
        return match($this) {
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

            self::VIEW_CHECKLISTS => 'Permite visualizar checklists de qualidade',
            self::CREATE_CHECKLISTS => 'Permite criar novos checklists de qualidade',
            self::EDIT_CHECKLISTS => 'Permite editar e responder checklists',
            self::DELETE_CHECKLISTS => 'Permite deletar checklists pendentes',

            self::VIEW_MEDICAL_CERTIFICATES => 'Permite visualizar atestados medicos',
            self::CREATE_MEDICAL_CERTIFICATES => 'Permite cadastrar novos atestados medicos',
            self::EDIT_MEDICAL_CERTIFICATES => 'Permite editar atestados medicos',
            self::DELETE_MEDICAL_CERTIFICATES => 'Permite excluir atestados medicos',

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
        };
    }

    public static function all(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn($case) => $case->value, self::cases()),
            array_map(fn($case) => $case->label(), self::cases())
        );
    }
}