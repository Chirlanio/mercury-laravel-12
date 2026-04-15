<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Enums\Role;
use App\Models\CentralActivityLog;
use App\Models\CentralMenu;
use App\Models\CentralMenuPageDefault;
use App\Models\CentralModule;
use App\Models\CentralPage;
use App\Models\CentralPageGroup;
use App\Models\CentralRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NavigationController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'menus');

        $menus = CentralMenu::with('children')
            ->parentMenus()
            ->ordered()
            ->get()
            ->map(fn ($menu) => [
                'id' => $menu->id,
                'name' => $menu->name,
                'icon' => $menu->icon,
                'order' => $menu->order,
                'type' => $menu->type,
                'is_active' => $menu->is_active,
                'children' => $menu->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'icon' => $child->icon,
                    'order' => $child->order,
                    'is_active' => $child->is_active,
                ]),
                'pages_count' => CentralMenuPageDefault::where('central_menu_id', $menu->id)
                    ->distinct('central_page_id')
                    ->count('central_page_id'),
            ]);

        $pages = CentralPage::with(['pageGroup', 'module'])
            ->orderBy('page_name')
            ->get()
            ->map(fn ($page) => [
                'id' => $page->id,
                'page_name' => $page->page_name,
                'route' => $page->route,
                'icon' => $page->icon,
                'is_public' => $page->is_public,
                'is_active' => $page->is_active,
                'page_group' => $page->pageGroup?->name,
                'module' => $page->module ? [
                    'id' => $page->module->id,
                    'name' => $page->module->name,
                    'slug' => $page->module->slug,
                ] : null,
            ]);

        $pageGroups = CentralPageGroup::withCount('pages')
            ->orderBy('name')
            ->get();

        // Defaults matrix: for each menu, which pages are assigned per role
        $defaults = CentralMenuPageDefault::with(['menu', 'page'])
            ->orderBy('order')
            ->get()
            ->groupBy('central_menu_id')
            ->map(fn ($group) => $group->groupBy('role_slug'));

        // Roles come from the central_roles table (source of truth), so
        // DB-only roles like `store_manager` and `drivers` — added via the
        // SaaS admin UI, not present in the Role enum — show up in the
        // permissions matrix. Falls back to the enum if the table is empty
        // (fresh install before seeders run).
        $centralRoles = CentralRole::active()
            ->ordered()
            ->get(['name', 'label'])
            ->mapWithKeys(fn ($r) => [$r->name => $r->label ?: $r->name])
            ->all();

        $allRoles = ! empty($centralRoles) ? $centralRoles : Role::options();

        return Inertia::render('Central/Navigation/Index', [
            'menus' => $menus,
            'pages' => $pages,
            'pageGroups' => $pageGroups,
            'defaults' => $defaults,
            'allRoles' => $allRoles,
            'allModules' => CentralModule::active()->ordered()->get(['id', 'name', 'slug']),
            'tab' => $tab,
        ]);
    }

    // --- Menus ---

    public function storeMenu(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:220',
            'icon' => 'nullable|string|max:40',
            'type' => 'required|in:main,hr,utility,system',
            'parent_id' => 'nullable|exists:central_menus,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['order'] = CentralMenu::when(
            $validated['parent_id'] ?? null,
            fn ($q) => $q->where('parent_id', $validated['parent_id']),
            fn ($q) => $q->whereNull('parent_id'),
        )->max('order') + 1;

        $validated['is_active'] = $validated['is_active'] ?? true;

        $menu = CentralMenu::create($validated);

        CentralActivityLog::log('navigation.menu_created', "Menu '{$menu->name}' criado");

        return back()->with('success', "Menu '{$menu->name}' criado.");
    }

    public function updateMenu(Request $request, CentralMenu $menu)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:220',
            'icon' => 'nullable|string|max:40',
            'type' => 'sometimes|in:main,hr,utility,system',
            'is_active' => 'sometimes|boolean',
        ]);

        $menu->update($validated);

        CentralActivityLog::log('navigation.menu_updated', "Menu '{$menu->name}' atualizado");

        return back()->with('success', "Menu '{$menu->name}' atualizado.");
    }

    /**
     * Bulk-reorder menus. Accepts a flat list [{id, order, parent_id?}, ...]
     * — typically sent after a drag-drop or move-up/down action in the UI.
     * Wrapped in a transaction so a partial failure leaves the old ordering
     * intact. The observer on CentralMenu clears the sidebar cache once the
     * transaction commits.
     */
    public function reorderMenus(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:central_menus,id',
            'items.*.order' => 'required|integer|min:0',
            'items.*.parent_id' => 'nullable|exists:central_menus,id',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                CentralMenu::where('id', $item['id'])->update([
                    'order' => $item['order'],
                    'parent_id' => $item['parent_id'] ?? null,
                ]);
            }
        });

        CentralActivityLog::log('navigation.menus_reordered', 'Ordem dos menus atualizada (' . count($validated['items']) . ' itens)');

        return back()->with('success', 'Ordem dos menus atualizada.');
    }

    public function destroyMenu(CentralMenu $menu)
    {
        $defaultsCount = CentralMenuPageDefault::where('central_menu_id', $menu->id)->count();
        if ($defaultsCount > 0) {
            return back()->with('error', "Menu '{$menu->name}' possui {$defaultsCount} página(s) vinculada(s). Remova os vínculos antes de excluir.");
        }

        $name = $menu->name;
        $menu->delete();

        CentralActivityLog::log('navigation.menu_deleted', "Menu '{$name}' excluído");

        return back()->with('success', "Menu '{$name}' excluído.");
    }

    // --- Pages ---

    public function storePage(Request $request)
    {
        $validated = $request->validate([
            'page_name' => 'required|string|max:220',
            'route' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:40',
            'notes' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'central_page_group_id' => 'nullable|exists:central_page_groups,id',
            'central_module_id' => 'nullable|exists:central_modules,id',
        ]);

        $validated['is_public'] = $validated['is_public'] ?? false;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $page = CentralPage::create($validated);

        CentralActivityLog::log('navigation.page_created', "Página '{$page->page_name}' criada");

        return back()->with('success', "Página '{$page->page_name}' criada.");
    }

    public function updatePage(Request $request, CentralPage $page)
    {
        $validated = $request->validate([
            'page_name' => 'sometimes|string|max:220',
            'route' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:40',
            'notes' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'central_page_group_id' => 'nullable|exists:central_page_groups,id',
            'central_module_id' => 'nullable|exists:central_modules,id',
        ]);

        $page->update($validated);

        CentralActivityLog::log('navigation.page_updated', "Página '{$page->page_name}' atualizada");

        return back()->with('success', "Página '{$page->page_name}' atualizada.");
    }

    public function destroyPage(CentralPage $page)
    {
        $name = $page->page_name;

        // Remove associated defaults
        CentralMenuPageDefault::where('central_page_id', $page->id)->delete();
        $page->delete();

        CentralActivityLog::log('navigation.page_deleted', "Página '{$name}' excluída");

        return back()->with('success', "Página '{$name}' excluída.");
    }

    // --- Page Groups ---

    public function storePageGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:central_page_groups,name',
        ]);

        CentralPageGroup::create($validated);

        return back()->with('success', "Grupo '{$validated['name']}' criado.");
    }

    public function updatePageGroup(Request $request, CentralPageGroup $pageGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:central_page_groups,name,' . $pageGroup->id,
        ]);

        $pageGroup->update($validated);

        return back()->with('success', "Grupo atualizado.");
    }

    public function destroyPageGroup(CentralPageGroup $pageGroup)
    {
        if ($pageGroup->pages()->exists()) {
            return back()->with('error', 'Grupo possui páginas vinculadas.');
        }

        $pageGroup->delete();

        return back()->with('success', 'Grupo excluído.');
    }

    // --- Menu-Page Defaults ---

    public function updateDefaults(Request $request)
    {
        $validated = $request->validate([
            'defaults' => 'required|array',
            'defaults.*.central_menu_id' => 'required|exists:central_menus,id',
            'defaults.*.central_page_id' => 'required|exists:central_pages,id',
            'defaults.*.role_slug' => 'required|string',
            'defaults.*.permission' => 'required|boolean',
            'defaults.*.order' => 'required|integer',
            'defaults.*.dropdown' => 'required|boolean',
            'defaults.*.lib_menu' => 'required|boolean',
        ]);

        foreach ($validated['defaults'] as $default) {
            if ($default['permission']) {
                CentralMenuPageDefault::updateOrCreate(
                    [
                        'central_menu_id' => $default['central_menu_id'],
                        'central_page_id' => $default['central_page_id'],
                        'role_slug' => $default['role_slug'],
                    ],
                    [
                        'permission' => true,
                        'order' => $default['order'],
                        'dropdown' => $default['dropdown'],
                        'lib_menu' => $default['lib_menu'],
                    ]
                );
            } else {
                // Remove the default if permission is false
                CentralMenuPageDefault::where([
                    'central_menu_id' => $default['central_menu_id'],
                    'central_page_id' => $default['central_page_id'],
                    'role_slug' => $default['role_slug'],
                ])->delete();
            }
        }

        CentralActivityLog::log('navigation.defaults_updated', 'Permissões padrão de navegação atualizadas');

        return back()->with('success', 'Permissões padrão atualizadas.');
    }

    /**
     * Create a single menu↔page↔role assignment. Used by the "Atribuir
     * página a menu" modal.
     *
     * Business rule: a page lives in exactly ONE menu at a time. If the
     * page is already assigned to a different menu (for any role), those
     * rows are deleted first so assigning it to menu B effectively moves
     * it out of menu A. This prevents the "same link in two places"
     * duplication the SaaS admin ran into.
     */
    public function storeDefault(Request $request)
    {
        $validated = $request->validate([
            'central_menu_id' => 'required|exists:central_menus,id',
            'central_page_id' => 'required|exists:central_pages,id',
            'role_slug' => 'required|string|max:60',
            'permission' => 'sometimes|boolean',
            'order' => 'sometimes|integer|min:0',
            'dropdown' => 'sometimes|boolean',
            'lib_menu' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($validated) {
            // Purge any rows for this page in OTHER menus (across all roles).
            // We iterate via get()->each() instead of bulk ->delete() so
            // the observer fires and clears the resolver cache for each
            // affected role.
            CentralMenuPageDefault::where('central_page_id', $validated['central_page_id'])
                ->where('central_menu_id', '!=', $validated['central_menu_id'])
                ->get()
                ->each(fn ($row) => $row->delete());

            // Default order: last position inside this menu/role bucket.
            $nextOrder = CentralMenuPageDefault::where('central_menu_id', $validated['central_menu_id'])
                ->where('role_slug', $validated['role_slug'])
                ->max('order') + 1;

            $default = CentralMenuPageDefault::updateOrCreate(
                [
                    'central_menu_id' => $validated['central_menu_id'],
                    'central_page_id' => $validated['central_page_id'],
                    'role_slug' => $validated['role_slug'],
                ],
                [
                    'permission' => $validated['permission'] ?? true,
                    'order' => $validated['order'] ?? $nextOrder,
                    'dropdown' => $validated['dropdown'] ?? false,
                    'lib_menu' => $validated['lib_menu'] ?? true,
                ]
            );

            CentralActivityLog::log(
                'navigation.default_created',
                "Página #{$default->central_page_id} vinculada ao menu #{$default->central_menu_id} para role '{$default->role_slug}'"
            );
        });

        return back()->with('success', 'Página vinculada ao menu.');
    }

    /**
     * Remove every role assignment of a page inside a specific menu in
     * one shot. Saves the admin from clicking the trash icon once per
     * role cell in the matrix.
     *
     * Uses get()->each() to fire the observer per row (cache cleared
     * granularly per role); bulk Eloquent delete() would skip events.
     */
    public function destroyAllDefaultsForPage(CentralMenu $menu, CentralPage $page)
    {
        $rows = CentralMenuPageDefault::where('central_menu_id', $menu->id)
            ->where('central_page_id', $page->id)
            ->get();

        $count = $rows->count();
        $rows->each(fn ($row) => $row->delete());

        CentralActivityLog::log(
            'navigation.defaults_bulk_deleted',
            "Removidos {$count} vínculo(s) da página '{$page->page_name}' no menu '{$menu->name}'"
        );

        return back()->with('success', "Página removida do menu ({$count} vínculo(s)).");
    }

    /**
     * Remove a single menu↔page↔role assignment. The tenant stops seeing
     * that page in that menu slot for that role once the cache clears
     * (handled automatically by CentralMenuPageDefaultObserver::deleted).
     */
    public function destroyDefault(CentralMenuPageDefault $default)
    {
        $menuId = $default->central_menu_id;
        $pageId = $default->central_page_id;
        $roleSlug = $default->role_slug;

        $default->delete();

        CentralActivityLog::log(
            'navigation.default_deleted',
            "Vínculo removido: página #{$pageId} do menu #{$menuId} para role '{$roleSlug}'"
        );

        return back()->with('success', 'Vínculo removido.');
    }

    /**
     * Toggle the permission flag on a single default row. Optimistic path
     * for the editable permissions matrix — one click, one PATCH.
     */
    public function togglePermission(CentralMenuPageDefault $default)
    {
        $default->update(['permission' => ! $default->permission]);

        CentralActivityLog::log(
            'navigation.default_toggled',
            "Permissão " . ($default->permission ? 'liberada' : 'bloqueada') .
            " para página #{$default->central_page_id} no menu #{$default->central_menu_id} (role: {$default->role_slug})"
        );

        return back()->with('success', $default->permission ? 'Acesso liberado.' : 'Acesso bloqueado.');
    }

    /**
     * Bulk-reorder page assignments inside a menu. The UI sends
     * [{id, order}, ...] covering every role row of the pages being
     * moved, so role_slug ordering stays consistent across the matrix.
     *
     * Iterates via get()->each() instead of Query Builder ->update() so
     * the observer fires and the sidebar cache is invalidated per
     * affected role. Bulk Eloquent updates skip model events.
     */
    public function reorderDefaults(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:central_menu_page_defaults,id',
            'items.*.order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $ids = collect($validated['items'])->pluck('id')->all();
            $rows = CentralMenuPageDefault::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($validated['items'] as $item) {
                $row = $rows->get($item['id']);
                if ($row) {
                    $row->update(['order' => $item['order']]);
                }
            }
        });

        CentralActivityLog::log('navigation.defaults_reordered', 'Ordem de páginas dentro de menus atualizada (' . count($validated['items']) . ' itens)');

        return back()->with('success', 'Ordem atualizada.');
    }
}
