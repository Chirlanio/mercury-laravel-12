<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralPage;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cria as 3 roles permanentes de áreas funcionais (Financeira,
 * Contabilidade, Fiscal) e sincroniza permissões + sidebar defaults.
 *
 * Hierarchy shift (escala com gaps intencionais — deixa espaço para roles
 * intermediárias futuras sem shift em cascata):
 *   - super_admin:  4 → 10
 *   - admin:        3 → 9
 *   - finance:      novo, 8
 *   - accounting:   novo, 8
 *   - fiscal:       novo, 8
 *   - support:      2 (mantém)
 *   - user/driver:  1 (mantém)
 *
 * Scope de permissões definido em `Role::permissions()` no enum. A sync
 * aqui é idempotente — usa `syncWithoutDetaching` para não remover
 * atribuições customizadas adicionadas via SaaS admin.
 *
 * Sidebar: cada role nova herda as entries em `central_menu_page_defaults`
 * que `super_admin` já tem, mas filtradas para páginas cujo módulo tem
 * pelo menos 1 permissão assignada. Isso evita mostrar no sidebar link
 * para uma tela que vai dar 403 na hora.
 */
return new class extends Migration
{
    /** @var array<string,int> slug → hierarchy_level */
    private const NEW_ROLES = [
        'finance' => 8,
        'accounting' => 8,
        'fiscal' => 8,
    ];

    /** Roles que têm hierarchy_level deslocado. */
    private const SHIFTED_HIERARCHY = [
        'admin' => ['old' => 3, 'new' => 9],
        'super_admin' => ['old' => 4, 'new' => 10],
    ];

    public function up(): void
    {
        $this->shiftExistingHierarchy();
        $this->seedRoles();
        $this->syncRolePermissions();
        $this->syncMenuDefaultsFromSuperAdmin();
        $this->clearMenuCache();
    }

    public function down(): void
    {
        // 1. Remove menu_page_defaults das roles novas
        CentralMenuPageDefault::whereIn('role_slug', array_keys(self::NEW_ROLES))->delete();

        // 2. Remove role_permissions + roles
        $newRoleIds = CentralRole::whereIn('name', array_keys(self::NEW_ROLES))->pluck('id')->toArray();
        if (! empty($newRoleIds)) {
            DB::table('central_role_permissions')
                ->whereIn('central_role_id', $newRoleIds)
                ->delete();
        }
        CentralRole::whereIn('name', array_keys(self::NEW_ROLES))->delete();

        // 3. Desfaz shift do hierarchy_level
        foreach (self::SHIFTED_HIERARCHY as $slug => $levels) {
            CentralRole::where('name', $slug)->update(['hierarchy_level' => $levels['old']]);
        }

        $this->clearMenuCache();
    }

    // ---------------------------------------------------------------------
    // Passos
    // ---------------------------------------------------------------------

    private function shiftExistingHierarchy(): void
    {
        foreach (self::SHIFTED_HIERARCHY as $slug => $levels) {
            CentralRole::where('name', $slug)->update(['hierarchy_level' => $levels['new']]);
        }
    }

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
     * Para cada role nova, copia as entries de `super_admin` em
     * `central_menu_page_defaults` — mas só para páginas cujo módulo tem
     * pelo menos 1 permissão atribuída à role. Isso garante que o sidebar
     * só mostre itens acessíveis.
     *
     * Sem `central_page.central_module_id` o filtro é impossível — páginas
     * não vinculadas a módulo são copiadas por segurança (compat com páginas
     * legacy).
     */
    private function syncMenuDefaultsFromSuperAdmin(): void
    {
        $superAdminDefaults = CentralMenuPageDefault::where('role_slug', 'super_admin')->get();
        if ($superAdminDefaults->isEmpty()) {
            return;
        }

        // Pré-calcula groups de permissão por role (ex: finance → ['dre','budgets','order_payments',...])
        $rolePermGroups = [];
        $permissions = CentralPermission::all();
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

        // Mapa page → módulo slug. Requer central_pages.central_module_id
        // join com central_modules.slug.
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

                // Regra: se a página tem módulo E a role não tem permission
                // em nenhum módulo ou o módulo específico não está nos groups,
                // pula. Páginas sem módulo são incluídas (legacy / core).
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

    private function clearMenuCache(): void
    {
        try {
            app(\App\Services\CentralMenuResolver::class)->clearCache();
            app(\App\Services\CentralRoleResolver::class)->clearCache();
        } catch (\Throwable $e) {
            // Resolvers podem não estar disponíveis em ambientes legacy.
        }
    }
};
