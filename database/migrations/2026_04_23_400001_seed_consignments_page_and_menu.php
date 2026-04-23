<?php

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use Illuminate\Database\Migrations\Migration;

/**
 * Registra a página principal `/consignments` em central_pages +
 * central_menu_page_defaults (menu "Comercial"). Separada da migration
 * 200001_seed_consignments_module_and_permissions para manter aquela
 * idempotente e rodar só após o ConsignmentController existir (Fase 2a),
 * evitando menu "fantasma".
 *
 * Visibilidade no sidebar:
 *  - super_admin + admin: vêem todas as lojas
 *  - support: vê apenas da sua loja (scope automático)
 *  - user (vendedor): vê apenas da sua loja (scope automático)
 */
return new class extends Migration
{
    private const PAGE_ROUTE = '/consignments';
    private const PAGE_NAME = 'Consignações';
    private const PAGE_ICON = 'fas fa-box-open';
    private const PARENT_MENU_NAME = 'Comercial';
    private const MODULE_SLUG = 'consignments';

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

        // Consignações são operação comercial — vendedor (user), suporte
        // e admins acessam. FINANCE/ACCOUNTING não acessam por padrão.
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

    private function clearMenuCache(): void
    {
        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // Silent — cache pode não estar acessível em migrate:fresh
        }
    }
};
