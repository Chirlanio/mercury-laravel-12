<?php

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use Illuminate\Database\Migrations\Migration;

/**
 * Registra a página `/customers` em central_pages +
 * central_menu_page_defaults (menu "Comercial"). Separada da 200001
 * (permissions+módulo) para rodar só após o CustomerController existir
 * — evita menu "fantasma" antes da Fase 5b.
 *
 * Visibilidade:
 *  - super_admin, admin: acesso completo
 *  - support: lista e exporta
 *  - user (vendedor): lista (precisa pro autocomplete do consignment)
 */
return new class extends Migration
{
    private const PAGE_ROUTE = '/customers';
    private const PAGE_NAME = 'Clientes';
    private const PAGE_ICON = 'fas fa-users';
    private const PARENT_MENU_NAME = 'Comercial';
    private const MODULE_SLUG = 'customers';

    public function up(): void
    {
        $this->seedPage();
        $this->seedMenuPageDefaults();
        $this->clearMenuCache();
    }

    public function down(): void
    {
        $pageIds = CentralPage::whereIn('route', [self::PAGE_ROUTE])->pluck('id')->toArray();
        if (! empty($pageIds)) {
            CentralMenuPageDefault::whereIn('central_page_id', $pageIds)->delete();
            CentralPage::whereIn('id', $pageIds)->delete();
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
            ],
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
                ],
            );
        }
    }

    private function clearMenuCache(): void
    {
        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
        } catch (\Throwable) {
            // Silent em migrate:fresh
        }
    }
};
