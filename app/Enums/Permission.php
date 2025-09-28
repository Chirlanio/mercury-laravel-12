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