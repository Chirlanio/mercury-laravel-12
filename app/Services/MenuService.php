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
            'employees/index' => '/employees',

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
            'stores/index' => '/stores',
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

        // Simplificado para sempre usar a mesma lógica correta para todos os usuários.
        return self::getMenuForAccessLevel($user->accessLevel->id);
    }

    /**
     * Retorna estrutura de menu para um nível de acesso específico, ignorando parent_id
     * e usando access_level_pages como única fonte de verdade.
     *
     * @param int $accessLevelId
     * @return array
     */
    public static function getMenuForAccessLevel(int $accessLevelId): array
    {
        // 1. Busca todos os itens de página/menu permitidos, visíveis no menu, para o nível de acesso.
        $menuItems = AccessLevelPage::query()
            ->where('access_level_id', $accessLevelId)
            ->where('permission', true)
            ->where('lib_menu', true) // Garante que só itens marcados para menu apareçam
            ->with(['menu' => fn ($q) => $q->where('is_active', true), 'page' => fn ($q) => $q->where('is_active', true)])
            ->get()
            // 2. Filtra itens que não tenham um menu ou página válida associada.
            ->filter(fn ($item) => $item->menu && $item->page);

        // 3. Agrupa as páginas encontradas pelo ID do menu pai.
        $menuItemsByMenuId = $menuItems->groupBy('menu_id');

        $menuStructure = [];

        // 4. Itera sobre os grupos para construir a estrutura final.
        foreach ($menuItemsByMenuId as $menuId => $items) {
            $menu = $items->first()->menu;
            if (!$menu) continue;

            // 5. Particiona os itens em 'dropdown' (true) e 'direct' (false).
            [$dropdownItems, $directItems] = $items->partition(fn ($item) => $item->dropdown);

            $mapItemFunc = function ($item) {
                // Usa o campo route diretamente se disponível, senão usa fallback para conversão legada
                $route = $item->page->route;
                if (empty($route)) {
                    $route = self::convertRoute($item->page->menu_controller . '/' . $item->page->menu_method);
                }

                return [
                    'id' => $item->page->id,
                    'name' => $item->page->page_name,
                    'route' => $route,
                    'icon' => $item->page->icon,
                    'order' => $item->order,
                ];
            };

            // Adiciona à estrutura somente se houver itens
            if ($directItems->isNotEmpty() || $dropdownItems->isNotEmpty()) {
                $menuStructure[] = [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'icon' => $menu->icon,
                    'order' => $menu->order,
                    'direct_items' => $directItems->map($mapItemFunc)->sortBy('order')->values()->all(),
                    'dropdown_items' => $dropdownItems->map($mapItemFunc)->sortBy('order')->values()->all(),
                ];
            }
        }

        // 7. Ordena os menus principais e retorna a estrutura final.
        return collect($menuStructure)->sortBy('order')->values()->toArray();
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
