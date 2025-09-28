<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auditoria Habilitada
    |--------------------------------------------------------------------------
    |
    | Define se a auditoria está habilitada globalmente no sistema.
    |
    */

    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Modelos que Permitem Auditoria sem Autenticação
    |--------------------------------------------------------------------------
    |
    | Lista de modelos que podem ser auditados mesmo quando não há usuário
    | autenticado. Útil para logs de sistema ou operações automáticas.
    |
    */

    'allow_without_auth' => [
        // App\Models\SystemLog::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Limpeza Automática
    |--------------------------------------------------------------------------
    |
    | Define configurações para limpeza automática de logs antigos.
    |
    */

    'cleanup' => [
        'enabled' => env('AUDIT_CLEANUP_ENABLED', false),
        'older_than_days' => env('AUDIT_CLEANUP_DAYS', 365),
        'batch_size' => env('AUDIT_CLEANUP_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Detecção de Atividade Suspeita
    |--------------------------------------------------------------------------
    |
    | Configurações para detecção de atividades suspeitas.
    |
    */

    'suspicious_activity' => [
        'enabled' => env('AUDIT_SUSPICIOUS_DETECTION', true),
        'time_window_minutes' => 5,
        'max_actions_per_window' => 20,
        'max_ips_per_window' => 2,
        'max_access_denied_per_window' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Exportação
    |--------------------------------------------------------------------------
    |
    | Configurações para exportação de logs de auditoria.
    |
    */

    'export' => [
        'formats' => ['csv', 'excel', 'json'],
        'max_records' => env('AUDIT_EXPORT_MAX_RECORDS', 10000),
        'batch_size' => env('AUDIT_EXPORT_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Campos Globalmente Ignorados
    |--------------------------------------------------------------------------
    |
    | Campos que serão ignorados em todos os modelos auditáveis.
    |
    */

    'global_ignore' => [
        'updated_at',
        'created_at',
        'deleted_at',
        'remember_token',
        'password',
        'password_confirmation',
        'email_verified_at',
        '_token',
        '_method',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção de Dados por Tipo de Ação
    |--------------------------------------------------------------------------
    |
    | Define por quantos dias manter os logs baseado no tipo de ação.
    |
    */

    'retention_by_action' => [
        'login' => 90,           // 3 meses
        'logout' => 30,          // 1 mês
        'create' => 365,         // 1 ano
        'update' => 365,         // 1 ano
        'delete' => 2555,        // 7 anos (requerimento legal)
        'access' => 30,          // 1 mês
        'access_denied' => 365,  // 1 ano (segurança)
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Performance
    |--------------------------------------------------------------------------
    |
    | Configurações para otimizar a performance da auditoria.
    |
    */

    'performance' => [
        'queue_enabled' => env('AUDIT_QUEUE_ENABLED', false),
        'queue_name' => env('AUDIT_QUEUE_NAME', 'audit'),
        'batch_insert' => env('AUDIT_BATCH_INSERT', false),
        'batch_size' => env('AUDIT_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatação de Logs
    |--------------------------------------------------------------------------
    |
    | Configurações para formatação e apresentação dos logs.
    |
    */

    'formatting' => [
        'date_format' => 'd/m/Y H:i:s',
        'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
        'locale' => 'pt_BR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Notificação
    |--------------------------------------------------------------------------
    |
    | Configurações para notificações de atividades suspeitas ou importantes.
    |
    */

    'notifications' => [
        'suspicious_activity' => [
            'enabled' => env('AUDIT_NOTIFY_SUSPICIOUS', false),
            'channels' => ['mail', 'slack'],
            'recipients' => [
                // 'admin@example.com'
            ],
        ],
        'critical_actions' => [
            'enabled' => env('AUDIT_NOTIFY_CRITICAL', false),
            'actions' => ['delete', 'access_denied'],
            'channels' => ['mail'],
            'recipients' => [
                // 'security@example.com'
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integração com Logs do Laravel
    |--------------------------------------------------------------------------
    |
    | Define se deve também registrar no log padrão do Laravel.
    |
    */

    'laravel_log' => [
        'enabled' => env('AUDIT_LARAVEL_LOG', false),
        'level' => env('AUDIT_LOG_LEVEL', 'info'),
        'channel' => env('AUDIT_LOG_CHANNEL', 'audit'),
    ],

];