<?php

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use Illuminate\Database\Migrations\Migration;

/**
 * Remove da sidebar os dois imports de DRE (realizado + orçado).
 *
 * Motivos:
 *   - Orçado DRE: duplicava `dre_budgets` com o fluxo oficial Budgets →
 *     BudgetToDreProjector. Rota, controller e page React também removidos;
 *     importação excepcional agora só via `php artisan dre:import-budgets`.
 *   - Realizado DRE: continua existindo (rota + controller + page), mas sai
 *     do menu e passa a ser acessado via botão em Fechamentos DRE.
 *
 * down() restaura apenas as entradas de menu (o resto do código de Orçado
 * já foi removido e não volta via migration).
 */
return new class extends Migration
{
    private const ROUTES_TO_UNHOOK = [
        '/dre/imports/actuals',   // sai do menu, página continua acessível
        '/dre/imports/budgets',   // removida por completo
    ];

    public function up(): void
    {
        $pageIds = CentralPage::whereIn('route', self::ROUTES_TO_UNHOOK)->pluck('id');

        if ($pageIds->isNotEmpty()) {
            CentralMenuPageDefault::whereIn('central_page_id', $pageIds)->delete();
        }

        // A página de Orçado some completamente; a de Realizado fica (só sai do menu).
        CentralPage::where('route', '/dre/imports/budgets')->delete();

        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore em ambientes legacy
        }
    }

    public function down(): void
    {
        $menuId = CentralMenu::whereNull('parent_id')->where('name', 'Financeiro')->value('id');
        $listarGroup = CentralPageGroup::where('name', 'Listar')->first();
        $moduleId = \App\Models\CentralModule::where('slug', 'dre')->value('id');

        if (! $menuId) {
            return;
        }

        $pages = [
            ['route' => '/dre/imports/actuals', 'name' => 'Importar Realizado DRE', 'icon' => 'fas fa-file-import'],
            ['route' => '/dre/imports/budgets', 'name' => 'Importar Orçado DRE', 'icon' => 'fas fa-file-import'],
        ];

        $allowedRoles = ['super_admin', 'admin', 'support', 'store_manager'];
        $nextOrder = (CentralMenuPageDefault::max('order') ?? 0) + 1;

        foreach ($pages as $pageDef) {
            $page = CentralPage::updateOrCreate(
                ['route' => $pageDef['route']],
                [
                    'page_name' => $pageDef['name'],
                    'icon' => $pageDef['icon'],
                    'is_public' => false,
                    'is_active' => true,
                    'central_page_group_id' => $listarGroup?->id,
                    'central_module_id' => $moduleId,
                ],
            );

            foreach ($allowedRoles as $roleSlug) {
                CentralMenuPageDefault::updateOrCreate(
                    [
                        'central_menu_id' => $menuId,
                        'central_page_id' => $page->id,
                        'role_slug' => $roleSlug,
                    ],
                    [
                        'permission' => true,
                        'order' => $nextOrder,
                        'dropdown' => true,
                        'lib_menu' => true,
                    ],
                );
            }

            $nextOrder++;
        }

        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
