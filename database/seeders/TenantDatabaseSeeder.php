<?php

namespace Database\Seeders;

use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder for new tenant databases.
 * Seeds only the essential reference data — no company-specific data.
 *
 * Navigation (menus, pages, page_groups, access_level_pages) is now sourced
 * from central tables when available, falling back to static seeders for
 * backward compatibility.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Reference/lookup data (generic, no FK dependencies)
            EmailConfigurationSeeder::class,
            ColorThemeSeeder::class,
            StatusSeeder::class,
            PageStatusSeeder::class,
            EmploymentRelationshipSeeder::class,
            EducationLevelSeeder::class,
            GenderSeeder::class,
            EmployeeStatusSeeder::class,
            EmployeeEventTypeSeeder::class,
            TypeMovimentSeeder::class,
            PositionLevelSeeder::class,

            // DRE — estrutura gerencial executiva (20 linhas fixas)
            DreManagementLineSeeder::class,

            // Access levels (needed before navigation provisioning)
            AdditionalAccessLevelsSeeder::class,

            // Store goals commission config
            PercentageAwardSeeder::class,
        ]);

        // Navigation: use central tables if populated, otherwise fall back to static seeders
        if ($this->hasCentralNavigation()) {
            $this->provisionNavigationFromCentral();
        } else {
            $this->call([
                MenuSeeder::class,
                PageGroupSeeder::class,
                PageSeeder::class,
                AccessLevelPageSeeder::class,
                LaravelPagesSeeder::class,
            ]);
        }

        // Seed generic sectors without manager FK references
        $this->seedGenericSectors();
    }

    /**
     * Check if central navigation tables have data.
     */
    protected function hasCentralNavigation(): bool
    {
        try {
            return CentralMenu::on('mysql')->count() > 0
                && CentralPage::on('mysql')->count() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Provision tenant navigation from central definitions.
     * Copies central menus/pages/page_groups into tenant tables and
     * maps central_menu_page_defaults (role-based) to tenant access_level_pages.
     */
    protected function provisionNavigationFromCentral(): void
    {
        $now = now();

        // 1. Page Groups
        $centralGroups = CentralPageGroup::on('mysql')->get();
        $groupIdMap = []; // central_id => tenant_id
        foreach ($centralGroups as $group) {
            $tenantId = DB::table('page_groups')->insertGetId([
                'name' => $group->name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $groupIdMap[$group->id] = $tenantId;
        }

        // 2. Menus (parent menus first, then children)
        $centralMenus = CentralMenu::on('mysql')->active()->ordered()->get();
        $menuIdMap = []; // central_id => tenant_id

        // Parents first
        foreach ($centralMenus->whereNull('parent_id') as $menu) {
            $tenantId = DB::table('menus')->insertGetId([
                'name' => $menu->name,
                'icon' => $menu->icon,
                'order' => $menu->order,
                'is_active' => true,
                'parent_id' => null,
                'type' => $menu->type,
                'created_at' => $now,
            ]);
            $menuIdMap[$menu->id] = $tenantId;
        }

        // Children
        foreach ($centralMenus->whereNotNull('parent_id') as $menu) {
            $parentTenantId = $menuIdMap[$menu->parent_id] ?? null;
            if (! $parentTenantId) {
                continue;
            }

            $tenantId = DB::table('menus')->insertGetId([
                'name' => $menu->name,
                'icon' => $menu->icon,
                'order' => $menu->order,
                'is_active' => true,
                'parent_id' => $parentTenantId,
                'type' => $menu->type,
                'created_at' => $now,
            ]);
            $menuIdMap[$menu->id] = $tenantId;
        }

        // 3. Pages (filtered by tenant's active modules if available)
        $centralPages = CentralPage::on('mysql')->active()->get();
        $activeModuleSlugs = $this->getTenantActiveModuleSlugs();
        $pageIdMap = []; // central_id => tenant_id

        foreach ($centralPages as $page) {
            // Skip pages for modules the tenant doesn't have (unless no module assigned)
            if ($activeModuleSlugs !== null && $page->central_module_id) {
                $moduleSlug = $page->module?->slug;
                if ($moduleSlug && ! in_array($moduleSlug, $activeModuleSlugs)) {
                    continue;
                }
            }

            $tenantId = DB::table('pages')->insertGetId([
                'page_name' => $page->page_name,
                'route' => $page->route,
                'controller' => $page->controller ?? 'Laravel',
                'method' => $page->method ?? 'index',
                'menu_controller' => $page->menu_controller ?? '',
                'menu_method' => $page->menu_method ?? '',
                'icon' => $page->icon,
                'notes' => $page->notes ?? '',
                'is_public' => $page->is_public,
                'is_active' => true,
                'page_group_id' => $groupIdMap[$page->central_page_group_id] ?? null,
                'created_at' => $now,
            ]);
            $pageIdMap[$page->id] = $tenantId;
        }

        // 4. Map central_menu_page_defaults → tenant access_level_pages
        $roleToAccessLevel = $this->getRoleToAccessLevelMap();
        $defaults = CentralMenuPageDefault::on('mysql')->where('permission', true)->get();

        foreach ($defaults as $default) {
            $tenantMenuId = $menuIdMap[$default->central_menu_id] ?? null;
            $tenantPageId = $pageIdMap[$default->central_page_id] ?? null;
            $accessLevelId = $roleToAccessLevel[$default->role_slug] ?? null;

            if (! $tenantMenuId || ! $tenantPageId || ! $accessLevelId) {
                continue;
            }

            DB::table('access_level_pages')->insertOrIgnore([
                'menu_id' => $tenantMenuId,
                'page_id' => $tenantPageId,
                'access_level_id' => $accessLevelId,
                'permission' => true,
                'order' => $default->order,
                'dropdown' => $default->dropdown,
                'lib_menu' => $default->lib_menu,
            ]);
        }
    }

    /**
     * Get active module slugs for the current tenant, or null if no plan.
     */
    protected function getTenantActiveModuleSlugs(): ?array
    {
        $tenant = tenant();
        if (! $tenant || ! $tenant->plan) {
            return null; // No plan = include all pages
        }

        return $tenant->activeModules()->pluck('module_slug')->toArray();
    }

    /**
     * Map Role enum values to access_level IDs.
     * Convention: Super Administrador = ID 1, Administrador = ID 2.
     */
    protected function getRoleToAccessLevelMap(): array
    {
        $map = [
            'super_admin' => 1,
            'admin' => 2,
        ];

        // Attempt to find support/user access levels by name
        $levels = DB::table('access_levels')
            ->whereIn('name', ['Suporte', 'Operações', 'Usuário'])
            ->pluck('id', 'name');

        if ($levels->has('Suporte')) {
            $map['support'] = $levels['Suporte'];
        } elseif ($levels->has('Operações')) {
            $map['support'] = $levels['Operações'];
        }

        // user role doesn't typically have an access_level mapping
        // If needed, add it here

        return $map;
    }

    /**
     * Seed sectors without hardcoded manager references.
     * Managers are company-specific and added by each tenant later.
     */
    protected function seedGenericSectors(): void
    {
        $now = now();
        $sectors = [
            'Administrativo', 'Comercial', 'Financeiro', 'Logística',
            'Marketing', 'Operacional', 'Recursos Humanos', 'Tecnologia',
        ];

        foreach ($sectors as $name) {
            \DB::table('sectors')->insertOrIgnore([
                'sector_name' => $name,
                'area_manager_id' => null,
                'sector_manager_id' => null,
                'is_active' => true,
                'created_at' => $now,
            ]);
        }
    }
}
