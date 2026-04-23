<?php

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use Illuminate\Database\Migrations\Migration;

/**
 * Registra a página principal `/coupons` em central_pages +
 * central_menu_page_defaults (menu "E-commerce"). Separada da
 * migration 700001 porque foi adicionada após a criação do
 * CouponController (Fase 3), mantendo a 700001 idempotente.
 *
 * super_admin, admin e support vêem. support (store-scoped) só
 * vê cupons da própria loja + os que criou (store scoping aplicado
 * no controller).
 */
return new class extends Migration
{
    private const PAGE_ROUTE = '/coupons';
    private const PAGE_NAME = 'Cupons';
    private const PAGE_ICON = 'fas fa-ticket';
    private const PARENT_MENU_NAME = 'E-commerce';
    private const MODULE_SLUG = 'coupons';

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

        $allowedRoles = ['super_admin', 'admin', 'support'];
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

    private function clearMenuCache(): void
    {
        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // Silent — cache pode não estar acessível em migrate:fresh
        }
    }
};
