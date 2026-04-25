<?php

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use Illuminate\Database\Migrations\Migration;

/**
 * Registra a página /travel-expenses no menu central. Separada do seed de
 * permissions/módulo (200001) pra rodar APÓS a Fase 3, quando o controller
 * existe — assim evitamos menu fantasma levando a 404 caso a Fase 3 não
 * fosse concluída.
 *
 * Mesmo padrão de Reversals/Coupons/Returns: encaixa sob o menu "Financeiro"
 * existente, sem criar novo top-level.
 */
return new class extends Migration
{
    private const PAGE_ROUTE = '/travel-expenses';
    private const PAGE_NAME = 'Verbas de Viagem';
    private const PAGE_ICON = 'fas fa-plane-departure';
    private const MODULE_SLUG = 'travel_expenses';
    private const PARENT_MENU_NAME = 'Financeiro';

    public function up(): void
    {
        $this->seedPage();
        $this->seedMenuPageDefaults();
    }

    public function down(): void
    {
        $page = CentralPage::where('route', self::PAGE_ROUTE)->first();
        if ($page) {
            CentralMenuPageDefault::where('central_page_id', $page->id)->delete();
            $page->delete();
        }
    }

    private function seedPage(): void
    {
        $listarGroup = CentralPageGroup::where('name', 'Listar')->first();
        $moduleId = CentralModule::where('slug', self::MODULE_SLUG)->value('id');

        CentralPage::updateOrCreate(
            ['route' => self::PAGE_ROUTE],
            [
                'page_name' => self::PAGE_NAME,
                'icon' => self::PAGE_ICON,
                'is_public' => false,
                'is_active' => true,
                'central_page_group_id' => $listarGroup?->id,
                'central_module_id' => $moduleId,
            ]
        );
    }

    private function seedMenuPageDefaults(): void
    {
        $menuId = CentralMenu::whereNull('parent_id')
            ->where('name', self::PARENT_MENU_NAME)
            ->value('id');

        $page = CentralPage::where('route', self::PAGE_ROUTE)->first();

        if (! $menuId || ! $page) {
            return;
        }

        // Roles que veem o menu: padrão idêntico aos demais módulos
        // financeiros — Admin/Support sempre veem; Finance porque opera o
        // ciclo de aprovação; User pra solicitar suas próprias.
        $allowedRoles = ['super_admin', 'admin', 'support', 'finance', 'user'];
        $maxOrder = (CentralMenuPageDefault::max('order') ?? 0) + 1;

        foreach ($allowedRoles as $roleSlug) {
            CentralMenuPageDefault::updateOrCreate(
                [
                    'central_menu_id' => $menuId,
                    'central_page_id' => $page->id,
                    'role_slug' => $roleSlug,
                ],
                [
                    'permission' => true,
                    'order' => $maxOrder,
                    'dropdown' => true,
                    'lib_menu' => true,
                ]
            );
        }
    }
};
