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
 * Registra o módulo `budgets` (Orçamentos) — primeira fase do módulo
 * principal após a fundação (CC + AC + MC) estar pronta.
 *
 * - central_modules + 7 permissions granulares
 * - CentralPage /budgets vinculada ao menu "Financeiro"
 * - Habilitado nos planos Professional e Enterprise (Starter não tem
 *   Budgets — é módulo de valor agregado)
 */
return new class extends Migration
{
    private const MODULE_SLUG = 'budgets';
    private const PERMISSION_PREFIX = 'budgets.';
    private const PAGE_ROUTE = '/budgets';
    private const PAGE_NAME = 'Orçamentos';
    private const PAGE_ICON = 'fas fa-money-bill-trend-up';
    private const PARENT_MENU_NAME = 'Financeiro';

    public function up(): void
    {
        $this->seedModule();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->seedPage();
        $this->seedMenuPageDefaults();
        $this->addModuleToPlans();
    }

    public function down(): void
    {
        DB::table('tenant_modules')
            ->where('module_slug', self::MODULE_SLUG)
            ->delete();

        $page = CentralPage::where('route', self::PAGE_ROUTE)->first();
        if ($page) {
            CentralMenuPageDefault::where('central_page_id', $page->id)->delete();
            $page->delete();
        }

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

        CentralModule::where('slug', self::MODULE_SLUG)->delete();
    }

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

        $allowedRoles = ['super_admin', 'admin', 'support', 'store_manager'];
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

            // Budgets é valor agregado — Professional e Enterprise só
            $isEnabled = in_array($plan->slug, ['professional', 'enterprise']);

            DB::table('tenant_modules')->insert([
                'plan_id' => $plan->id,
                'module_slug' => self::MODULE_SLUG,
                'is_enabled' => $isEnabled,
            ]);
        }
    }
};
