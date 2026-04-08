<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Seeds the Laravel route-based pages and their access_level_pages permissions.
 * This ensures new tenants get full menu navigation for all implemented modules.
 */
class LaravelPagesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Ensure required menus exist (RH, Operações may not exist in base MenuSeeder)
        $this->ensureMenusExist($now);

        // Create pages with Laravel routes
        $pages = $this->getPages();

        foreach ($pages as $page) {
            DB::table('pages')->updateOrInsert(
                ['route' => $page['route']],
                [
                    'controller' => $page['controller'] ?? 'Laravel',
                    'method' => $page['method'] ?? 'index',
                    'menu_controller' => $page['menu_controller'] ?? '',
                    'menu_method' => $page['menu_method'] ?? '',
                    'page_name' => $page['page_name'],
                    'route' => $page['route'],
                    'notes' => $page['notes'] ?? '',
                    'is_public' => false,
                    'icon' => $page['icon'] ?? '',
                    'page_group_id' => $page['page_group_id'] ?? 1,
                    'is_active' => true,
                    'created_at' => $now,
                ]
            );
        }

        // Assign all pages to Super Administrador (access_level_id = 1)
        $this->assignPagesToSuperAdmin();
    }

    protected function ensureMenusExist(Carbon $now): void
    {
        $menus = [
            ['name' => 'RH', 'icon' => 'fas fa-users-cog', 'order' => 5],
            ['name' => 'Operações', 'icon' => 'fas fa-cogs', 'order' => 6],
        ];

        foreach ($menus as $menu) {
            DB::table('menus')->updateOrInsert(
                ['name' => $menu['name']],
                [
                    'icon' => $menu['icon'],
                    'order' => $menu['order'],
                    'is_active' => true,
                    'created_at' => $now,
                ]
            );
        }
    }

    protected function getPages(): array
    {
        return [
            // Dashboard
            ['route' => '/dashboard', 'page_name' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'controller' => 'Dashboard', 'method' => 'index', 'menu_controller' => 'dashboard', 'menu_method' => 'listar'],

            // Usuários
            ['route' => '/users', 'page_name' => 'Usuários', 'icon' => 'fas fa-users', 'controller' => 'Users', 'method' => 'index', 'menu_controller' => 'users', 'menu_method' => 'index'],

            // Funcionários
            ['route' => '/employees', 'page_name' => 'Funcionários', 'icon' => 'fas fa-id-badge', 'controller' => 'Employees', 'method' => 'index', 'menu_controller' => 'employees', 'menu_method' => 'index'],

            // Vendas
            ['route' => '/sales', 'page_name' => 'Vendas', 'icon' => 'fas fa-chart-line', 'controller' => 'Sales', 'method' => 'index', 'menu_controller' => 'sales', 'menu_method' => 'list'],

            // Produtos
            ['route' => '/products', 'page_name' => 'Produtos', 'icon' => 'fas fa-box', 'controller' => 'Products', 'method' => 'index', 'menu_controller' => 'products', 'menu_method' => 'list'],

            // Lojas
            ['route' => '/stores', 'page_name' => 'Lojas', 'icon' => 'fas fa-store', 'controller' => 'Stores', 'method' => 'index', 'menu_controller' => 'stores', 'menu_method' => 'index'],

            // Turnos
            ['route' => '/work-shifts', 'page_name' => 'Turnos', 'icon' => 'fas fa-clock', 'controller' => 'WorkShifts', 'method' => 'index', 'menu_controller' => 'work-shifts', 'menu_method' => 'list'],

            // Escalas de Trabalho
            ['route' => '/work-schedules', 'page_name' => 'Escalas de Trabalho', 'icon' => 'fas fa-calendar-alt', 'controller' => 'WorkSchedules', 'method' => 'index', 'menu_controller' => 'work-schedules', 'menu_method' => 'list'],

            // Usuários Online
            ['route' => '/user-sessions', 'page_name' => 'Usuários Online', 'icon' => 'fas fa-wifi', 'controller' => 'UserSessions', 'method' => 'index', 'menu_controller' => 'users-online', 'menu_method' => 'list'],

            // Transferências
            ['route' => '/transfers', 'page_name' => 'Transferências', 'icon' => 'fas fa-exchange-alt', 'controller' => 'Transfers', 'method' => 'index', 'menu_controller' => 'transfers', 'menu_method' => 'list'],

            // Ajustes de Estoque
            ['route' => '/stock-adjustments', 'page_name' => 'Ajustes de Estoque', 'icon' => 'fas fa-clipboard-check', 'controller' => 'StockAdjustments', 'method' => 'index', 'menu_controller' => 'stock-adjustments', 'menu_method' => 'list'],

            // Ordens de Pagamento
            ['route' => '/order-payments', 'page_name' => 'Ordens de Pagamento', 'icon' => 'fas fa-money-bill-wave', 'controller' => 'OrderPayments', 'method' => 'index', 'menu_controller' => 'order-payments', 'menu_method' => 'list'],

            // Fornecedores
            ['route' => '/suppliers', 'page_name' => 'Fornecedores', 'icon' => 'fas fa-truck', 'controller' => 'Suppliers', 'method' => 'index', 'menu_controller' => 'supplier', 'menu_method' => 'list'],

            // Atestados Médicos
            ['route' => '/medical-certificates', 'page_name' => 'Atestados Médicos', 'icon' => 'fas fa-file-medical', 'controller' => 'MedicalCertificates', 'method' => 'index', 'menu_controller' => 'medical-certificate', 'menu_method' => 'list'],

            // Faltas
            ['route' => '/absences', 'page_name' => 'Faltas', 'icon' => 'fas fa-user-times', 'controller' => 'Absences', 'method' => 'index', 'menu_controller' => 'absence-control', 'menu_method' => 'list'],

            // Horas Extras
            ['route' => '/overtime-records', 'page_name' => 'Horas Extras', 'icon' => 'fas fa-business-time', 'controller' => 'OvertimeRecords', 'method' => 'index', 'menu_controller' => 'overtime-control', 'menu_method' => 'list'],

            // Checklists
            ['route' => '/checklists', 'page_name' => 'Checklists', 'icon' => 'fas fa-tasks', 'controller' => 'Checklists', 'method' => 'index', 'menu_controller' => 'checklist', 'menu_method' => 'list'],

            // Logs de Atividade
            ['route' => '/activity-logs', 'page_name' => 'Logs de Atividade', 'icon' => 'fas fa-history', 'controller' => 'ActivityLogs', 'method' => 'index', 'menu_controller' => 'activity-logs', 'menu_method' => 'index'],

            // Configurações
            ['route' => '/config/positions', 'page_name' => 'Cargos', 'icon' => 'fas fa-cog', 'controller' => 'ConfigPositions', 'method' => 'index', 'menu_controller' => 'config-positions', 'menu_method' => 'index'],
            ['route' => '/config/sectors', 'page_name' => 'Setores', 'icon' => 'fas fa-cog', 'controller' => 'ConfigSectors', 'method' => 'index', 'menu_controller' => 'config-sectors', 'menu_method' => 'index'],
            ['route' => '/config/position-levels', 'page_name' => 'Níveis de Cargo', 'icon' => 'fas fa-cog', 'controller' => 'ConfigPositionLevels', 'method' => 'index', 'menu_controller' => 'config-position-levels', 'menu_method' => 'index'],
            ['route' => '/config/genders', 'page_name' => 'Gêneros', 'icon' => 'fas fa-cog', 'controller' => 'ConfigGenders', 'method' => 'index', 'menu_controller' => 'config-genders', 'menu_method' => 'index'],
            ['route' => '/config/education-levels', 'page_name' => 'Escolaridades', 'icon' => 'fas fa-cog', 'controller' => 'ConfigEducationLevels', 'method' => 'index', 'menu_controller' => 'config-education-levels', 'menu_method' => 'index'],
            ['route' => '/config/employee-statuses', 'page_name' => 'Situações de Funcionário', 'icon' => 'fas fa-cog', 'controller' => 'ConfigEmployeeStatuses', 'method' => 'index', 'menu_controller' => 'config-employee-statuses', 'menu_method' => 'index'],
            ['route' => '/config/networks', 'page_name' => 'Redes', 'icon' => 'fas fa-cog', 'controller' => 'ConfigNetworks', 'method' => 'index', 'menu_controller' => 'config-networks', 'menu_method' => 'index'],
            ['route' => '/config/managers', 'page_name' => 'Gestores', 'icon' => 'fas fa-cog', 'controller' => 'ConfigManagers', 'method' => 'index', 'menu_controller' => 'config-managers', 'menu_method' => 'index'],
            ['route' => '/config/banks', 'page_name' => 'Bancos', 'icon' => 'fas fa-cog', 'controller' => 'ConfigBanks', 'method' => 'index', 'menu_controller' => 'banks', 'menu_method' => 'list'],
            ['route' => '/config/cost-centers', 'page_name' => 'Centros de Custo', 'icon' => 'fas fa-cog', 'controller' => 'ConfigCostCenters', 'method' => 'index', 'menu_controller' => 'cost-centers', 'menu_method' => 'list'],
            ['route' => '/config/payment-types', 'page_name' => 'Tipos de Pagamento', 'icon' => 'fas fa-cog', 'controller' => 'ConfigPaymentTypes', 'method' => 'index', 'menu_controller' => 'type-payments', 'menu_method' => 'list'],
            ['route' => '/config/drivers', 'page_name' => 'Motoristas', 'icon' => 'fas fa-cog', 'controller' => 'ConfigDrivers', 'method' => 'index', 'menu_controller' => 'drivers', 'menu_method' => 'list'],

            // Configurações - Cadastro de Produtos
            ['route' => '/config/product-brands', 'page_name' => 'Marcas de Produto', 'icon' => 'fas fa-cog', 'controller' => 'ConfigProductBrands', 'method' => 'index', 'menu_controller' => 'config-product-brands', 'menu_method' => 'index'],
            ['route' => '/config/product-categories', 'page_name' => 'Categorias de Produto', 'icon' => 'fas fa-cog', 'controller' => 'ConfigProductCategories', 'method' => 'index', 'menu_controller' => 'config-product-categories', 'menu_method' => 'index'],
            ['route' => '/config/product-collections', 'page_name' => 'Coleções de Produto', 'icon' => 'fas fa-cog', 'controller' => 'ConfigProductCollections', 'method' => 'index', 'menu_controller' => 'config-product-collections', 'menu_method' => 'index'],
            ['route' => '/config/product-subcollections', 'page_name' => 'Subcoleções de Produto', 'icon' => 'fas fa-cog', 'controller' => 'ConfigProductSubcollections', 'method' => 'index', 'menu_controller' => 'config-product-subcollections', 'menu_method' => 'index'],
            ['route' => '/config/product-colors', 'page_name' => 'Cores de Produto', 'icon' => 'fas fa-cog', 'controller' => 'ConfigProductColors', 'method' => 'index', 'menu_controller' => 'config-product-colors', 'menu_method' => 'index'],
            ['route' => '/config/product-materials', 'page_name' => 'Materiais de Produto', 'icon' => 'fas fa-cog', 'controller' => 'ConfigProductMaterials', 'method' => 'index', 'menu_controller' => 'config-product-materials', 'menu_method' => 'index'],
            ['route' => '/config/product-sizes', 'page_name' => 'Tamanhos de Produto', 'icon' => 'fas fa-cog', 'controller' => 'ConfigProductSizes', 'method' => 'index', 'menu_controller' => 'config-product-sizes', 'menu_method' => 'index'],
            ['route' => '/config/product-article-complements', 'page_name' => 'Complementos de Artigo', 'icon' => 'fas fa-cog', 'controller' => 'ConfigProductArticleComplements', 'method' => 'index', 'menu_controller' => 'config-product-article-complements', 'menu_method' => 'index'],

            // Integrações
            ['route' => '/integrations', 'page_name' => 'Integrações', 'icon' => 'fas fa-link', 'controller' => 'Integrations', 'method' => 'index', 'menu_controller' => 'integrations', 'menu_method' => 'index'],

            // Sair
            ['route' => '/logout', 'page_name' => 'Sair', 'icon' => 'fas fa-sign-out-alt', 'controller' => 'Logout', 'method' => 'logout', 'menu_controller' => 'login', 'menu_method' => 'logout'],
        ];
    }

    protected function assignPagesToSuperAdmin(): void
    {
        // Menu mapping: route => menu_name
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
            '/stock-adjustments' => 'Operações',
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
            '/config/product-brands' => 'Configurações',
            '/config/product-categories' => 'Configurações',
            '/config/product-collections' => 'Configurações',
            '/config/product-subcollections' => 'Configurações',
            '/config/product-colors' => 'Configurações',
            '/config/product-materials' => 'Configurações',
            '/config/product-sizes' => 'Configurações',
            '/config/product-article-complements' => 'Configurações',
            '/integrations' => 'Configurações',
            '/logout' => 'Sair',
        ];

        // Direct items (not in dropdown)
        $directItems = ['/dashboard', '/logout'];

        // Get all access levels to assign
        $accessLevelIds = DB::table('access_levels')->pluck('id')->toArray();

        // Cache menu IDs by name
        $menuIds = DB::table('menus')->pluck('id', 'name')->toArray();

        foreach ($routeToMenu as $route => $menuName) {
            $page = DB::table('pages')->where('route', $route)->first();
            $menuId = $menuIds[$menuName] ?? null;

            if (!$page || !$menuId) {
                continue;
            }

            $isDropdown = !in_array($route, $directItems);

            // Assign to Super Administrador (ID 1) with full permissions
            DB::table('access_level_pages')->updateOrInsert(
                ['access_level_id' => 1, 'page_id' => $page->id],
                [
                    'menu_id' => $menuId,
                    'permission' => true,
                    'lib_menu' => true,
                    'dropdown' => $isDropdown,
                    'order' => $page->id,
                ]
            );

            // Also assign to Administrador (ID 2) if exists
            if (in_array(2, $accessLevelIds)) {
                DB::table('access_level_pages')->updateOrInsert(
                    ['access_level_id' => 2, 'page_id' => $page->id],
                    [
                        'menu_id' => $menuId,
                        'permission' => true,
                        'lib_menu' => true,
                        'dropdown' => $isDropdown,
                        'order' => $page->id,
                    ]
                );
            }
        }
    }
}
