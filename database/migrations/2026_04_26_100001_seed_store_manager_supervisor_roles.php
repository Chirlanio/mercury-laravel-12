<?php

use App\Enums\Role;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cria 3 roles operacionais novas: Lojas, Gerente e Supervisão Comercial.
 *
 * Hierarchy nos gaps existentes (sem shift em cascata):
 *   - store (Lojas):                  3 (acima de support=2)
 *   - manager (Gerente):              5
 *   - commercial_supervisor:          7 (abaixo de finance/accounting/fiscal/marketing=8)
 *
 * Escopo de loja:
 *   - store, manager: usuários ficam restritos a user.store_id pelo padrão de
 *     cada módulo (ausência da permission MANAGE_X faz scoping automático).
 *   - commercial_supervisor: vê todas as lojas (sem scoping). Permissions
 *     específicas (incluindo MANAGE_X conforme o módulo) serão atribuídas
 *     via /admin/roles-permissions, sem deploy.
 *
 * As 3 roles entram no DB com is_system=false — editáveis pelo SaaS admin.
 * O conjunto inicial é mínimo (dashboard + perfil próprio); demais permissões
 * serão cadastradas posteriormente.
 *
 * syncWithoutDetaching para não remover atribuições customizadas que possam
 * ter sido feitas via SaaS admin entre deploys.
 */
return new class extends Migration
{
    /** @var array<string,int> slug → hierarchy_level */
    private const NEW_ROLES = [
        'store' => 3,
        'manager' => 5,
        'commercial_supervisor' => 7,
    ];

    public function up(): void
    {
        $this->seedRoles();
        $this->syncRolePermissions();
        $this->syncMenuDefaultsFromSuperAdmin();
        $this->clearCache();
    }

    public function down(): void
    {
        // Remove menu defaults das roles novas
        CentralMenuPageDefault::whereIn('role_slug', array_keys(self::NEW_ROLES))->delete();

        // Remove role_permissions + roles
        $newRoleIds = CentralRole::whereIn('name', array_keys(self::NEW_ROLES))->pluck('id')->toArray();
        if (! empty($newRoleIds)) {
            DB::table('central_role_permissions')
                ->whereIn('central_role_id', $newRoleIds)
                ->delete();
        }
        CentralRole::whereIn('name', array_keys(self::NEW_ROLES))->delete();

        $this->clearCache();
    }

    // ---------------------------------------------------------------------
    // Passos
    // ---------------------------------------------------------------------

    private function seedRoles(): void
    {
        foreach (self::NEW_ROLES as $slug => $level) {
            $roleEnum = Role::tryFrom($slug);
            CentralRole::updateOrCreate(
                ['name' => $slug],
                [
                    'label' => $roleEnum?->label() ?? ucfirst($slug),
                    'hierarchy_level' => $level,
                    'is_system' => false, // editáveis no SaaS admin
                    'is_active' => true,
                ]
            );
        }
    }

    private function syncRolePermissions(): void
    {
        $permissions = CentralPermission::all()->keyBy('slug');

        foreach (self::NEW_ROLES as $slug => $_) {
            $roleModel = CentralRole::where('name', $slug)->first();
            $roleEnum = Role::tryFrom($slug);
            if (! $roleModel || ! $roleEnum) {
                continue;
            }

            $permSlugs = $roleEnum->permissions();

            $permIds = $permissions
                ->filter(fn ($p) => in_array($p->slug, $permSlugs, true))
                ->pluck('id')
                ->toArray();

            if (empty($permIds)) {
                continue;
            }

            $roleModel->permissions()->syncWithoutDetaching($permIds);
        }
    }

    /**
     * Para cada role nova copia as entries de `super_admin` em
     * `central_menu_page_defaults` filtradas pelos groups de permissão que a
     * role realmente tem. Como o set inicial é mínimo (3 permissions sem
     * group módulo), na prática só páginas legacy sem central_module_id
     * passam — sidebar fica enxuta até as permissions de módulo serem
     * atribuídas via SaaS admin.
     */
    private function syncMenuDefaultsFromSuperAdmin(): void
    {
        $superAdminDefaults = CentralMenuPageDefault::where('role_slug', 'super_admin')->get();
        if ($superAdminDefaults->isEmpty()) {
            return;
        }

        $permissions = CentralPermission::all();
        $rolePermGroups = [];
        foreach (self::NEW_ROLES as $slug => $_) {
            $roleEnum = Role::tryFrom($slug);
            if (! $roleEnum) {
                continue;
            }
            $roleSlugs = $roleEnum->permissions();
            $rolePermGroups[$slug] = $permissions
                ->filter(fn ($p) => in_array($p->slug, $roleSlugs, true))
                ->pluck('group')
                ->unique()
                ->filter()
                ->values()
                ->toArray();
        }

        $pageModules = DB::table('central_pages')
            ->leftJoin('central_modules', 'central_pages.central_module_id', '=', 'central_modules.id')
            ->select('central_pages.id', 'central_modules.slug as module_slug')
            ->get()
            ->keyBy('id');

        foreach (self::NEW_ROLES as $slug => $_) {
            $allowedGroups = $rolePermGroups[$slug] ?? [];

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
                        'role_slug' => $slug,
                    ],
                    [
                        'permission' => true,
                        'order' => $default->order,
                        'dropdown' => (bool) $default->dropdown,
                        'lib_menu' => (bool) $default->lib_menu,
                    ]
                );
            }
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
