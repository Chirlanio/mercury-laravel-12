<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'page_name');
        $sortDirection = $request->get('direction', 'asc');
        $groupId = $request->get('group_id');
        $isActive = $request->get('is_active');
        $isPublic = $request->get('is_public');

        // Validar campos de ordenação permitidos
        $allowedSortFields = ['page_name', 'controller', 'method', 'is_active', 'is_public', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'page_name';
        }

        // Validar direção da ordenação
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = Page::with('pageGroup');

        // Aplicar busca se fornecida
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('page_name', 'like', "%{$search}%")
                  ->orWhere('controller', 'like', "%{$search}%")
                  ->orWhere('method', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Filtrar por grupo se fornecido
        if ($groupId) {
            $query->where('page_group_id', $groupId);
        }

        // Filtrar por status ativo se fornecido
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        // Filtrar por público/privado se fornecido
        if ($isPublic !== null) {
            $query->where('is_public', $isPublic);
        }

        // Aplicar ordenação
        $query->orderBy($sortField, $sortDirection);

        $pages = $query->paginate($perPage);

        return Inertia::render('Pages/Index', [
            'pages' => $pages->through(function ($page) {
                return [
                    'id' => $page->id,
                    'page_name' => $page->page_name,
                    'controller' => $page->controller,
                    'method' => $page->method,
                    'menu_controller' => $page->menu_controller,
                    'menu_method' => $page->menu_method,
                    'notes' => $page->notes,
                    'is_public' => $page->is_public,
                    'is_active' => $page->is_active,
                    'icon' => $page->icon,
                    'created_at' => $page->created_at,
                    'updated_at' => $page->updated_at,
                    'page_group' => $page->pageGroup ? [
                        'id' => $page->pageGroup->id,
                        'name' => $page->pageGroup->name,
                    ] : null,
                    'route' => $page->route,
                    'menu_route' => $page->menu_route,
                    'full_name' => $page->pageGroup ? $page->full_name : $page->page_name,
                ];
            }),
            'pageGroups' => PageGroup::orderBy('name')->pluck('name', 'id'),
            'groupedPages' => Page::getGroupedOptions(),
            'crudPages' => Page::getCrudPages(),
            'controllerMethods' => Page::getControllerMethods(),
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'group_id' => $groupId,
                'is_active' => $isActive,
                'is_public' => $isPublic,
            ],
            'stats' => [
                'total' => Page::count(),
                'active' => Page::active()->count(),
                'inactive' => Page::inactive()->count(),
                'public' => Page::public()->count(),
                'private' => Page::private()->count(),
            ],
        ]);
    }

    public function show(Page $page)
    {
        $page->load('pageGroup', 'accessLevels');

        $pageData = [
            'id' => $page->id,
            'page_name' => $page->page_name,
            'controller' => $page->controller,
            'method' => $page->method,
            'menu_controller' => $page->menu_controller,
            'menu_method' => $page->menu_method,
            'notes' => $page->notes,
            'is_public' => $page->is_public,
            'is_active' => $page->is_active,
            'icon' => $page->icon,
            'created_at' => $page->created_at,
            'updated_at' => $page->updated_at,
            'page_group' => [
                'id' => $page->pageGroup->id,
                'name' => $page->pageGroup->name,
            ],
            'route' => $page->route,
            'menu_route' => $page->menu_route,
            'full_name' => $page->full_name,
            'access_levels' => $page->accessLevels->map(function ($accessLevel) {
                return [
                    'id' => $accessLevel->id,
                    'name' => $accessLevel->name,
                    'permission' => $accessLevel->pivot->permission,
                    'order' => $accessLevel->pivot->order,
                    'dropdown' => $accessLevel->pivot->dropdown,
                    'lib_menu' => $accessLevel->pivot->lib_menu,
                    'menu_id' => $accessLevel->pivot->menu_id,
                ];
            }),
            'access_level_count' => $page->getAccessLevelCount(),
            'is_accessible_to_all' => $page->isAccessibleToAll(),
        ];

        // Se for uma requisição AJAX, retornar JSON
        if (request()->ajax() || request()->wantsJson() || request()->header('Accept') === 'application/json') {
            return response()->json($pageData);
        }

        // Caso contrário, retornar a view Inertia (para compatibilidade)
        return Inertia::render('Pages/Show', [
            'page' => $pageData,
        ]);
    }

    public function activate(Page $page)
    {
        $page->activate();

        return back()->with('success', 'Página ativada com sucesso!');
    }

    public function deactivate(Page $page)
    {
        $page->deactivate();

        return back()->with('success', 'Página desativada com sucesso!');
    }

    public function makePublic(Page $page)
    {
        $page->makePublic();

        return back()->with('success', 'Página tornada pública com sucesso!');
    }

    public function makePrivate(Page $page)
    {
        $page->makePrivate();

        return back()->with('success', 'Página tornada privada com sucesso!');
    }


    public function store(Request $request)
    {
        $request->validate([
            'page_name' => 'required|string|max:255',
            'controller' => 'required|string|max:255',
            'method' => 'required|string|max:255',
            'menu_controller' => 'nullable|string|max:255',
            'menu_method' => 'nullable|string|max:255',
            'route' => 'required|string|max:100',
            'notes' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'page_group_id' => 'required|exists:page_groups,id',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Verificar se já existe uma página com o mesmo controller e method
        $existingPage = Page::where('controller', $request->controller)
                           ->where('method', $request->method)
                           ->first();

        if ($existingPage) {
            return back()->withErrors([
                'controller' => 'Já existe uma página com este Controller e Método.'
            ])->withInput();
        }

        $page = Page::create([
            'page_name' => $request->page_name,
            'controller' => $request->controller,
            'method' => $request->method,
            'menu_controller' => $request->menu_controller,
            'menu_method' => $request->menu_method,
            'route' => $request->route,
            'notes' => $request->notes,
            'icon' => $request->icon,
            'page_group_id' => $request->page_group_id,
            'is_public' => $request->boolean('is_public', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Página criada com sucesso!');
    }

    public function update(Request $request, Page $page)
    {
        $request->validate([
            'page_name' => 'required|string|max:255',
            'controller' => 'required|string|max:255',
            'method' => 'required|string|max:255',
            'menu_controller' => 'nullable|string|max:255',
            'menu_method' => 'nullable|string|max:255',
            'route' => 'required|string|max:100',
            'notes' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'page_group_id' => 'required|exists:page_groups,id',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Verificar se já existe uma página com o mesmo controller e method (exceto a atual)
        $existingPage = Page::where('controller', $request->controller)
                           ->where('method', $request->method)
                           ->where('id', '!=', $page->id)
                           ->first();

        if ($existingPage) {
            return back()->withErrors([
                'controller' => 'Já existe uma página com este Controller e Método.'
            ])->withInput();
        }

        $page->update([
            'page_name' => $request->page_name,
            'controller' => $request->controller,
            'method' => $request->method,
            'menu_controller' => $request->menu_controller,
            'menu_method' => $request->menu_method,
            'route' => $request->route,
            'notes' => $request->notes,
            'icon' => $request->icon,
            'page_group_id' => $request->page_group_id,
            'is_public' => $request->boolean('is_public', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Página atualizada com sucesso!');
    }
}