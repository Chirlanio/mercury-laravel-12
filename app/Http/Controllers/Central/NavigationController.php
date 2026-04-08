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
use Illuminate\Http\Request;
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

        return Inertia::render('Central/Navigation/Index', [
            'menus' => $menus,
            'pages' => $pages,
            'pageGroups' => $pageGroups,
            'defaults' => $defaults,
            'allRoles' => Role::options(),
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
}
