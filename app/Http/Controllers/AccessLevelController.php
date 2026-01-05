<?php

namespace App\Http\Controllers;

use App\Models\AccessLevel;
use App\Models\AccessLevelPage;
use App\Models\ColorTheme;
use App\Models\Page;
use App\Models\PageGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AccessLevelController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'order');
        $sortDirection = $request->get('direction', 'asc');
        $category = $request->get('category');

        // Validar campos de ordenação permitidos
        $allowedSortFields = ['name', 'order', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'order';
        }

        // Validar direção da ordenação
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = AccessLevel::with('colorTheme');

        // Aplicar busca se fornecida
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filtrar por categoria se fornecida
        if ($category && $category !== '') {
            switch ($category) {
                case 'administrative':
                    $query->administrative();
                    break;
                case 'operational':
                    $query->operational();
                    break;
                case 'financial':
                    $query->financial();
                    break;
                case 'human_resources':
                    $query->humanResources();
                    break;
                case 'commercial':
                    $query->commercial();
                    break;
                case 'management':
                    $query->management();
                    break;
            }
        }

        // Aplicar ordenação
        $query->orderBy($sortField, $sortDirection);

        $accessLevels = $query->paginate($perPage);

        // Buscar todos os menus ativos para o modal de seleção
        $menus = \App\Models\Menu::where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'name', 'icon']);

        // Buscar temas de cores para o formulário de criação
        $colorThemes = ColorTheme::orderBy('name')->get(['id', 'name', 'color_class']);

        return Inertia::render('AccessLevels/Index', [
            'menus' => $menus,
            'colorThemes' => $colorThemes,
            'accessLevels' => $accessLevels->through(function ($accessLevel) {
                return [
                    'id' => $accessLevel->id,
                    'name' => $accessLevel->name,
                    'order' => $accessLevel->order,
                    'color_theme_id' => $accessLevel->color_theme_id,
                    'created_at' => $accessLevel->created_at,
                    'updated_at' => $accessLevel->updated_at,
                    'color' => $accessLevel->color,
                    'color_class' => $accessLevel->color_class,
                    'is_administrative' => $accessLevel->is_administrative,
                    'is_operational' => $accessLevel->is_operational,
                    'is_financial' => $accessLevel->is_financial,
                    'is_human_resources' => $accessLevel->is_human_resources,
                    'is_commercial' => $accessLevel->is_commercial,
                    'is_management' => $accessLevel->is_management,
                    'is_super_admin' => $accessLevel->is_super_admin,
                    'is_level_1' => $accessLevel->is_level_1,
                    'authorized_pages_count' => $accessLevel->authorizedPages()->count(),
                    'total_pages_count' => $accessLevel->pages()->count(),
                ];
            }),
            'categories' => AccessLevel::getCategories(),
            'groupedAccessLevels' => AccessLevel::getGroupedOptions(),
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'category' => $category,
            ],
            'stats' => [
                'total' => AccessLevel::count(),
                'administrative' => AccessLevel::administrative()->count(),
                'operational' => AccessLevel::operational()->count(),
                'financial' => AccessLevel::financial()->count(),
                'human_resources' => AccessLevel::humanResources()->count(),
                'commercial' => AccessLevel::commercial()->count(),
                'management' => AccessLevel::management()->count(),
            ],
        ]);
    }

    public function show(AccessLevel $accessLevel)
    {
        $accessLevel->load('colorTheme', 'pages');

        $accessLevelData = [
                'id' => $accessLevel->id,
                'name' => $accessLevel->name,
                'order' => $accessLevel->order,
                'color_theme_id' => $accessLevel->color_theme_id,
                'created_at' => $accessLevel->created_at,
                'updated_at' => $accessLevel->updated_at,
                'color' => $accessLevel->color,
                'color_class' => $accessLevel->color_class,
                'color_theme' => $accessLevel->colorTheme ? [
                    'id' => $accessLevel->colorTheme->id,
                    'name' => $accessLevel->colorTheme->name,
                    'color' => $accessLevel->colorTheme->color,
                    'bootstrap_class' => $accessLevel->colorTheme->bootstrap_class,
                ] : null,
                'is_administrative' => $accessLevel->is_administrative,
                'is_operational' => $accessLevel->is_operational,
                'is_financial' => $accessLevel->is_financial,
                'is_human_resources' => $accessLevel->is_human_resources,
                'is_commercial' => $accessLevel->is_commercial,
                'is_management' => $accessLevel->is_management,
                'is_super_admin' => $accessLevel->is_super_admin,
                'is_level_1' => $accessLevel->is_level_1,
                'authorized_pages' => $accessLevel->authorizedPages->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'page_name' => $page->page_name,
                        'controller' => $page->controller,
                        'method' => $page->method,
                        'route' => $page->route,
                        'permission' => $page->pivot->permission,
                        'order' => $page->pivot->order,
                        'dropdown' => $page->pivot->dropdown,
                        'lib_menu' => $page->pivot->lib_menu,
                        'menu_id' => $page->pivot->menu_id,
                    ];
                }),
                'total_pages' => $accessLevel->pages->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'page_name' => $page->page_name,
                        'controller' => $page->controller,
                        'method' => $page->method,
                        'route' => $page->route,
                        'permission' => $page->pivot->permission,
                        'order' => $page->pivot->order,
                        'dropdown' => $page->pivot->dropdown,
                        'lib_menu' => $page->pivot->lib_menu,
                        'menu_id' => $page->pivot->menu_id,
                    ];
                }),
        ];

        // Se for uma requisição AJAX, retornar JSON
        if (request()->ajax() || request()->wantsJson() || request()->header('Accept') === 'application/json') {
            return response()->json($accessLevelData);
        }

        // Caso contrário, retornar a view Inertia
        return Inertia::render('AccessLevels/Show', [
            'accessLevel' => $accessLevelData,
        ]);
    }

    /**
     * Get permissions for a specific access level
     */
    public function getPermissions(AccessLevel $accessLevel)
    {
        // Buscar todas as páginas ativas com seus grupos
        $pages = Page::with('pageGroup')
            ->active()
            ->orderBy('page_name')
            ->get();

        // Buscar permissões existentes para este perfil
        $existingPermissions = AccessLevelPage::where('access_level_id', $accessLevel->id)
            ->with('menu')
            ->get()
            ->keyBy('page_id');

        // Buscar todos os menus ativos
        $menus = \App\Models\Menu::where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'name', 'icon']);

        // Formatar páginas para tabela
        $pagesData = [];
        foreach ($pages as $page) {
            $permission = $existingPermissions->get($page->id);

            $pagesData[] = [
                'id' => $page->id,
                'page_name' => $page->page_name,
                'controller' => $page->controller,
                'method' => $page->method,
                'icon' => $page->icon,
                'notes' => $page->notes,
                'page_group' => $page->pageGroup->name ?? 'Outros',
                'has_permission' => $permission ? $permission->permission : false,
                'access_level_page_id' => $permission ? $permission->id : null,
                'menu_id' => $permission ? $permission->menu_id : null,
                'menu_name' => $permission && $permission->menu ? $permission->menu->name : null,
                'dropdown' => $permission ? $permission->dropdown : false,
                'lib_menu' => $permission ? $permission->lib_menu : false,
                'order' => $permission ? $permission->order : 999,
            ];
        }

        return Inertia::render('AccessLevels/Permissions', [
            'accessLevel' => [
                'id' => $accessLevel->id,
                'name' => $accessLevel->name,
                'color' => $accessLevel->color,
                'color_class' => $accessLevel->color_class,
            ],
            'pages' => $pagesData,
            'menus' => $menus,
            'stats' => [
                'total_pages' => $pages->count(),
                'authorized_pages' => $existingPermissions->where('permission', true)->count(),
            ],
        ]);
    }

    /**
     * Update permissions for a specific access level
     */
    public function updatePermissions(Request $request, AccessLevel $accessLevel)
    {
        Log::info('Permissions Update Request', [
            'access_level_id' => $accessLevel->id,
            'headers' => $request->headers->all(),
            'has_csrf' => $request->header('X-CSRF-TOKEN') !== null,
        ]);

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*.page_id' => 'required|exists:pages,id',
            'permissions.*.has_permission' => 'required|boolean',
            'permissions.*.order' => 'required|integer',
            'permissions.*.dropdown' => 'required|boolean',
            'permissions.*.lib_menu' => 'required|boolean',
            'permissions.*.menu_id' => 'nullable|exists:menus,id',
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->permissions as $permission) {
                AccessLevelPage::updateOrCreate(
                    [
                        'access_level_id' => $accessLevel->id,
                        'page_id' => $permission['page_id'],
                    ],
                    [
                        'permission' => $permission['has_permission'],
                        'order' => $permission['order'] ?? 999,
                        'dropdown' => $permission['dropdown'] ?? false,
                        'lib_menu' => $permission['lib_menu'] ?? false,
                        'menu_id' => $permission['menu_id'] ?? null,
                    ]
                );
            }

            DB::commit();

            return redirect()
                ->route('access-levels.permissions', $accessLevel)
                ->with('success', 'Permissões atualizadas com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'Erro ao atualizar permissões: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created access level
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:access_levels,name',
            'color_theme_id' => 'nullable|exists:color_themes,id',
        ]);

        // Obter a próxima ordem disponível
        $maxOrder = AccessLevel::max('order') ?? 0;

        $accessLevel = AccessLevel::create([
            'name' => $request->name,
            'order' => $maxOrder + 1,
            'color_theme_id' => $request->color_theme_id,
        ]);

        return back()->with('success', 'Nivel de acesso criado com sucesso!');
    }

    /**
     * Update an existing access level
     */
    public function update(Request $request, AccessLevel $accessLevel)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:access_levels,name,' . $accessLevel->id,
            'color_theme_id' => 'nullable|exists:color_themes,id',
            'order' => 'nullable|integer|min:1',
        ]);

        $accessLevel->update([
            'name' => $request->name,
            'color_theme_id' => $request->color_theme_id,
            'order' => $request->order ?? $accessLevel->order,
        ]);

        return back()->with('success', 'Nivel de acesso atualizado com sucesso!');
    }

    /**
     * Delete an access level
     */
    public function destroy(AccessLevel $accessLevel)
    {
        // Verificar se há usuários vinculados a este nível de acesso
        // Nota: você pode querer adicionar essa verificação dependendo da estrutura do seu banco

        // Remover todas as permissões associadas
        $accessLevel->accessLevelPages()->delete();

        // Remover o nível de acesso
        $accessLevel->delete();

        return back()->with('success', 'Nivel de acesso excluido com sucesso!');
    }
}
