<?php

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds four admin sub-entries to the existing "Helpdesk" sidebar menu:
 *
 *   - Configurações       → /helpdesk/admin/department-settings
 *   - Templates           → /helpdesk/admin/intake-templates
 *   - Base de Conhecimento → /helpdesk/admin/articles
 *   - Permissões          → /helpdesk/admin/permissions
 *
 * Role gates in the sidebar mirror the route middleware:
 *   - First 3 visible to super_admin, admin, support
 *     (backed by Permission::MANAGE_HD_DEPARTMENTS)
 *   - Permissões visible only to super_admin and admin
 *     (backed by Permission::MANAGE_HD_PERMISSIONS)
 *
 * The sidebar only enforces role + module membership — actual access
 * control lives on the route middleware, so a Support user who somehow
 * sees a non-authorized link would get a 403 from the backend. Keeping
 * sidebar gates in sync with middleware gates avoids that.
 *
 * CentralMenuResolver caches menu output for 5 minutes per role/tenant.
 * After running this migration, either wait or clear via
 *   php artisan cache:forget helpdesk.menu.*
 */
return new class extends Migration
{
    public function up(): void
    {
        $menu = CentralMenu::where('name', 'Helpdesk')->first();
        if (! $menu) {
            // Base helpdesk menu doesn't exist yet — nothing to graft under.
            return;
        }

        $moduleId = CentralModule::where('slug', 'helpdesk')->value('id');

        $pages = [
            [
                'route' => '/helpdesk/admin/department-settings',
                'page_name' => 'Configurações',
                'icon' => 'fas fa-cog',
                'roles' => ['super_admin', 'admin', 'support'],
            ],
            [
                'route' => '/helpdesk/admin/intake-templates',
                'page_name' => 'Templates de Intake',
                'icon' => 'fas fa-file-alt',
                'roles' => ['super_admin', 'admin', 'support'],
            ],
            [
                'route' => '/helpdesk/admin/articles',
                'page_name' => 'Base de Conhecimento',
                'icon' => 'fas fa-book',
                'roles' => ['super_admin', 'admin', 'support'],
            ],
            [
                'route' => '/helpdesk/admin/permissions',
                'page_name' => 'Permissões',
                'icon' => 'fas fa-lock',
                'roles' => ['super_admin', 'admin'],
            ],
        ];

        $baseOrder = CentralMenuPageDefault::where('central_menu_id', $menu->id)->max('order') ?? 0;

        foreach ($pages as $offset => $data) {
            $page = CentralPage::updateOrCreate(
                ['route' => $data['route']],
                [
                    'page_name' => $data['page_name'],
                    'icon' => $data['icon'],
                    'is_public' => false,
                    'is_active' => true,
                    'central_module_id' => $moduleId,
                ],
            );

            foreach ($data['roles'] as $role) {
                CentralMenuPageDefault::updateOrCreate(
                    [
                        'central_menu_id' => $menu->id,
                        'central_page_id' => $page->id,
                        'role_slug' => $role,
                    ],
                    [
                        'permission' => true,
                        // Keep each new entry after the existing helpdesk
                        // items, preserving their relative order within
                        // the dropdown.
                        'order' => $baseOrder + 10 + $offset,
                        'dropdown' => true,
                        'lib_menu' => true,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        $routes = [
            '/helpdesk/admin/department-settings',
            '/helpdesk/admin/intake-templates',
            '/helpdesk/admin/articles',
            '/helpdesk/admin/permissions',
        ];

        $pageIds = CentralPage::whereIn('route', $routes)->pluck('id')->toArray();

        if (empty($pageIds)) {
            return;
        }

        CentralMenuPageDefault::whereIn('central_page_id', $pageIds)->delete();
        CentralPage::whereIn('id', $pageIds)->delete();
    }
};
