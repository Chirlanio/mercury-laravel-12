<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds chat and helpdesk modules: central_modules, central_permissions,
 * central_role_permissions, central_pages, central_menus,
 * central_menu_page_defaults, and tenant_modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->seedModules();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->seedMenus();
        $this->seedPages();
        $this->seedMenuPageDefaults();
        $this->addModulesToPlans();
    }

    public function down(): void
    {
        // Remove plan module assignments
        DB::table('tenant_modules')
            ->whereIn('module_slug', ['chat', 'helpdesk'])
            ->delete();

        // Remove menu_page_defaults for chat/helpdesk pages
        $pageIds = CentralPage::whereIn('route', [
            '/chat', '/helpdesk', '/helpdesk-reports',
        ])->pluck('id')->toArray();

        if (! empty($pageIds)) {
            CentralMenuPageDefault::whereIn('central_page_id', $pageIds)->delete();
            CentralPage::whereIn('id', $pageIds)->delete();
        }

        // Remove menus
        CentralMenu::whereIn('name', ['Chat', 'Helpdesk'])->delete();

        // Remove role-permission assignments
        $slugs = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, 'chat.') || str_starts_with($p->value, 'helpdesk.'))
            ->pluck('value')
            ->toArray();

        $permIds = CentralPermission::whereIn('slug', $slugs)->pluck('id')->toArray();
        if (! empty($permIds)) {
            DB::table('central_role_permissions')->whereIn('central_permission_id', $permIds)->delete();
        }

        // Remove permissions
        CentralPermission::whereIn('slug', $slugs)->delete();

        // Remove modules
        CentralModule::whereIn('slug', ['chat', 'helpdesk'])->delete();
    }

    private function seedModules(): void
    {
        $modules = config('modules', []);

        foreach (['chat', 'helpdesk'] as $slug) {
            if (! isset($modules[$slug])) {
                continue;
            }

            $def = $modules[$slug];
            CentralModule::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $def['name'],
                    'description' => $def['description'] ?? null,
                    'icon' => $def['icon'] ?? null,
                    'routes' => $def['routes'] ?? [],
                    'dependencies' => $def['dependencies'] ?? null,
                    'is_active' => true,
                    'sort_order' => CentralModule::max('sort_order') + 1,
                ]
            );
        }
    }

    private function seedPermissions(): void
    {
        $modulePerms = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, 'chat.') || str_starts_with($p->value, 'helpdesk.'));

        foreach ($modulePerms as $perm) {
            $group = explode('.', $perm->value)[0];
            CentralPermission::updateOrCreate(
                ['slug' => $perm->value],
                [
                    'label' => $perm->label(),
                    'description' => $perm->description(),
                    'group' => $group,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedRolePermissions(): void
    {
        $roles = CentralRole::all()->keyBy('name');
        $permissions = CentralPermission::all()->keyBy('slug');

        foreach (Role::cases() as $roleEnum) {
            $role = $roles[$roleEnum->value] ?? null;
            if (! $role) {
                continue;
            }

            // Filter only chat/helpdesk permissions from this role's permission list
            $permSlugs = collect($roleEnum->permissions())
                ->filter(fn ($slug) => str_starts_with($slug, 'chat.') || str_starts_with($slug, 'helpdesk.'))
                ->toArray();

            $permIds = $permissions->filter(fn ($p) => in_array($p->slug, $permSlugs))->pluck('id')->toArray();

            $role->permissions()->syncWithoutDetaching($permIds);
        }
    }

    private function seedMenus(): void
    {
        $maxOrder = CentralMenu::whereNull('parent_id')->max('order') ?? 0;

        // Create Chat menu
        CentralMenu::updateOrCreate(
            ['name' => 'Chat', 'parent_id' => null],
            [
                'icon' => 'fas fa-comments',
                'order' => $maxOrder + 1,
                'is_active' => true,
                'type' => 'main',
            ]
        );

        // Create Helpdesk menu
        CentralMenu::updateOrCreate(
            ['name' => 'Helpdesk', 'parent_id' => null],
            [
                'icon' => 'fas fa-headset',
                'order' => $maxOrder + 2,
                'is_active' => true,
                'type' => 'main',
            ]
        );
    }

    private function seedPages(): void
    {
        $listarGroup = CentralPageGroup::where('name', 'Listar')->first();
        $moduleIds = CentralModule::pluck('id', 'slug')->toArray();

        // Reports tab is now part of the main Helpdesk page (/helpdesk?tab=reports),
        // so /helpdesk-reports is not registered as a separate menu entry anymore.
        $pages = [
            [
                'route' => '/chat',
                'page_name' => 'Chat',
                'icon' => 'fas fa-comments',
                'module_slug' => 'chat',
            ],
            [
                'route' => '/helpdesk',
                'page_name' => 'Helpdesk',
                'icon' => 'fas fa-headset',
                'module_slug' => 'helpdesk',
            ],
        ];

        foreach ($pages as $page) {
            CentralPage::updateOrCreate(
                ['route' => $page['route']],
                [
                    'page_name' => $page['page_name'],
                    'icon' => $page['icon'],
                    'is_public' => false,
                    'is_active' => true,
                    'central_page_group_id' => $listarGroup?->id,
                    'central_module_id' => $moduleIds[$page['module_slug']] ?? null,
                ]
            );
        }
    }

    private function seedMenuPageDefaults(): void
    {
        // Route → menu mapping
        $routeToMenu = [
            '/chat' => 'Chat',
            '/helpdesk' => 'Helpdesk',
        ];

        // Role access matrix
        $routeAccess = [
            '/chat' => ['super_admin', 'admin', 'support', 'user', 'drivers'],
            '/helpdesk' => ['super_admin', 'admin', 'support', 'user', 'drivers'],
        ];

        $menuIds = CentralMenu::whereNull('parent_id')->pluck('id', 'name')->toArray();
        $pages = CentralPage::whereIn('route', array_keys($routeToMenu))->get()->keyBy('route');
        $maxOrder = CentralMenuPageDefault::max('order') ?? 0;

        foreach ($routeToMenu as $route => $menuName) {
            $page = $pages[$route] ?? null;
            $menuId = $menuIds[$menuName] ?? null;

            if (! $page || ! $menuId) {
                continue;
            }

            $maxOrder++;
            $allowedRoles = $routeAccess[$route] ?? [];

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
                        'dropdown' => false, // Chat e Helpdesk são menus principais (sem dropdown)
                        'lib_menu' => true,
                    ]
                );
            }
        }
    }

    private function addModulesToPlans(): void
    {
        $plans = DB::table('tenant_plans')->get();

        foreach ($plans as $plan) {
            foreach (['chat', 'helpdesk'] as $moduleSlug) {
                $exists = DB::table('tenant_modules')
                    ->where('plan_id', $plan->id)
                    ->where('module_slug', $moduleSlug)
                    ->exists();

                if (! $exists) {
                    // Chat disponível em todos os planos; Helpdesk apenas Professional/Enterprise
                    $isEnabled = $moduleSlug === 'chat'
                        ? true
                        : in_array($plan->slug, ['professional', 'enterprise']);

                    DB::table('tenant_modules')->insert([
                        'plan_id' => $plan->id,
                        'module_slug' => $moduleSlug,
                        'is_enabled' => $isEnabled,
                    ]);
                }
            }
        }
    }
};
