<?php

namespace App\Services;

use App\Models\CentralMenuPageDefault;
use App\Models\CentralRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the sidebar menu from central database tables.
 *
 * Replaces the legacy MenuService that used per-tenant access_level_pages.
 * Menu structure is defined centrally by the SaaS admin and filtered per
 * tenant based on their plan's active modules and the user's role.
 */
class CentralMenuResolver
{
    /**
     * Get the complete menu structure for a user, filtered by role and tenant modules.
     *
     * @return array [{id, name, icon, order, direct_items: [...], dropdown_items: [...]}]
     */
    public function getMenuForUser(User $user): array
    {
        $roleSlug = $user->role->value;
        $tenant = tenant();
        $tenantId = $tenant?->id ?? 'none';

        return Cache::store('file')->remember(
            "central_menu:{$roleSlug}:{$tenantId}",
            300, // 5 minutes
            fn () => $this->resolveMenu($roleSlug, $tenant)
        );
    }

    /**
     * Clear cached menu entries for a given role/tenant combination.
     *
     * Behavior matrix:
     *  - Both args given: forget exactly "central_menu:{role}:{tenant}".
     *  - Only $roleSlug:  forget that role for every known tenant + "none".
     *  - Only $tenantId:  forget every known role for that specific tenant.
     *  - No args:         forget every (role, tenant) combination + "none".
     *
     * Roles are read from the central_roles table (source of truth), so
     * DB-only roles like `store_manager` or `drivers` — added via the SaaS
     * admin UI — are covered. Falls back to the enum default set if the
     * central DB is unreachable. Tenant IDs come from the central Tenant
     * model (plus the literal 'none' sentinel used when resolving the menu
     * outside any tenant context).
     */
    public function clearCache(?string $roleSlug = null, ?string $tenantId = null): void
    {
        $cache = Cache::store('file');

        // Targeted single-key invalidation — no DB reads needed.
        if ($roleSlug && $tenantId) {
            $cache->forget("central_menu:{$roleSlug}:{$tenantId}");

            return;
        }

        $roles = $roleSlug
            ? [$roleSlug]
            : $this->resolveKnownRoles();

        $tenantIds = $tenantId
            ? [$tenantId]
            : $this->resolveKnownTenantIds();

        foreach ($roles as $role) {
            foreach ($tenantIds as $tid) {
                $cache->forget("central_menu:{$role}:{$tid}");
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resolveKnownRoles(): array
    {
        try {
            $fromDb = CentralRole::on('mysql')->pluck('name')->all();
            if (! empty($fromDb)) {
                return $fromDb;
            }
        } catch (\Exception $e) {
            // Central DB unreachable — fall through to defaults.
        }

        return ['super_admin', 'admin', 'support', 'user', 'drivers', 'store_manager'];
    }

    /**
     * @return array<int, string>
     */
    protected function resolveKnownTenantIds(): array
    {
        try {
            $ids = Tenant::on('mysql')->pluck('id')->all();
        } catch (\Exception $e) {
            $ids = [];
        }

        // Always include the 'none' sentinel used when getMenuForUser()
        // runs without a tenant context (local dev, tests, central admin).
        $ids[] = 'none';

        return array_values(array_unique($ids));
    }

    protected function resolveMenu(string $roleSlug, $tenant): array
    {
        // Get active module slugs for this tenant's plan
        $activeModuleSlugs = [];
        if ($tenant) {
            $activeModuleSlugs = $tenant->activeModules()->pluck('module_slug')->toArray();
        }

        // Fetch menu-page assignments for this role from the central DB
        try {
            $defaults = CentralMenuPageDefault::on('mysql')
                ->with(['menu', 'page.module'])
                ->where('role_slug', $roleSlug)
                ->where('permission', true)
                ->where('lib_menu', true)
                ->whereHas('menu', fn ($q) => $q->where('is_active', true))
                ->whereHas('page', fn ($q) => $q->where('is_active', true))
                ->get();
        } catch (\Exception $e) {
            // Central DB unavailable — return empty menu
            return [];
        }

        // Filter by tenant's active modules
        $filtered = $defaults->filter(function ($default) use ($activeModuleSlugs, $tenant) {
            // No tenant context (tests / local dev) — allow all
            if (! $tenant) {
                return true;
            }

            // If the page is linked to a module, check if it's active
            if ($default->page->central_module_id) {
                $moduleSlug = $default->page->module?->slug;

                return $moduleSlug && in_array($moduleSlug, $activeModuleSlugs);
            }

            // Pages without a module are always visible
            return true;
        });

        return $this->buildMenuStructure($filtered);
    }

    protected function buildMenuStructure($defaults): array
    {
        $grouped = $defaults->groupBy(fn ($d) => $d->menu->id);

        $menuStructure = [];

        foreach ($grouped as $menuId => $items) {
            $menu = $items->first()->menu;

            [$dropdownItems, $directItems] = $items->partition(fn ($item) => $item->dropdown);

            $mapItem = fn ($item) => [
                'id' => $item->page->id,
                'name' => $item->page->page_name,
                'route' => $item->page->route,
                'icon' => $item->page->icon,
                'order' => $item->order,
            ];

            $mappedDirect = $directItems->map($mapItem)->sortBy('order')->values();
            $mappedDropdown = $dropdownItems->map($mapItem)->sortBy('order')->values();

            // Deduplicate by route
            $dedup = fn ($items) => $items->unique(fn ($item) => $item['route'])->values()->all();

            if ($mappedDirect->isNotEmpty() || $mappedDropdown->isNotEmpty()) {
                $menuStructure[] = [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'icon' => $menu->icon,
                    'order' => $menu->order,
                    'type' => $menu->type,
                    'direct_items' => $dedup($mappedDirect),
                    'dropdown_items' => $dedup($mappedDropdown),
                ];
            }
        }

        return collect($menuStructure)->sortBy('order')->values()->toArray();
    }
}
