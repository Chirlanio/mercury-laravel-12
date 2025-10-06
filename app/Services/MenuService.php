<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Page;
use App\Models\User;
use App\Models\AccessLevelPage;
use Illuminate\Support\Collection;

class MenuService
{
    /**
     * Mapeamento de rotas antigas (controller/method) para rotas Laravel
     */
    private static function getRouteMapping(): array
    {
        return [
            // Dashboard/Home
            'home/index' => '/dashboard',
            'dashboard/listar' => '/dashboard',

            // Usuários
            'usuarios/listar' => '/users',
            'nivel-acesso/listar' => '/access-levels',
            'users-online/list' => '/users-online',
            'employees/list' => '/employees',

            // Páginas e Menus
            'pagina/listar' => '/pages',
            'menu/listar' => '/menus',

            // Logs
            'activity-logs' => '/activity-logs',

            // Admin
            'editar-conf-email/edit-conf-email' => '/admin/email-settings',
            'editar-form-cad-usuario/edit-form-cad-usuario' => '/admin/login-settings',

            // Funcionários
            'fixed-assets/list' => '/fixed-assets',
            'count-fixed-assets/list' => '/count-fixed-assets',

            // Controle de Jornada
            'material-marketing/list' => '/material-marketing',
            'material-request/list' => '/material-request',
            'overtime-control/list' => '/work-shifts',

            // Estoque
            'ajuste/listar-ajuste' => '/stock-adjustments',
            'transferencia/listar-transf' => '/transfers',
            'relocation/list' => '/relocations',
            'consignments/list' => '/consignments',

            // Delivery
            'delivery/listar' => '/delivery',
            'delivery-routes/list' => '/delivery-routes',
            'situacao-delivery/listar' => '/delivery-status',

            // RH/Pessoas & Cultura
            'gente-gestao/listar' => '/pessoas-cultura',
            'editar-gente-gestao/edit-gente-gestao' => '/pessoas-cultura/edit',
            'jobs-candidates/list' => '/candidates',
            'candidate-files/list' => '/candidate-files',
            'referral/list' => '/referrals',
            'personnel-moviments/list' => '/personnel-movements',
            'vacancy-opening/list' => '/vacancy-openings',
            'medical-certificate/list' => '/medical-certificates',
            'absence-control/list' => '/absence-control',
            'internal-transfer-system/list' => '/internal-transfers',

            // Financeiro
            'order-payments/list' => '/order-payments',
            'supplier/list' => '/suppliers',
            'cost-centers/list' => '/cost-centers',
            'type-payments/list' => '/payment-types',
            'banks/list' => '/banks',
            'tipo-pagamento/listar' => '/payment-types',
            'bandeira/listar' => '/card-brands',
            'motivo-estorno/listar' => '/reversal-reasons',
            'estorno/listar' => '/reversals',
            'autorizacao-resp/listar' => '/authorizations',
            'situacao-order-payment/listar' => '/payment-order-status',
            'travel-expenses/list' => '/travel-expenses',

            // Configurações
            'cor/listar' => '/colors',
            'grupo-pg/listar' => '/page-groups',
            'tipo-pg/listar' => '/page-types',
            'situacao/listar' => '/statuses',
            'situacao-user/listar' => '/user-statuses',
            'situacao-pg/listar' => '/page-statuses',
            'situacao-ajuste/listar' => '/adjustment-statuses',
            'situacao-transf/listar' => '/transfer-statuses',
            'situacao-troca/listar' => '/exchange-statuses',
            'lojas/listar-lojas' => '/stores',
            'cargo/listar-cargo' => '/positions',
            'bairro/listar' => '/neighborhoods',
            'rota/listar' => '/routes',
            'marcas/listar' => '/brands',
            'drivers/list' => '/drivers',
            'cfop/listar' => '/cfops',

            // Qualidade
            'ordem-servico/listar' => '/service-orders',
            'defeitos/listar' => '/defects',
            'detalhes/listar' => '/details',
            'defeito-local/listar' => '/defect-locations',

            // Escola Digital
            'escola-digital/listar-videos' => '/escola-digital',

            // Biblioteca de Processos
            'process-library/list' => '/biblioteca-processos',
            'policies/list' => '/policies',

            // E-commerce
            'ecommerce/list' => '/ecommerce',
            'cupons/list' => '/coupons',
            'returns/list' => '/returns',
            'sales/list' => '/sales',
            'store-goals/list' => '/store-goals',

            // Checklist
            'checklist/list' => '/checklists',
            'checklist-service/list' => '/service-checklists',

            // Outros
            'arquivo/listar' => '/files',
            'order-control/list' => '/order-control',

            // Logout
            'login/logout' => '/logout',
        ];
    }

    /**
     * Converte rota antiga (controller/method) para rota Laravel
     */
    private static function convertRoute(string $oldRoute): string
    {
        $mapping = self::getRouteMapping();
        $normalizedRoute = strtolower(trim($oldRoute, '/'));

        // Se existe mapeamento direto, usa
        if (isset($mapping[$normalizedRoute])) {
            return $mapping[$normalizedRoute];
        }

        // Caso contrário, retorna a rota antiga formatada
        return '/' . $normalizedRoute;
    }
    /**
     * Retorna estrutura de menu baseada no nível de acesso do usuário
     *
     * @param int $userId
     * @return array
     */
    public static function getMenuForUser(int $userId): array
    {
        $user = User::with('accessLevel')->find($userId);

        if (!$user || !$user->accessLevel) {
            return [];
        }

        return self::getMenuForAccessLevel($user->accessLevel->id);
    }

    /**
     * Retorna estrutura de menu para um nível de acesso específico
     * Equivalente ao método itemMenu() do projeto original
     *
     * @param int $accessLevelId
     * @return array
     */
    public static function getMenuForAccessLevel(int $accessLevelId): array
    {
        // Query equivalente ao projeto original:
        // SELECT m.*, p.*, alp.*
        // FROM access_level_pages alp
        // INNER JOIN menus m ON alp.menu_id = m.id
        // INNER JOIN pages p ON alp.page_id = p.id
        // WHERE alp.access_level_id = :access_level_id
        //   AND alp.permission = 1
        //   AND alp.lib_menu = 1
        //   AND m.is_active = 1
        //   AND p.is_active = 1
        // ORDER BY m.order, alp.order

        $menuItems = AccessLevelPage::query()
            ->where('access_level_id', $accessLevelId)
            ->where('permission', true)
            ->where('lib_menu', true)
            ->with(['menu' => function ($query) {
                $query->where('is_active', true)->orderBy('order');
            }, 'page' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('order')
            ->get()
            ->filter(function ($item) {
                // Filtrar apenas itens que têm menu E página ativos
                return $item->menu && $item->page;
            })
            ->groupBy('menu_id');

        $menuStructure = [];

        foreach ($menuItems as $menuId => $items) {
            $menu = $items->first()->menu;

            if (!$menu) {
                continue;
            }

<<<<<<< HEAD
            // Separar items baseado no dropdown
            $dropdownItems = $items->where('dropdown', true);
            $directItems = $items->where('dropdown', false);

            // Mapear função para converter item
            $mapItemFunc = function ($item) {
                $oldRoute = $item->page->menu_controller . '/' . $item->page->menu_method;

                return [
                    'id' => $item->page->id,
                    'name' => $item->page->page_name,
                    'controller' => $item->page->menu_controller,
                    'method' => $item->page->menu_method,
                    'route' => self::convertRoute($oldRoute),
                    'icon' => $item->page->icon,
                    'order' => $item->order,
                    'permission' => $item->permission,
                    'dropdown' => $item->dropdown,
                ];
            };
=======
            // Verificar se algum item tem dropdown = true
            $hasDropdown = $items->where('dropdown', true)->count() > 0;
>>>>>>> d6bd258 (fix menu)

            $menuStructure[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'icon' => $menu->icon,
                'order' => $menu->order,
<<<<<<< HEAD
                'is_dropdown' => $dropdownItems->count() > 0,
                'direct_items' => $directItems->map($mapItemFunc)->sortBy('order')->values()->toArray(),
                'dropdown_items' => $dropdownItems->map($mapItemFunc)->sortBy('order')->values()->toArray(),
=======
                'is_dropdown' => $hasDropdown,
                'items' => $items->map(function ($item) {
                    $oldRoute = $item->page->menu_controller . '/' . $item->page->menu_method;

                    return [
                        'id' => $item->page->id,
                        'name' => $item->page->page_name,
                        'controller' => $item->page->menu_controller,
                        'method' => $item->page->menu_method,
                        'route' => self::convertRoute($oldRoute),
                        'icon' => $item->page->icon,
                        'order' => $item->order,
                        'permission' => $item->permission,
                    ];
                })->sortBy('order')->values()->toArray(),
>>>>>>> d6bd258 (fix menu)
            ];
        }

        // Ordenar menus por ordem e retornar
        return collect($menuStructure)
            ->sortBy('order')
            ->values()
            ->toArray();
    }

    /**
     * Verifica se um usuário tem permissão para acessar uma página específica
     *
     * @param int $userId
     * @param int $pageId
     * @return bool
     */
    public static function canAccessPage(int $userId, int $pageId): bool
    {
        $user = User::with('accessLevel')->find($userId);

        if (!$user || !$user->accessLevel) {
            return false;
        }

        $permission = AccessLevelPage::where('access_level_id', $user->accessLevel->id)
            ->where('page_id', $pageId)
            ->where('permission', true)
            ->first();

        return $permission !== null;
    }

    /**
     * Verifica se um usuário tem permissão para acessar uma rota (controller + method)
     *
     * @param int $userId
     * @param string $controller
     * @param string $method
     * @return bool
     */
    public static function canAccessRoute(int $userId, string $controller, string $method): bool
    {
        $page = Page::where('menu_controller', $controller)
            ->where('menu_method', $method)
            ->first();

        if (!$page) {
            return false;
        }

        return self::canAccessPage($userId, $page->id);
    }

    /**
     * Retorna todos os menus disponíveis (para administração)
     *
     * @return Collection
     */
    public static function getAllMenus(): Collection
    {
        return Menu::active()->ordered()->get();
    }

    /**
     * Retorna todas as páginas de um menu para um nível de acesso específico
     *
     * @param int $accessLevelId
     * @param int $menuId
     * @return Collection
     */
    public static function getPagesForMenu(int $accessLevelId, int $menuId): Collection
    {
        return AccessLevelPage::where('access_level_id', $accessLevelId)
            ->where('menu_id', $menuId)
            ->where('permission', true)
            ->with('page')
            ->orderBy('order')
            ->get()
            ->pluck('page')
            ->filter(); // Remove nulls
    }
}
