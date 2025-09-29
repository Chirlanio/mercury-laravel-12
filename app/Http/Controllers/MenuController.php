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
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'type' => $type,
            ],
        ]);
    }

    public function show(Menu $menu)
    {
        return Inertia::render('Menu/Show', [
            'menu' => [
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
            ],
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
}