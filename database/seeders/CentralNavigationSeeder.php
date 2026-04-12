<?php

namespace Database\Seeders;

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use App\Models\CentralRole;
use Illuminate\Database\Seeder;

/**
 * Seeds central navigation tables from existing tenant seeder data.
 * Run once to bootstrap central definitions from the current navigation structure.
 */
class CentralNavigationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPageGroups();
        $this->seedMenus();
        $this->seedPages();
        $this->seedMenuPageDefaults();
    }

    protected function seedPageGroups(): void
    {
        $groups = ['Listar', 'Cadastrar', 'Editar', 'Apagar', 'Visualizar', 'Outros', 'Acesso', 'Pesquisar'];

        foreach ($groups as $name) {
            CentralPageGroup::updateOrCreate(['name' => $name]);
        }
    }

    protected function seedMenus(): void
    {
        $menus = [
            ['name' => 'Home', 'icon' => 'fas fa-home', 'order' => 1, 'type' => 'main'],
            ['name' => 'Usuário', 'icon' => 'fas fa-user', 'order' => 2, 'type' => 'main'],
            ['name' => 'Produto', 'icon' => 'fas fa-shopping-cart', 'order' => 3, 'type' => 'main'],
            ['name' => 'Planejamento', 'icon' => 'fa-solid fa-diagram-project', 'order' => 4, 'type' => 'main'],
            ['name' => 'Financeiro', 'icon' => 'fas fa-credit-card', 'order' => 5, 'type' => 'main'],
            ['name' => 'RH', 'icon' => 'fas fa-users-cog', 'order' => 6, 'type' => 'hr'],
            ['name' => 'Comercial', 'icon' => 'fa-solid fa-money-bill-wave', 'order' => 7, 'type' => 'main'],
            ['name' => 'Operações', 'icon' => 'fas fa-cogs', 'order' => 8, 'type' => 'main'],
            ['name' => 'Delivery', 'icon' => 'fas fa-shipping-fast', 'order' => 9, 'type' => 'main'],
            ['name' => 'Rotas', 'icon' => 'fa-solid fa-map-location-dot', 'order' => 10, 'type' => 'main'],
            ['name' => 'E-commerce', 'icon' => 'fa-solid fa-store', 'order' => 11, 'type' => 'main'],
            ['name' => 'Dashboard\'s', 'icon' => 'fas fa-chart-pie', 'order' => 12, 'type' => 'main'],
            ['name' => 'Qualidade', 'icon' => 'fa-solid fa-industry', 'order' => 13, 'type' => 'main'],
            ['name' => 'Pessoas & Cultura', 'icon' => 'fas fa-users', 'order' => 14, 'type' => 'hr'],
            ['name' => 'Departamento Pessoal', 'icon' => 'fa-solid fa-address-card', 'order' => 15, 'type' => 'hr'],
            ['name' => 'Escola Digital', 'icon' => 'fa-solid fa-video', 'order' => 16, 'type' => 'main'],
            ['name' => 'Movidesk', 'icon' => 'fas fa-headset', 'order' => 17, 'type' => 'utility'],
            ['name' => 'Biblioteca de Processos', 'icon' => 'fa-solid fa-landmark', 'order' => 18, 'type' => 'utility'],
            ['name' => 'FAQ\'s', 'icon' => 'fas fa-question-circle', 'order' => 19, 'type' => 'utility'],
            ['name' => 'Configurações', 'icon' => 'fas fa-cogs', 'order' => 20, 'type' => 'system'],
            ['name' => 'Sair', 'icon' => 'fas fa-sign-out-alt', 'order' => 21, 'type' => 'system'],
            ['name' => 'Ativo Fixo', 'icon' => 'fa-solid fa-file-signature', 'order' => 22, 'type' => 'main'],
        ];

        foreach ($menus as $menu) {
            CentralMenu::updateOrCreate(
                ['name' => $menu['name'], 'parent_id' => null],
                [
                    'icon' => $menu['icon'],
                    'order' => $menu['order'],
                    'is_active' => true,
                    'type' => $menu['type'],
                ]
            );
        }

        // Submenus
        $submenus = [
            'Departamento Pessoal' => [
                ['name' => 'Funcionários', 'icon' => 'fas fa-users', 'order' => 1],
                ['name' => 'Controle de Jornada', 'icon' => 'fas fa-clock', 'order' => 2],
            ],
            'Configurações' => [
                ['name' => 'Gerenciar Níveis', 'icon' => 'fas fa-shield-alt', 'order' => 1],
                ['name' => 'Gerenciar Menus', 'icon' => 'fas fa-bars', 'order' => 2],
                ['name' => 'Gerenciar Páginas', 'icon' => 'fas fa-file-alt', 'order' => 3],
                ['name' => 'Logs de Atividade', 'icon' => 'fas fa-history', 'order' => 4],
                ['name' => 'Configurações de Email', 'icon' => 'fas fa-envelope-open-text', 'order' => 5],
            ],
        ];

        foreach ($submenus as $parentName => $children) {
            $parent = CentralMenu::where('name', $parentName)->whereNull('parent_id')->first();
            if (! $parent) {
                continue;
            }

            foreach ($children as $child) {
                CentralMenu::updateOrCreate(
                    ['name' => $child['name'], 'parent_id' => $parent->id],
                    [
                        'icon' => $child['icon'],
                        'order' => $child['order'],
                        'is_active' => true,
                        'type' => $parent->type,
                    ]
                );
            }
        }
    }

    protected function seedPages(): void
    {
        $listarGroup = CentralPageGroup::where('name', 'Listar')->first();

        // Module slug mapping: route prefix → central_module slug
        $routeToModule = [
            '/dashboard' => 'dashboard',
            '/users' => 'users',
            '/employees' => 'employees',
            '/sales' => 'sales',
            '/products' => 'products',
            '/stores' => 'stores',
            '/work-shifts' => 'work_shifts',
            '/work-schedules' => 'work_schedules',
            '/user-sessions' => 'user_sessions',
            '/transfers' => 'transfers',
            '/deliveries' => 'delivery',
            '/delivery-routes' => 'delivery',
            '/driver-dashboard' => 'delivery',
            '/my-deliveries' => 'delivery',
            '/stock-adjustments' => 'stock_adjustments',
            '/stock-audits' => 'stock_audits',
            '/order-payments' => 'order_payments',
            '/suppliers' => 'suppliers',
            '/medical-certificates' => 'medical_certificates',
            '/absences' => 'absences',
            '/overtime-records' => 'overtime',
            '/checklists' => 'checklists',
            '/activity-logs' => 'activity_logs',
            '/config/' => 'config',
            '/integrations' => 'integrations',
            '/color-themes' => 'color_themes',
            '/store-goals' => 'store_goals',
            '/movements' => 'movements',
            '/vacations' => 'vacations',
            '/personnel-movements' => 'personnel_movements',
            '/trainings' => 'training',
            '/my-trainings' => 'training',
            '/training-reports' => 'training',
            '/experience-tracker' => 'experience-tracker',
        ];

        $pages = [
            ['route' => '/dashboard', 'page_name' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt'],
            ['route' => '/users', 'page_name' => 'Usuários', 'icon' => 'fas fa-users'],
            ['route' => '/employees', 'page_name' => 'Funcionários', 'icon' => 'fas fa-id-badge'],
            ['route' => '/sales', 'page_name' => 'Vendas', 'icon' => 'fas fa-chart-line'],
            ['route' => '/products', 'page_name' => 'Produtos', 'icon' => 'fas fa-box'],
            ['route' => '/stores', 'page_name' => 'Lojas', 'icon' => 'fas fa-store'],
            ['route' => '/work-shifts', 'page_name' => 'Turnos', 'icon' => 'fas fa-clock'],
            ['route' => '/work-schedules', 'page_name' => 'Escalas de Trabalho', 'icon' => 'fas fa-calendar-alt'],
            ['route' => '/user-sessions', 'page_name' => 'Usuários Online', 'icon' => 'fas fa-wifi'],
            ['route' => '/transfers', 'page_name' => 'Transferências', 'icon' => 'fas fa-exchange-alt'],
            ['route' => '/deliveries', 'page_name' => 'Entregas', 'icon' => 'fas fa-truck'],
            ['route' => '/delivery-routes', 'page_name' => 'Rotas de Entrega', 'icon' => 'fas fa-route'],
            ['route' => '/driver-dashboard', 'page_name' => 'Painel do Motorista', 'icon' => 'fas fa-shipping-fast'],
            ['route' => '/my-deliveries', 'page_name' => 'Minhas Entregas', 'icon' => 'fas fa-box-open'],
            ['route' => '/stock-adjustments', 'page_name' => 'Ajustes de Estoque', 'icon' => 'fas fa-clipboard-check'],
            ['route' => '/stock-audits', 'page_name' => 'Auditoria de Estoque', 'icon' => 'fas fa-clipboard-list'],
            ['route' => '/order-payments', 'page_name' => 'Ordens de Pagamento', 'icon' => 'fas fa-money-bill-wave'],
            ['route' => '/suppliers', 'page_name' => 'Fornecedores', 'icon' => 'fas fa-truck'],
            ['route' => '/medical-certificates', 'page_name' => 'Atestados Médicos', 'icon' => 'fas fa-file-medical'],
            ['route' => '/absences', 'page_name' => 'Faltas', 'icon' => 'fas fa-user-times'],
            ['route' => '/overtime-records', 'page_name' => 'Horas Extras', 'icon' => 'fas fa-business-time'],
            ['route' => '/checklists', 'page_name' => 'Checklists', 'icon' => 'fas fa-tasks'],
            ['route' => '/activity-logs', 'page_name' => 'Logs de Atividade', 'icon' => 'fas fa-history'],
            ['route' => '/config/positions', 'page_name' => 'Cargos', 'icon' => 'fas fa-cog'],
            ['route' => '/config/sectors', 'page_name' => 'Setores', 'icon' => 'fas fa-cog'],
            ['route' => '/config/position-levels', 'page_name' => 'Níveis de Cargo', 'icon' => 'fas fa-cog'],
            ['route' => '/config/genders', 'page_name' => 'Gêneros', 'icon' => 'fas fa-cog'],
            ['route' => '/config/education-levels', 'page_name' => 'Escolaridades', 'icon' => 'fas fa-cog'],
            ['route' => '/config/employee-statuses', 'page_name' => 'Situações de Funcionário', 'icon' => 'fas fa-cog'],
            ['route' => '/config/networks', 'page_name' => 'Redes', 'icon' => 'fas fa-cog'],
            ['route' => '/config/managers', 'page_name' => 'Gestores', 'icon' => 'fas fa-cog'],
            ['route' => '/config/banks', 'page_name' => 'Bancos', 'icon' => 'fas fa-cog'],
            ['route' => '/config/cost-centers', 'page_name' => 'Centros de Custo', 'icon' => 'fas fa-cog'],
            ['route' => '/config/payment-types', 'page_name' => 'Tipos de Pagamento', 'icon' => 'fas fa-cog'],
            ['route' => '/config/drivers', 'page_name' => 'Motoristas', 'icon' => 'fas fa-cog'],
            ['route' => '/integrations', 'page_name' => 'Integrações', 'icon' => 'fas fa-link'],
            ['route' => '/store-goals', 'page_name' => 'Metas de Loja', 'icon' => 'fas fa-bullseye'],
            ['route' => '/movements', 'page_name' => 'Movimentações', 'icon' => 'fas fa-arrows-alt'],
            ['route' => '/vacations', 'page_name' => 'Férias', 'icon' => 'fas fa-umbrella-beach'],
            ['route' => '/personnel-movements', 'page_name' => 'Movimentação de Pessoal', 'icon' => 'fas fa-people-arrows'],
            ['route' => '/trainings', 'page_name' => 'Treinamentos', 'icon' => 'fas fa-graduation-cap'],
            ['route' => '/my-trainings', 'page_name' => 'Meus Treinamentos', 'icon' => 'fas fa-book-reader'],
            ['route' => '/training-reports', 'page_name' => 'Relatórios de Treinamento', 'icon' => 'fas fa-chart-bar'],
            ['route' => '/experience-tracker', 'page_name' => 'Avaliação de Experiência', 'icon' => 'fas fa-clipboard-check'],
            ['route' => '/config/stock-audit-cycles', 'page_name' => 'Ciclos de Auditoria', 'icon' => 'fas fa-cog'],
            ['route' => '/config/stock-audit-vendors', 'page_name' => 'Empresas Auditoras', 'icon' => 'fas fa-cog'],
            ['route' => '/logout', 'page_name' => 'Sair', 'icon' => 'fas fa-sign-out-alt', 'is_public' => true],
        ];

        // Cache module IDs
        $moduleIds = CentralModule::pluck('id', 'slug')->toArray();

        foreach ($pages as $page) {
            // Determine module_id from route
            $moduleId = null;
            foreach ($routeToModule as $prefix => $moduleSlug) {
                if (str_starts_with($page['route'], $prefix)) {
                    $moduleId = $moduleIds[$moduleSlug] ?? null;
                    break;
                }
            }

            CentralPage::updateOrCreate(
                ['route' => $page['route']],
                [
                    'page_name' => $page['page_name'],
                    'icon' => $page['icon'] ?? null,
                    'is_public' => $page['is_public'] ?? false,
                    'is_active' => true,
                    'central_page_group_id' => $listarGroup?->id,
                    'central_module_id' => $moduleId,
                ]
            );
        }
    }

    protected function seedMenuPageDefaults(): void
    {
        // Route → menu name mapping (which menu each page belongs to)
        $routeToMenu = [
            '/dashboard' => 'Home',
            '/users' => 'Usuário',
            '/employees' => 'RH',
            '/sales' => 'Comercial',
            '/products' => 'Comercial',
            '/stores' => 'Comercial',
            '/work-shifts' => 'RH',
            '/work-schedules' => 'RH',
            '/user-sessions' => 'RH',
            '/transfers' => 'Operações',
            '/deliveries' => 'Operações',
            '/delivery-routes' => 'Operações',
            '/driver-dashboard' => 'Operações',
            '/my-deliveries' => 'Operações',
            '/stock-adjustments' => 'Operações',
            '/stock-audits' => 'Operações',
            '/order-payments' => 'Financeiro',
            '/suppliers' => 'Configurações',
            '/medical-certificates' => 'RH',
            '/absences' => 'RH',
            '/overtime-records' => 'RH',
            '/checklists' => 'Qualidade',
            '/activity-logs' => 'Configurações',
            '/config/positions' => 'Configurações',
            '/config/sectors' => 'Configurações',
            '/config/position-levels' => 'Configurações',
            '/config/genders' => 'Configurações',
            '/config/education-levels' => 'Configurações',
            '/config/employee-statuses' => 'Configurações',
            '/config/networks' => 'Configurações',
            '/config/managers' => 'Configurações',
            '/config/banks' => 'Configurações',
            '/config/cost-centers' => 'Configurações',
            '/config/payment-types' => 'Configurações',
            '/config/drivers' => 'Configurações',
            '/integrations' => 'Configurações',
            '/store-goals' => 'Comercial',
            '/movements' => 'Comercial',
            '/vacations' => 'RH',
            '/personnel-movements' => 'RH',
            '/trainings' => 'RH',
            '/my-trainings' => 'RH',
            '/training-reports' => 'RH',
            '/experience-tracker' => 'RH',
            '/config/stock-audit-cycles' => 'Configurações',
            '/config/stock-audit-vendors' => 'Configurações',
            '/logout' => 'Sair',
        ];

        // Direct items (not dropdown)
        $directItems = ['/dashboard', '/logout'];

        // Role permissions: which roles see which pages
        // super_admin: everything | admin: everything except system management | support: view-only | user: dashboard only
        $viewOnlyRoutes = [
            '/dashboard', '/sales', '/products', '/stores', '/employees',
            '/transfers', '/stock-adjustments', '/order-payments', '/suppliers',
            '/checklists', '/medical-certificates', '/absences', '/overtime-records',
            '/user-sessions', '/work-shifts', '/work-schedules', '/activity-logs',
            '/store-goals', '/movements', '/vacations', '/stock-audits',
            '/personnel-movements',
            '/trainings',
            '/my-trainings',
            '/training-reports',
            '/experience-tracker',
        ];

        $userRoutes = ['/dashboard', '/logout', '/my-trainings', '/trainings'];

        $driverRoutes = ['/dashboard', '/logout', '/driver-dashboard', '/my-deliveries'];

        // Cache IDs
        $menuIds = CentralMenu::whereNull('parent_id')->pluck('id', 'name')->toArray();
        $pages = CentralPage::all()->keyBy('route');

        // Todas as roles ativas do banco central
        $roles = CentralRole::where('is_active', true)->pluck('name')->toArray();
        $order = 0;

        foreach ($routeToMenu as $route => $menuName) {
            $page = $pages[$route] ?? null;
            $menuId = $menuIds[$menuName] ?? null;

            if (! $page || ! $menuId) {
                continue;
            }

            $isDropdown = ! in_array($route, $directItems);
            $order++;

            foreach ($roles as $role) {
                $hasPermission = match ($role) {
                    'super_admin' => true,
                    'admin' => true,
                    'support' => in_array($route, $viewOnlyRoutes) || $route === '/logout',
                    'user' => in_array($route, $userRoutes),
                    'drivers' => in_array($route, $driverRoutes),
                    'store_manager' => in_array($route, $viewOnlyRoutes) || $route === '/logout',
                    default => in_array($route, ['/dashboard', '/logout']),
                };

                if (! $hasPermission) {
                    continue;
                }

                CentralMenuPageDefault::updateOrCreate(
                    [
                        'central_menu_id' => $menuId,
                        'central_page_id' => $page->id,
                        'role_slug' => $role,
                    ],
                    [
                        'permission' => true,
                        'order' => $order,
                        'dropdown' => $isDropdown,
                        'lib_menu' => true,
                    ]
                );
            }
        }
    }
}
