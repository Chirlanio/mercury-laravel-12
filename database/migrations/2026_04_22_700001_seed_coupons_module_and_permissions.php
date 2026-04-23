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
 * Seeds the Coupons module: central_modules, central_permissions,
 * central_role_permissions, central_pages, central_menu_page_defaults
 * (para a página de config de redes sociais — a página principal
 * /coupons será adicionada na Fase 3 quando o controller existir),
 * e tenant_modules.
 *
 * A página /coupons em si NÃO é registrada aqui — fica pra Fase 3
 * junto com o CouponController, pra não criar menu "fantasma" que
 * levaria a 404. Esta migration habilita módulo + config auxiliar.
 */
return new class extends Migration
{
    private const MODULE_SLUG = 'coupons';
    private const PERMISSION_PREFIX = 'coupons.';
    private const CONFIG_PAGE_ROUTE = '/config/social-media';
    private const CONFIG_PAGE_NAME = 'Redes Sociais';
    private const CONFIG_PARENT_MENU_NAME = 'Configurações';

    public function up(): void
    {
        $this->seedModule();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->seedConfigPage();
        $this->seedConfigMenuPageDefaults();
        $this->addModuleToPlans();
    }

    public function down(): void
    {
        DB::table('tenant_modules')
            ->where('module_slug', self::MODULE_SLUG)
            ->delete();

        $pageIds = CentralPage::whereIn('route', [self::CONFIG_PAGE_ROUTE])->pluck('id')->toArray();
        if (! empty($pageIds)) {
            CentralMenuPageDefault::whereIn('central_page_id', $pageIds)->delete();
            CentralPage::whereIn('id', $pageIds)->delete();
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

    private function seedConfigPage(): void
    {
        $listarGroup = CentralPageGroup::where('name', 'Listar')->first();
        $configModuleId = CentralModule::where('slug', 'config')->value('id');

        CentralPage::updateOrCreate(
            ['route' => self::CONFIG_PAGE_ROUTE],
            [
                'page_name' => self::CONFIG_PAGE_NAME,
                'icon' => 'fas fa-cog',
                'is_public' => false,
                'is_active' => true,
                'central_page_group_id' => $listarGroup?->id,
                'central_module_id' => $configModuleId,
            ]
        );
    }

    private function seedConfigMenuPageDefaults(): void
    {
        $menuId = CentralMenu::whereNull('parent_id')
            ->where('name', self::CONFIG_PARENT_MENU_NAME)
            ->value('id');

        $page = CentralPage::where('route', self::CONFIG_PAGE_ROUTE)->first();

        if (! $menuId || ! $page) {
            return;
        }

        // Apenas admins gerenciam redes sociais (MANAGE_SETTINGS)
        $allowedRoles = ['super_admin', 'admin'];
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

            // Coupons disponível em Professional e Enterprise
            $isEnabled = in_array($plan->slug, ['professional', 'enterprise']);

            DB::table('tenant_modules')->insert([
                'plan_id' => $plan->id,
                'module_slug' => self::MODULE_SLUG,
                'is_enabled' => $isEnabled,
            ]);
        }
    }
};
