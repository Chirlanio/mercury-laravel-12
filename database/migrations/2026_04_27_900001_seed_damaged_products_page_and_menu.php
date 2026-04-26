<?php

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use Illuminate\Database\Migrations\Migration;

/**
 * Registra a página /damaged-products no menu central. Separada do seed de
 * permissions/módulo (800001) pra rodar APÓS Fase 3, quando o controller
 * existe (mesma separação que TurnList).
 *
 * Vai sob o menu "Operações" — gestão operacional de inventário entre lojas.
 */
return new class extends Migration
{
    private const PAGE_ROUTE = '/damaged-products';
    private const PAGE_NAME = 'Produtos Avariados';
    private const PAGE_ICON = 'fas fa-triangle-exclamation';
    private const MODULE_SLUG = 'damaged_products';
    private const PARENT_MENU_NAME = 'Operações';

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

        // Roles que veem o item no menu — vendedor (USER) também vê pra
        // cadastrar avarias da loja. Aprovação fica restrita por permission.
        $allowedRoles = ['super_admin', 'admin', 'support', 'user'];
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
