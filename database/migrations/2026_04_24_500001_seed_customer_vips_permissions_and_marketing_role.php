<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds para o sub-módulo Clientes VIP (Black/Gold — curadoria de Marketing).
 *
 * - Registra as 6 permissions `customer_vips.*` em `central_permissions`.
 * - Atribui essas permissions a super_admin, admin e marketing.
 * - Cria a role permanente `marketing` (hierarchy_level = 8, idem FINANCE/ACCOUNTING/FISCAL).
 * - Sincroniza menu defaults do super_admin para a role marketing, filtrados
 *   pelas permissions que a role realmente tem (evita sidebar com 403).
 *
 * Observação: não cria `central_modules` novo. As permissions VIP convivem
 * dentro do módulo `customers` (já existente) — ativar Customers num plano
 * já libera o VIP para tenants que tenham usuários com Role::MARKETING.
 */
return new class extends Migration
{
    private const PERMISSION_PREFIX = 'customer_vips.';

    private const MARKETING_ROLE_SLUG = 'marketing';

    private const MARKETING_HIERARCHY = 8;

    public function up(): void
    {
        $this->seedPermissions();
        $this->seedMarketingRole();
        $this->syncAllRolePermissions();
        $this->syncMenuDefaultsForMarketing();
        $this->clearCache();
    }

    public function down(): void
    {
        // Remove menu defaults da role marketing
        CentralMenuPageDefault::where('role_slug', self::MARKETING_ROLE_SLUG)->delete();

        // Slugs VIP
        $slugs = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, self::PERMISSION_PREFIX))
            ->pluck('value')
            ->toArray();

        $permIds = CentralPermission::whereIn('slug', $slugs)->pluck('id')->toArray();

        // Remove atribuições de permissions a todas roles
        if (! empty($permIds)) {
            DB::table('central_role_permissions')
                ->whereIn('central_permission_id', $permIds)
                ->delete();
        }

        // Remove a role marketing e todas as suas atribuições
        $marketingRole = CentralRole::where('name', self::MARKETING_ROLE_SLUG)->first();
        if ($marketingRole) {
            DB::table('central_role_permissions')
                ->where('central_role_id', $marketingRole->id)
                ->delete();
            $marketingRole->delete();
        }

        // Remove permissions VIP
        CentralPermission::whereIn('slug', $slugs)->delete();

        $this->clearCache();
    }

    // ---------------------------------------------------------------------
    // Passos
    // ---------------------------------------------------------------------

    private function seedPermissions(): void
    {
        $vipPerms = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, self::PERMISSION_PREFIX));

        foreach ($vipPerms as $perm) {
            $group = explode('.', $perm->value)[0]; // 'customer_vips'
            CentralPermission::updateOrCreate(
                ['slug' => $perm->value],
                [
                    'label' => $perm->label(),
                    'description' => $perm->description(),
                    'group' => $group,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedMarketingRole(): void
    {
        $roleEnum = Role::MARKETING;

        CentralRole::updateOrCreate(
            ['name' => self::MARKETING_ROLE_SLUG],
            [
                'label' => $roleEnum->label(),
                'hierarchy_level' => self::MARKETING_HIERARCHY,
                'is_system' => false,
                'is_active' => true,
            ],
        );
    }

    /**
     * Sincroniza todas as permissions VIP para cada role que deve tê-las
     * (conforme Role::permissions() do enum). Idempotente via syncWithoutDetaching.
     */
    private function syncAllRolePermissions(): void
    {
        $permissions = CentralPermission::all()->keyBy('slug');
        $roles = CentralRole::all()->keyBy('name');

        foreach (Role::cases() as $roleEnum) {
            $role = $roles[$roleEnum->value] ?? null;
            if (! $role) {
                continue;
            }

            $permSlugs = collect($roleEnum->permissions())
                ->filter(fn ($slug) => str_starts_with($slug, self::PERMISSION_PREFIX))
                ->toArray();

            if (empty($permSlugs)) {
                continue;
            }

            $permIds = $permissions
                ->filter(fn ($p) => in_array($p->slug, $permSlugs, true))
                ->pluck('id')
                ->toArray();

            if (empty($permIds)) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($permIds);
        }
    }

    /**
     * Copia menu defaults do super_admin para a role marketing, filtrando
     * páginas cujo módulo a role não tem permission. Páginas sem módulo
     * (core / legacy) são incluídas por padrão.
     */
    private function syncMenuDefaultsForMarketing(): void
    {
        $superAdminDefaults = CentralMenuPageDefault::where('role_slug', 'super_admin')->get();
        if ($superAdminDefaults->isEmpty()) {
            return;
        }

        $roleEnum = Role::MARKETING;
        $roleSlugs = $roleEnum->permissions();
        $allPerms = CentralPermission::all();

        $allowedGroups = $allPerms
            ->filter(fn ($p) => in_array($p->slug, $roleSlugs, true))
            ->pluck('group')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $pageModules = DB::table('central_pages')
            ->leftJoin('central_modules', 'central_pages.central_module_id', '=', 'central_modules.id')
            ->select('central_pages.id', 'central_modules.slug as module_slug')
            ->get()
            ->keyBy('id');

        foreach ($superAdminDefaults as $default) {
            $pageInfo = $pageModules->get($default->central_page_id);
            $moduleSlug = $pageInfo?->module_slug;

            if ($moduleSlug !== null && ! in_array($moduleSlug, $allowedGroups, true)) {
                continue;
            }

            CentralMenuPageDefault::updateOrCreate(
                [
                    'central_menu_id' => $default->central_menu_id,
                    'central_page_id' => $default->central_page_id,
                    'role_slug' => self::MARKETING_ROLE_SLUG,
                ],
                [
                    'permission' => true,
                    'order' => $default->order,
                    'dropdown' => (bool) $default->dropdown,
                    'lib_menu' => (bool) $default->lib_menu,
                ],
            );
        }
    }

    private function clearCache(): void
    {
        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
            app(\App\Services\CentralRoleResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // Resolvers podem não estar disponíveis em ambientes legacy.
        }
    }
};
