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
 * Registra o módulo `dre` (DRE Gerencial) no SaaS central — fecha o playbook
 * de 14 prompts com a fase final de disponibilidade: menu, permissões e
 * habilitação por plano.
 *
 * Entregas:
 * - `central_modules` com a entrada `dre` + dependências (AC + CC).
 * - 8 `central_permissions` (dre.*) + sync de role_permissions para SUPER_ADMIN
 *   e ADMIN (espelha o fallback do `Role` enum).
 * - 6 `central_pages` representando os entry points da UI (matrix, management-
 *   lines, mappings, periods, imports/actuals, imports/budgets) sob o grupo
 *   "Listar" e vinculadas ao módulo `dre`.
 * - `central_menu_page_defaults` dos 6 pages sob o menu "Financeiro",
 *   visíveis para 4 roles (super_admin, admin, support, store_manager);
 *   o `CentralMenuResolver` filtra depois por permission do usuário, então
 *   quem não tiver `dre.view` simplesmente não vê.
 * - `tenant_modules` — habilitado em Professional e Enterprise (Starter
 *   fica sem DRE; é módulo de valor agregado, exige fundação contábil).
 *
 * Segue o padrão dos módulos Budgets / ManagementClasses / AccountingClasses
 * (mesmos timestamps `2026_04_20_*00001`).
 */
return new class extends Migration
{
    private const MODULE_SLUG = 'dre';
    private const PERMISSION_PREFIX = 'dre.';
    private const PARENT_MENU_NAME = 'Financeiro';

    /**
     * Lista de páginas a cadastrar. Ordem aqui determina o sort_order
     * relativo no menu (primeira entrada aparece mais perto do topo).
     *
     * @var array<int,array{route:string,name:string,icon:string}>
     */
    private const PAGES = [
        ['route' => '/dre/matrix', 'name' => 'DRE Gerencial', 'icon' => 'fas fa-chart-line'],
        ['route' => '/dre/management-lines', 'name' => 'Plano Gerencial DRE', 'icon' => 'fas fa-list'],
        ['route' => '/dre/mappings', 'name' => 'De-para DRE', 'icon' => 'fas fa-link'],
        ['route' => '/dre/periods', 'name' => 'Fechamentos DRE', 'icon' => 'fas fa-calendar-check'],
        ['route' => '/dre/imports/actuals', 'name' => 'Importar Realizado DRE', 'icon' => 'fas fa-file-import'],
        ['route' => '/dre/imports/budgets', 'name' => 'Importar Orçado DRE', 'icon' => 'fas fa-file-import'],
    ];

    public function up(): void
    {
        $this->seedModule();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->seedPages();
        $this->seedMenuPageDefaults();
        $this->addModuleToPlans();

        // Menu cache é file-based com TTL de 5min — sem invalidação explícita
        // usuários veriam sidebar stale. Clear por role (não por tenant, que
        // aqui é central).
        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // Resolver pode não existir em ambientes legacy — não bloqueia o up.
        }
    }

    public function down(): void
    {
        // 1. Revert tenant_modules
        DB::table('tenant_modules')
            ->where('module_slug', self::MODULE_SLUG)
            ->delete();

        // 2. Menu page defaults + pages
        $pageRoutes = array_column(self::PAGES, 'route');
        $pageIds = CentralPage::whereIn('route', $pageRoutes)->pluck('id')->toArray();
        if (! empty($pageIds)) {
            CentralMenuPageDefault::whereIn('central_page_id', $pageIds)->delete();
            CentralPage::whereIn('id', $pageIds)->delete();
        }

        // 3. Role permissions + permissions
        $slugs = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, self::PERMISSION_PREFIX))
            ->pluck('value')
            ->toArray();

        $permIds = CentralPermission::whereIn('slug', $slugs)->pluck('id')->toArray();
        if (! empty($permIds)) {
            DB::table('central_role_permissions')
                ->whereIn('central_permission_id', $permIds)
                ->delete();
        }
        CentralPermission::whereIn('slug', $slugs)->delete();

        // 4. Module
        CentralModule::where('slug', self::MODULE_SLUG)->delete();

        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // ---------------------------------------------------------------------
    // Seeders (mesmo padrão das 4 migrations anteriores)
    // ---------------------------------------------------------------------

    private function seedModule(): void
    {
        $def = config('modules.'.self::MODULE_SLUG);

        if (! $def) {
            return;
        }

        CentralModule::updateOrCreate(
            ['slug' => self::MODULE_SLUG],
            [
                'name' => $def['name'],
                'description' => $def['description'] ?? null,
                'icon' => $def['icon'] ?? null,
                'routes' => $def['routes'] ?? [],
                'dependencies' => $def['dependencies'] ?? null,
                'is_active' => true,
                'sort_order' => (CentralModule::max('sort_order') ?? 0) + 1,
            ]
        );
    }

    private function seedPermissions(): void
    {
        $modulePerms = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, self::PERMISSION_PREFIX));

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

            $permSlugs = collect($roleEnum->permissions())
                ->filter(fn ($slug) => str_starts_with($slug, self::PERMISSION_PREFIX))
                ->toArray();

            $permIds = $permissions
                ->filter(fn ($p) => in_array($p->slug, $permSlugs))
                ->pluck('id')
                ->toArray();

            if (empty($permIds)) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($permIds);
        }
    }

    private function seedPages(): void
    {
        $listarGroup = CentralPageGroup::where('name', 'Listar')->first();
        $moduleId = CentralModule::where('slug', self::MODULE_SLUG)->value('id');

        foreach (self::PAGES as $page) {
            CentralPage::updateOrCreate(
                ['route' => $page['route']],
                [
                    'page_name' => $page['name'],
                    'icon' => $page['icon'],
                    'is_public' => false,
                    'is_active' => true,
                    'central_page_group_id' => $listarGroup?->id,
                    'central_module_id' => $moduleId,
                ]
            );
        }
    }

    private function seedMenuPageDefaults(): void
    {
        $menuId = CentralMenu::whereNull('parent_id')
            ->where('name', self::PARENT_MENU_NAME)
            ->value('id');

        if (! $menuId) {
            return;
        }

        $allowedRoles = ['super_admin', 'admin', 'support', 'store_manager'];
        $nextOrder = (CentralMenuPageDefault::max('order') ?? 0) + 1;

        foreach (self::PAGES as $pageDef) {
            $page = CentralPage::where('route', $pageDef['route'])->first();
            if (! $page) {
                continue;
            }

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
                    ]
                );
            }

            $nextOrder++;
        }
    }

    private function addModuleToPlans(): void
    {
        $plans = DB::table('tenant_plans')->get();

        foreach ($plans as $plan) {
            $exists = DB::table('tenant_modules')
                ->where('plan_id', $plan->id)
                ->where('module_slug', self::MODULE_SLUG)
                ->exists();

            if ($exists) {
                continue;
            }

            // DRE é módulo de valor agregado — libera só em Professional
            // e Enterprise (Starter não tem fundação contábil suficiente
            // para um relatório executivo).
            $isEnabled = in_array($plan->slug, ['professional', 'enterprise']);

            DB::table('tenant_modules')->insert([
                'plan_id' => $plan->id,
                'module_slug' => self::MODULE_SLUG,
                'is_enabled' => $isEnabled,
            ]);
        }
    }
};
