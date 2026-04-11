<?php

/**
 * Module definitions for the SaaS platform.
 *
 * Each module maps to route groups, permissions, and menu sections.
 * Used by CheckTenantModule middleware and MenuService to control access.
 */

return [
    'dashboard' => [
        'name' => 'Dashboard',
        'description' => 'Painel principal com indicadores e métricas.',
        'routes' => ['dashboard'],
        'icon' => 'HomeIcon',
    ],
    'users' => [
        'name' => 'Usuários',
        'description' => 'Gestão de usuários do sistema.',
        'routes' => ['users.*'],
        'icon' => 'UserGroupIcon',
    ],
    'employees' => [
        'name' => 'Funcionários',
        'description' => 'Cadastro e gestão de funcionários, contratos e histórico.',
        'routes' => ['employees.*'],
        'icon' => 'IdentificationIcon',
    ],
    'stores' => [
        'name' => 'Lojas',
        'description' => 'Gestão de lojas e unidades.',
        'routes' => ['stores.*'],
        'icon' => 'BuildingStorefrontIcon',
    ],
    'sales' => [
        'name' => 'Vendas',
        'description' => 'Registro e consulta de vendas.',
        'routes' => ['sales.*'],
        'icon' => 'CurrencyDollarIcon',
    ],
    'products' => [
        'name' => 'Produtos',
        'description' => 'Catálogo de produtos e variantes.',
        'routes' => ['products.*'],
        'icon' => 'CubeIcon',
    ],
    'transfers' => [
        'name' => 'Transferências',
        'description' => 'Transferências entre lojas.',
        'routes' => ['transfers.*'],
        'icon' => 'ArrowsRightLeftIcon',
    ],
    'stock_adjustments' => [
        'name' => 'Ajustes de Estoque',
        'description' => 'Ajustes e correções de estoque.',
        'routes' => ['stock-adjustments.*'],
        'icon' => 'ClipboardDocumentListIcon',
    ],
    'order_payments' => [
        'name' => 'Ordens de Pagamento',
        'description' => 'Solicitações e controle de pagamentos.',
        'routes' => ['order-payments.*'],
        'icon' => 'BanknotesIcon',
    ],
    'suppliers' => [
        'name' => 'Fornecedores',
        'description' => 'Cadastro de fornecedores.',
        'routes' => ['suppliers.*'],
        'icon' => 'TruckIcon',
    ],
    'checklists' => [
        'name' => 'Checklists',
        'description' => 'Checklists de qualidade.',
        'routes' => ['checklists.*'],
        'icon' => 'ClipboardDocumentCheckIcon',
    ],
    'medical_certificates' => [
        'name' => 'Atestados Médicos',
        'description' => 'Controle de atestados médicos.',
        'routes' => ['medical-certificates.*'],
        'icon' => 'DocumentTextIcon',
    ],
    'absences' => [
        'name' => 'Faltas',
        'description' => 'Controle de faltas de funcionários.',
        'routes' => ['absences.*'],
        'icon' => 'CalendarDaysIcon',
    ],
    'overtime' => [
        'name' => 'Horas Extras',
        'description' => 'Controle de horas extras.',
        'routes' => ['overtime-records.*'],
        'icon' => 'ClockIcon',
    ],
    'work_shifts' => [
        'name' => 'Jornadas de Trabalho',
        'description' => 'Definição de jornadas de trabalho.',
        'routes' => ['work-shifts.*'],
        'icon' => 'CalendarIcon',
    ],
    'work_schedules' => [
        'name' => 'Escalas de Trabalho',
        'description' => 'Escalas e quadros de horários.',
        'routes' => ['work-schedules.*'],
        'icon' => 'TableCellsIcon',
    ],
    'menus' => [
        'name' => 'Menus',
        'description' => 'Gestão de menus do sistema.',
        'routes' => ['menus.*'],
        'icon' => 'Bars3Icon',
    ],
    'pages' => [
        'name' => 'Páginas',
        'description' => 'Gestão de páginas e grupos.',
        'routes' => ['pages.*', 'page-groups.*'],
        'icon' => 'DocumentIcon',
    ],
    'access_levels' => [
        'name' => 'Níveis de Acesso',
        'description' => 'Configuração de permissões por nível.',
        'routes' => ['access-levels.*'],
        'icon' => 'ShieldCheckIcon',
    ],
    'color_themes' => [
        'name' => 'Temas de Cores',
        'description' => 'Personalização visual.',
        'routes' => ['color-themes.*'],
        'icon' => 'SwatchIcon',
    ],
    'activity_logs' => [
        'name' => 'Logs de Atividade',
        'description' => 'Histórico de ações no sistema.',
        'routes' => ['activity-logs.*'],
        'icon' => 'ClipboardDocumentListIcon',
    ],
    'user_sessions' => [
        'name' => 'Usuários Online',
        'description' => 'Monitoramento de sessões ativas.',
        'routes' => ['user-sessions.*'],
        'icon' => 'SignalIcon',
    ],
    'config' => [
        'name' => 'Configurações',
        'description' => 'Tabelas de configuração do sistema.',
        'routes' => ['config.*'],
        'icon' => 'CogIcon',
    ],
    'integrations' => [
        'name' => 'Integrações',
        'description' => 'Integrações com sistemas externos (CIGAM, SAP, etc.).',
        'routes' => ['integrations.*'],
        'icon' => 'LinkIcon',
    ],
    'store_goals' => [
        'name' => 'Metas de Loja',
        'description' => 'Definição e acompanhamento de metas por loja.',
        'routes' => ['store-goals.*'],
        'icon' => 'ChartBarIcon',
    ],
    'movements' => [
        'name' => 'Movimentações',
        'description' => 'Movimentações diárias de estoque sincronizadas do CIGAM.',
        'routes' => ['movements.*'],
        'icon' => 'ArrowPathIcon',
    ],
    'vacations' => [
        'name' => 'Férias',
        'description' => 'Solicitação, aprovação e controle de férias.',
        'routes' => ['vacations.*'],
        'icon' => 'SunIcon',
        'dependencies' => ['employees'],
    ],
    'stock_audits' => [
        'name' => 'Auditoria de Estoque',
        'description' => 'Inventário com contagem, conciliação CIGAM e relatórios.',
        'routes' => ['stock-audits.*'],
        'icon' => 'ClipboardDocumentCheckIcon',
        'dependencies' => ['products', 'stores'],
    ],
    'personnel_movements' => [
        'name' => 'Movimentação de Pessoal',
        'description' => 'Gestão de movimentações: desligamento, promoção, transferência e reativação.',
        'routes' => ['personnel-movements.*'],
        'icon' => 'ArrowsUpDownIcon',
        'dependencies' => ['employees'],
    ],
    'training' => [
        'name' => 'Treinamentos',
        'description' => 'Gestão de treinamentos, eventos de capacitação, facilitadores e certificados.',
        'routes' => ['trainings.*', 'training-contents.*', 'training-content-categories.*', 'training-courses.*', 'my-trainings.*', 'training-reports.*', 'training-quizzes.*', 'training-quiz-attempts.*'],
        'icon' => 'AcademicCapIcon',
        'dependencies' => ['employees', 'stores'],
    ],
    'experience-tracker' => [
        'name' => 'Avaliacao de Experiencia',
        'description' => 'Acompanhamento do periodo de experiencia (45/90 dias).',
        'routes' => ['experience-tracker.*'],
        'icon' => 'ClipboardDocumentCheckIcon',
        'dependencies' => ['employees', 'stores'],
    ],
];
