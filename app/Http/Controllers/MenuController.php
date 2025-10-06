<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'order');
        $sortDirection = $request->get('direction', 'asc');
        $type = $request->get('type');

        // Validar campos de ordenação permitidos
        $allowedSortFields = ['name', 'order', 'is_active', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'order';
        }

        // Validar direção da ordenação
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = Menu::query();

        // Aplicar busca se fornecida
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filtrar por tipo se fornecido
        if ($type) {
            switch ($type) {
                case 'main':
                    $query->mainMenu();
                    break;
                case 'hr':
                    $query->hrMenu();
                    break;
                case 'utility':
                    $query->utilityMenu();
                    break;
                case 'system':
                    $query->systemMenu();
                    break;
            }
        }

        // Aplicar ordenação
        $query->orderBy($sortField, $sortDirection);

        $menus = $query->paginate($perPage);

        // Calcular próxima ordem disponível
        $nextOrder = Menu::max('order') + 1;

        return Inertia::render('Menu/Index', [
            'menus' => $menus->through(function ($menu) {
                return [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'icon' => $menu->icon,
                    'order' => $menu->order,
                    'is_active' => $menu->is_active,
                    'created_at' => $menu->created_at,
                    'updated_at' => $menu->updated_at,
                    'is_main_menu' => $menu->is_main_menu,
                    'is_utility_menu' => $menu->is_utility_menu,
                    'is_hr_menu' => $menu->is_hr_menu,
                    'is_system_menu' => $menu->is_system_menu,
                ];
            }),
            'types' => Menu::getTypes(),
            'groupedMenus' => Menu::getGroupedOptions(),
            'nextOrder' => $nextOrder,
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'type' => $type,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'order' => 'required|integer|min:1',
            'parent_id' => 'nullable|exists:menus,id',
            'is_active' => 'boolean',
        ]);

        // Se não foi especificado is_active, definir como true por padrão
        $validated['is_active'] = $validated['is_active'] ?? true;

        $menu = Menu::create($validated);

        return redirect()->route('menus.index')->with('success', 'Menu criado com sucesso!');
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'order' => 'required|integer|min:1',
            'parent_id' => 'nullable|exists:menus,id',
            'is_active' => 'boolean',
        ]);

        // Validar que o menu não seja seu próprio pai
        if (isset($validated['parent_id']) && $validated['parent_id'] == $menu->id) {
            return back()->withErrors(['parent_id' => 'Um menu não pode ser seu próprio pai.']);
        }

        $menu->update($validated);

        return redirect()->route('menus.index')->with('success', 'Menu atualizado com sucesso!');
    }

    public function show(Request $request, Menu $menu)
    {
        $menuData = [
            'id' => $menu->id,
            'name' => $menu->name,
            'icon' => $menu->icon,
            'order' => $menu->order,
            'parent_id' => $menu->parent_id,
            'is_active' => $menu->is_active,
            'created_at' => $menu->created_at,
            'updated_at' => $menu->updated_at,
            'is_main_menu' => $menu->is_main_menu,
            'is_utility_menu' => $menu->is_utility_menu,
            'is_hr_menu' => $menu->is_hr_menu,
            'is_system_menu' => $menu->is_system_menu,
            'parent' => $menu->parent ? [
                'id' => $menu->parent->id,
                'name' => $menu->parent->name,
            ] : null,
        ];

        // Se for uma requisição AJAX (do GenericDetailModal), retornar JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($menuData);
        }

        // Caso contrário, retornar a view Inertia
        return Inertia::render('Menu/Show', [
            'menu' => $menuData,
        ]);
    }

    public function activate(Menu $menu)
    {
        $menu->activate();

        return back()->with('success', 'Menu ativado com sucesso!');
    }

    public function deactivate(Menu $menu)
    {
        $menu->deactivate();

        return back()->with('success', 'Menu desativado com sucesso!');
    }

    public function moveUp(Menu $menu)
    {
        if ($menu->moveUp()) {
            return back()->with('success', 'Menu movido para cima com sucesso!');
        }

        return back()->withErrors(['order' => 'Não é possível mover este menu para cima.']);
    }

    public function moveDown(Menu $menu)
    {
        if ($menu->moveDown()) {
            return back()->with('success', 'Menu movido para baixo com sucesso!');
        }

        return back()->withErrors(['order' => 'Não é possível mover este menu para baixo.']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'menu_ids' => 'required|array',
            'menu_ids.*' => 'exists:menus,id',
        ]);

        Menu::reorderMenus($request->menu_ids);

        return back()->with('success', 'Ordem dos menus atualizada com sucesso!');
    }

    /**
     * Retorna menus baseados na estrutura antiga (menus com children)
     * @deprecated Usar getDynamicSidebarMenus() para menu baseado em access_level_pages
     */
    public function getSidebarMenus()
    {
        $menuGroups = [
            'main' => Menu::active()->mainMenu()->parentMenus()->ordered()->with('children')->get(['id', 'name', 'icon', 'parent_id']),
            'hr' => Menu::active()->hrMenu()->parentMenus()->ordered()->with('children')->get(['id', 'name', 'icon', 'parent_id']),
            'utility' => Menu::active()->utilityMenu()->parentMenus()->ordered()->with('children')->get(['id', 'name', 'icon', 'parent_id']),
            'system' => Menu::active()->systemMenu()->parentMenus()->ordered()->with('children')->get(['id', 'name', 'icon', 'parent_id']),
        ];

        return response()->json($menuGroups);
    }

    /**
     * Retorna menus dinâmicos baseados no nível de acesso do usuário autenticado
     * Usa access_level_pages para determinar quais páginas aparecem em cada menu
     */
    public function getDynamicSidebarMenus()
    {
        // Sempre retornar estrutura padrão mesmo sem usuário
        $menuGroups = [
            'main' => [],
            'hr' => [],
            'utility' => [],
            'system' => [],
        ];

        $user = auth()->user();

        if (!$user || !$user->access_level_id) {
            return response()->json($menuGroups);
        }

        try {
            // Usar MenuService para obter a estrutura de menu
            $menuStructure = \App\Services\MenuService::getMenuForAccessLevel($user->access_level_id);

            foreach ($menuStructure as $menu) {
                // Carregar o menu completo para verificar suas flags
                $menuModel = Menu::find($menu['id']);

                if (!$menuModel) {
                    continue;
                }

                // Determinar em qual grupo colocar o menu
                $group = 'main'; // padrão
                if ($menuModel->is_hr_menu) {
                    $group = 'hr';
                } elseif ($menuModel->is_utility_menu) {
                    $group = 'utility';
                } elseif ($menuModel->is_system_menu) {
                    $group = 'system';
                }

                $menuGroups[$group][] = $menu;
            }
        } catch (\Exception $e) {
            \Log::error('Erro ao carregar menus dinâmicos: ' . $e->getMessage());
        }

        return response()->json($menuGroups);
    }
}